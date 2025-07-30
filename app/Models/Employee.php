<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Employee extends Model
{
    use HasFactory;

    protected $fillable = [
        'full_name',
        'place_of_birth',
        'date_of_birth',
        'address',
        'phone_number',         
        'personal_email',       
        'id_card_number',
        'id_card_image_path',
        'gender',
        'marital_status',
        'basic_salary',         
        'bank_id',
        'bank_account_number',  
        'bank_account_holder_name', 
        'emergency_contact_name',   
        'emergency_contact_relationship', 
        'emergency_contact_phone',  
        'position_id',
        'profile_picture_path',
        'employment_contract_path',
        'join_date',
        'termination_date',    
        'termination_reason',  
    ];

    /**
     * Get the position associated with the employee.
     */
    public function position()
    {
        return $this->belongsTo(Position::class);
    }

    /**
     * Get the user account associated with the employee.
     */
    public function user()
    {
        return $this->hasOne(User::class);
    }

    /**
     * Get the bank associated with the employee.
     */
    public function bank() // Tambahkan ini
    {
        return $this->belongsTo(Bank::class);
    }

    /**
     * Get the family members for the employee.
     */
    public function familyMembers() // Tambahkan ini
    {
        return $this->hasMany(EmployeeFamily::class);
    }

    /**
     * Get the history records for the employee.
     */
    public function history() // Tambahkan ini
    {
        return $this->hasMany(EmployeeHistory::class);
    }
}