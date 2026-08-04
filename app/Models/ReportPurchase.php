<?php

namespace App\Models;

use App\Support\DataDatabase;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReportPurchase extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_PAID = 'paid';

    public const STATUS_FAILED = 'failed';

    public const STATUS_EXPIRED = 'expired';

    public const TYPE_PROPERTY = 'property';

    protected $table = 'report_purchases';

    protected $guarded = [];

    public function getConnectionName(): ?string
    {
        return DataDatabase::name();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'lat' => 'float',
            'lng' => 'float',
            'amount_cents' => 'integer',
            'metadata' => 'array',
            'paid_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function isPaid(): bool
    {
        return $this->status === self::STATUS_PAID;
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function markPaid(): void
    {
        $this->forceFill([
            'status' => self::STATUS_PAID,
            'paid_at' => $this->paid_at ?? now(),
        ])->save();
    }
}
