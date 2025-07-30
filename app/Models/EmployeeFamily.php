<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EmployeeFamily extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'employee_id',
        'name',
        'relationship',
        'date_of_birth',
        'occupation',
        'is_dependent',
    ];

    /**
     * Get the employee that owns the family member.
     */
    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }
}