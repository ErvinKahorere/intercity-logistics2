<?php

// App\Models\UserProfile.php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserProfile extends Model
{
    protected $fillable = [
        'user_id', 'designation', 'speciality', 'phone', 'about', 'photo'
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
