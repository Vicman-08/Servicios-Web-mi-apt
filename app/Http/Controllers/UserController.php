<?php

namespace App\Http\Controllers;

use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $data = $request->validate([
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        return UserResource::collection(
            User::orderBy('created_at', 'desc')
                ->paginate($data['per_page'] ?? 20)
                ->withQueryString(),
        );
    }

    public function register(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:100'],
            'email' => ['required', 'email', 'max:150', Rule::unique(User::class, 'email')],
            'password' => ['required', 'string', 'min:8', 'max:72'],
        ]);

        $user = User::create([
            'name' => trim($data['name']),
            'email' => strtolower($data['email']),
            'password' => $data['password'],
            'role' => 'buyer',
            'status' => 'active',
        ]);

        return response()->json([
            'message' => 'Cuenta de cliente creada correctamente.',
            'data' => (new UserResource($user))->resolve($request),
        ], 201);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:100'],
            'email' => ['required', 'email', 'max:150', Rule::unique(User::class, 'email')],
            'password' => ['required', 'string', 'min:8', 'max:72'],
            'role' => ['required', Rule::in(['admin', 'buyer'])],
            'status' => ['sometimes', Rule::in(['active', 'disabled'])],
        ]);

        $user = User::create([
            ...$data,
            'name' => trim($data['name']),
            'email' => strtolower($data['email']),
            'status' => $data['status'] ?? 'active',
        ]);

        return response()->json([
            'message' => 'Cuenta creada correctamente.',
            'data' => (new UserResource($user))->resolve($request),
        ], 201);
    }

    public function show(User $user): UserResource
    {
        return new UserResource($user);
    }

    public function update(Request $request, User $user): UserResource
    {
        $data = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'min:2', 'max:100'],
            'email' => ['sometimes', 'required', 'email', 'max:150', Rule::unique(User::class, 'email')->ignore($user)],
            'password' => ['nullable', 'string', 'min:8', 'max:72'],
            'role' => ['sometimes', Rule::in(['admin', 'buyer'])],
            'status' => ['sometimes', Rule::in(['active', 'disabled'])],
        ]);

        if (array_key_exists('name', $data)) {
            $data['name'] = trim($data['name']);
        }

        if (array_key_exists('email', $data)) {
            $data['email'] = strtolower($data['email']);
        }

        if (empty($data['password'])) {
            unset($data['password']);
        }

        $user->update($data);

        return new UserResource($user->fresh());
    }

    public function destroy(Request $request, User $user): JsonResponse
    {
        if ($request->user()->is($user)) {
            return response()->json(['message' => 'No puedes eliminar tu propia cuenta.'], 422);
        }

        $user->tokens()->delete();
        $user->delete();

        return response()->json(['message' => 'Usuario eliminado correctamente.']);
    }

    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $user = User::where('email', strtolower($data['email']))->first();

        if (! $user || ! Hash::check($data['password'], $user->password)) {
            return response()->json(['message' => 'Credenciales incorrectas.'], 401);
        }

        if (($user->status ?? 'active') !== 'active') {
            return response()->json(['message' => 'La cuenta está deshabilitada.'], 403);
        }

        $expirationMinutes = max(1, (int) config('sanctum.expiration', 5));
        $expiresAt = now()->addMinutes($expirationMinutes);
        $token = $user->createToken('interfaz-web', ['*'], $expiresAt)->plainTextToken;

        return response()->json([
            'message' => 'Inicio de sesión exitoso.',
            'data' => [
                'token' => $token,
                'expires_at' => $expiresAt->toIso8601String(),
                'expires_in' => $expirationMinutes * 60,
                'user' => (new UserResource($user))->resolve($request),
            ],
        ]);
    }

    public function me(Request $request): UserResource
    {
        return new UserResource($request->user());
    }

    public function updateProfile(Request $request): UserResource
    {
        $data = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'min:2', 'max:100'],
            'phone' => ['nullable', 'string', 'max:30'],
            'password' => ['nullable', 'string', 'min:8', 'max:72'],
            'addresses' => ['sometimes', 'array', 'max:5'],
            'addresses.*.label' => ['required', 'string', 'max:50'],
            'addresses.*.recipient' => ['required', 'string', 'max:100'],
            'addresses.*.line1' => ['required', 'string', 'max:200'],
            'addresses.*.line2' => ['nullable', 'string', 'max:200'],
            'addresses.*.city' => ['required', 'string', 'max:100'],
            'addresses.*.state' => ['required', 'string', 'max:100'],
            'addresses.*.postal_code' => ['required', 'string', 'max:20'],
            'addresses.*.country' => ['required', 'string', 'size:2'],
        ]);

        if (array_key_exists('name', $data)) {
            $data['name'] = trim($data['name']);
        }

        if (empty($data['password'])) {
            unset($data['password']);
        }

        $request->user()->update($data);

        return new UserResource($request->user()->fresh());
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()?->delete();

        return response()->json(['message' => 'Sesión cerrada.']);
    }
}
