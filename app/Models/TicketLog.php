<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TicketLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'ticket_id',
        'user_id',
        'action',
        'description',
        'metadata',
    ];
    
    protected $casts = [
        'metadata' => 'array',
    ];

    // Relasi: TicketLog milik satu Ticket
    public function ticket()
    {
        return $this->belongsTo(Ticket::class);
    }

    // Relasi: TicketLog milik satu User
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}