<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MonthlySummary extends Model
{
    protected $fillable = [
        'year', 'month', 'total_revenue', 'total_sales_commission', 'total_subscriptions', 'notes'
    ];
}