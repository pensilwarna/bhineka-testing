<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Company extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'tax_id', 'address', 'phone', 'email', 'meta_data'];

    // Relasi: Company memiliki banyak Customer
    public function customers()
    {
        return $this->hasMany(Customer::class);
    }

    public function serviceLocations()
    {
        return $this->hasMany(ServiceLocation::class);
    }
}