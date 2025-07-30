<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Commission extends Model
{
    protected $fillable = [
        'sales_id',
        'commissionable_type',
        'commissionable_id',
        'amount',
        'status',
        'paid_at',
    ];

    protected $casts = [
        'paid_at' => 'datetime',
    ];

    // Definisikan status yang valid
    public const STATUS_PENDING = 'pending';
    public const STATUS_PAID = 'paid';
    public const STATUS_CANCELLED = 'cancelled';

    public function commissionable()
    {
        return $this->morphTo();
    }

    public function sales()
    {
        return $this->belongsTo(User::class, 'sales_id');
    }
}