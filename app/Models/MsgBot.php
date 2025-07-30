<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MsgBot extends Model
{
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'id',
        'chat_id',
        'meta_data',
    ];

    protected $casts = [
        'id' => 'string',
        'meta_data' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
}