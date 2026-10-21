<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class Order extends Model
{
    protected $fillable = [
        'order_number',
        'worker_id',
        'cashier_id',
        'claimed_at',
        'customer_name',
        'status',
        'notes',
        'content_revision',
    ];

    protected function casts(): array
    {
        return [
            'claimed_at' => 'datetime',
            'content_revision' => 'integer',
        ];
    }

    public function worker()
    {
        return $this->belongsTo(User::class, 'worker_id');
    }

    public function cashier()
    {
        return $this->belongsTo(User::class, 'cashier_id');
    }

    public function items()
    {
        return $this->hasMany(OrderItem::class);
    }

    public function isTerminal(): bool
    {
        return in_array($this->status, ['completed', 'cancelled'], true);
    }

    public function isClaimedBy(?User $user): bool
    {
        return $user !== null
            && $this->cashier_id !== null
            && (int) $this->cashier_id === (int) $user->id;
    }

    public function isClaimedByAnother(?User $user): bool
    {
        return $this->cashier_id !== null
            && ($user === null || (int) $this->cashier_id !== (int) $user->id);
    }

    /**
     * Atomically claim this available non-terminal order for the given cashier.
     * Claims are independent per order: a cashier may hold many at once; this
     * never releases or blocks other orders claimed by the same cashier.
     * Returns true when this cashier owns the claim after the call.
     */
    public function claimFor(User $cashier): bool
    {
        return DB::transaction(function () use ($cashier) {
            $locked = static::query()->whereKey($this->id)->lockForUpdate()->firstOrFail();

            if ($locked->isTerminal()) {
                $this->refresh();

                return ! $locked->isClaimedByAnother($cashier);
            }

            if ($locked->cashier_id === null) {
                $locked->forceFill([
                    'cashier_id' => $cashier->id,
                    'claimed_at' => now(),
                ])->save();
                $this->refresh();

                return true;
            }

            $this->refresh();

            return $locked->isClaimedBy($cashier);
        });
    }

    public function releaseClaim(): void
    {
        DB::transaction(function () {
            $locked = static::query()->whereKey($this->id)->lockForUpdate()->firstOrFail();
            $locked->forceFill([
                'cashier_id' => null,
                'claimed_at' => null,
            ])->save();
            $this->refresh();
        });
    }

    public function reassignTo(User $cashier): void
    {
        DB::transaction(function () use ($cashier) {
            $locked = static::query()->whereKey($this->id)->lockForUpdate()->firstOrFail();
            $locked->forceFill([
                'cashier_id' => $cashier->id,
                'claimed_at' => now(),
            ])->save();
            $this->refresh();
        });
    }
}
