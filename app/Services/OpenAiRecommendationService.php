<?php

namespace App\Services;

use App\Exceptions\AiServiceException;
use App\Models\AiInteraction;
use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use JsonException;
use Throwable;

class OpenAiRecommendationService
{
    /**
     * @param  Collection<int, Product>  $products
     * @return array{answer: string, recommended_product_ids: array<int, string>, provider: string, model: string}
     */
    public function recommend(string $query, Collection $products, ?User $user, string $anonymousIdentifier): array
    {
        $startedAt = hrtime(true);
        $model = (string) config('services.openai.model', 'gpt-5.6-luna');
        $key = (string) config('services.openai.key');

        if ($key === '') {
            $message = 'El servicio de IA no está configurado. Agrega OPENAI_API_KEY en el archivo .env del servidor.';
            $this->record($user, $query, null, $model, 'error', $startedAt, ['error' => 'missing_api_key']);

            throw new AiServiceException($message);
        }

        try {
            $response = Http::baseUrl(rtrim((string) config('services.openai.base_url'), '/'))
                ->withToken($key)
                ->acceptJson()
                ->timeout((int) config('services.openai.timeout', 30))
                ->post('/responses', [
                    'model' => $model,
                    'store' => false,
                    'instructions' => $this->instructions(),
                    'input' => $this->input($query, $products),
                    'reasoning' => ['effort' => 'low'],
                    'max_output_tokens' => 500,
                    'safety_identifier' => hash('sha256', $user?->getKey() ?: $anonymousIdentifier),
                    'text' => [
                        'verbosity' => 'low',
                        'format' => $this->responseFormat(),
                    ],
                ]);

            if ($response->failed()) {
                $message = (string) ($response->json('error.message') ?: 'OpenAI no pudo generar las recomendaciones.');
                $this->record($user, $query, null, $model, 'error', $startedAt, [
                    'http_status' => $response->status(),
                    'error' => mb_substr($message, 0, 500),
                ]);

                throw new AiServiceException('El servicio externo de IA no está disponible en este momento.');
            }

            try {
                $outputText = $this->outputText($response->json());
            } catch (AiServiceException $exception) {
                $this->record($user, $query, null, $model, 'error', $startedAt, ['error' => 'missing_or_refused_output']);

                throw $exception;
            }
            $decoded = json_decode($outputText, true, flags: JSON_THROW_ON_ERROR);
            $answer = trim((string) ($decoded['answer'] ?? ''));
            $allowedIds = $products->map(fn ($product): string => (string) $product->getKey())->all();
            $recommendedIds = collect($decoded['recommended_product_ids'] ?? [])
                ->filter(fn ($id): bool => is_string($id) && in_array($id, $allowedIds, true))
                ->unique()
                ->take(3)
                ->values()
                ->all();

            if ($answer === '') {
                $this->record($user, $query, null, $model, 'error', $startedAt, ['error' => 'empty_answer']);

                throw new AiServiceException('La IA devolvió una respuesta vacía.');
            }

            $usedModel = (string) ($response->json('model') ?: $model);
            $this->record($user, $query, $answer, $usedModel, 'success', $startedAt, [
                'external_id' => $response->json('id'),
                'recommended_product_ids' => $recommendedIds,
                'usage' => $response->json('usage'),
            ]);

            return [
                'answer' => $answer,
                'recommended_product_ids' => $recommendedIds,
                'provider' => 'openai',
                'model' => $usedModel,
            ];
        } catch (AiServiceException $exception) {
            throw $exception;
        } catch (JsonException $exception) {
            $this->record($user, $query, null, $model, 'error', $startedAt, ['error' => 'invalid_json_response']);

            throw new AiServiceException('La IA devolvió una respuesta que no se pudo interpretar.', previous: $exception);
        } catch (Throwable $exception) {
            $this->record($user, $query, null, $model, 'error', $startedAt, ['error' => class_basename($exception)]);

            throw new AiServiceException('No fue posible conectar con el servicio externo de IA.', previous: $exception);
        }
    }

    private function instructions(): string
    {
        return <<<'PROMPT'
Eres el asistente de compras de una tienda. Responde siempre en español.
Recomienda como máximo tres productos y utiliza exclusivamente identificadores incluidos en el catálogo recibido.
Prioriza que el producto satisfaga la necesidad, tenga existencias y respete cualquier presupuesto indicado.
No inventes características. Si la solicitud no se relaciona con el catálogo, devuelve una explicación breve y una lista vacía.
PROMPT;
    }

    /** @param  Collection<int, Product>  $products */
    private function input(string $query, Collection $products): string
    {
        $catalog = $products->map(fn ($product): array => [
            'id' => (string) $product->getKey(),
            'name' => $product->name,
            'description' => mb_substr((string) $product->description, 0, 300),
            'price' => (string) $product->price,
            'currency' => $product->currency,
            'stock' => (int) $product->stock,
            'tags' => array_slice($product->tags ?? [], 0, 10),
        ])->values()->all();

        return "Solicitud del usuario:\n{$query}\n\nCatálogo disponible en JSON:\n".json_encode(
            $catalog,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR,
        );
    }

    /** @return array<string, mixed> */
    private function responseFormat(): array
    {
        return [
            'type' => 'json_schema',
            'name' => 'product_recommendations',
            'strict' => true,
            'schema' => [
                'type' => 'object',
                'properties' => [
                    'answer' => ['type' => 'string'],
                    'recommended_product_ids' => [
                        'type' => 'array',
                        'items' => ['type' => 'string'],
                        'maxItems' => 3,
                    ],
                ],
                'required' => ['answer', 'recommended_product_ids'],
                'additionalProperties' => false,
            ],
        ];
    }

    /** @param  array<string, mixed>  $response */
    private function outputText(array $response): string
    {
        foreach ($response['output'] ?? [] as $output) {
            if (($output['type'] ?? null) !== 'message') {
                continue;
            }

            foreach ($output['content'] ?? [] as $content) {
                if (($content['type'] ?? null) === 'refusal') {
                    throw new AiServiceException((string) ($content['refusal'] ?? 'La IA rechazó la solicitud.'));
                }

                if (($content['type'] ?? null) === 'output_text') {
                    return (string) ($content['text'] ?? '');
                }
            }
        }

        throw new AiServiceException('OpenAI no devolvió texto en la respuesta.');
    }

    /** @param  array<string, mixed>  $metadata */
    private function record(
        ?User $user,
        string $query,
        ?string $response,
        string $model,
        string $status,
        int $startedAt,
        array $metadata,
    ): void {
        AiInteraction::create([
            'user_id' => $user ? (string) $user->getKey() : null,
            'query' => $query,
            'response' => $response,
            'provider' => 'openai',
            'model' => $model,
            'status' => $status,
            'duration_ms' => max(0, (int) round((hrtime(true) - $startedAt) / 1_000_000)),
            'metadata' => $metadata,
        ]);
    }
}
