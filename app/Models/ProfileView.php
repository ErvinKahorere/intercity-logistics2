<?php

// app/Models/ProfileView.php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProfileView extends Model
{
    protected $fillable = ['driver_id', 'user_id'];

    public function driver() {
        return $this->belongsTo(Driver::class);
    }

    public function user() {
        return $this->belongsTo(User::class);
    }
}
