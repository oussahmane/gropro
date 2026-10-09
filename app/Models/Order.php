<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    protected $fillable = [
        'order_number',
        'worker_id',
        'customer_name',
        'status',
        'notes',
    ];

    public function worker()
    {
        return $this->belongsTo(User::class, 'worker_id');
    }

    public function items()
    {
        return $this->hasMany(OrderItem::class);
    }
}
