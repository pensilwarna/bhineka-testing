<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Package extends Model
{
    protected $fillable = ['name', 'price', 'description', 'status'];

    public function serviceLocations()
    {
        return $this->hasMany(ServiceLocation::class);
    }

    public function installationRequests()
    {
        return $this->hasMany(InstallationRequest::class);
    }

    public function payments()
    {
        return $this->hasMany(Payment::class);
    }
}