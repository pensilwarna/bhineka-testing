<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Bank extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'code',
        'logo_path',
    ];

    /**
     * Get the employees associated with the bank.
     */
    public function employees()
    {
        return $this->hasMany(Employee::class);
    }
}