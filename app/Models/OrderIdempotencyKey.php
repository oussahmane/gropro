<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrderIdempotencyKey extends Model
{
    protected $fillable = [
        'order_id',
        'user_id',
        'endpoint',
        'key',
        'status_code',
        'response_body',
    ];

    protected function casts(): array
    {
        return [
            'response_body' => 'array',
            'status_code' => 'integer',
        ];
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
