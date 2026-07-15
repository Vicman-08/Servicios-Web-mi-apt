<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

class AiInteraction extends Model
{
    public const UPDATED_AT = null;

    protected $connection = 'mongodb';

    protected $table = 'ai_interactions';

    protected $fillable = [
        'user_id',
        'query',
        'response',
        'provider',
        'model',
        'status',
        'duration_ms',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'duration_ms' => 'integer',
        ];
    }
}
