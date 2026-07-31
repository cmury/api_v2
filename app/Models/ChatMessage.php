<?php

namespace App\Models;

use App\Support\DataDatabase;
use App\Support\ProductChatTables;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChatMessage extends Model
{
    protected $fillable = [
        'thread_id',
        'role',
        'content',
        'sql',
        'payload',
    ];

    public function __construct(array $attributes = [])
    {
        $this->table = ProductChatTables::messages();
        parent::__construct($attributes);
    }

    protected function casts(): array
    {
        return [
            'payload' => 'array',
        ];
    }

    public function getConnectionName(): ?string
    {
        return DataDatabase::name();
    }

    public function thread(): BelongsTo
    {
        return $this->belongsTo(ChatThread::class, 'thread_id');
    }
}
