<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EmployeeHistory extends Model
{
    use HasFactory;

    protected $table ='employee_history';
    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'employee_id',
        'change_type',
        'old_value',
        'new_value',
        'change_date',
        'notes',
        'changed_by_user_id',
    ];


    /**
     * Get the employee that the history belongs to.
     */
    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * Get the user who made the change.
     */
    public function changedBy()
    {
        return $this->belongsTo(User::class, 'changed_by_user_id');
    }
}