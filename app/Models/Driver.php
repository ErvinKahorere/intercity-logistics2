<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Driver extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'phone',
        'location',
        'status',
        'designation',
        'speciality',
        'about',
        'verification_status',
        'verification_submitted_at',
        'verification_rejection_reason',
        'verified_at',
        'verified_by',
    ];

    protected $casts = [
        'verification_submitted_at' => 'datetime',
        'verified_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function driverRoutes()
    {
        return $this->hasMany(DriverRoute::class, 'driver_id');
    }

    public function vehicle()
    {
        return $this->hasOne(DriverRoute::class);
    }

    public function savedContacts()
    {
        return $this->hasMany(SavedContact::class);
    }

    public function alerts()
    {
        return $this->hasMany(DriverAlert::class)->latest();
    }

    public function savedByUsers()
    {
        return $this->belongsToMany(User::class, 'saved_drivers')
            ->withTimestamps();
    }

    public function profileViews()
    {
        return $this->hasMany(ProfileView::class);
    }

    public function savedBy()
    {
        return $this->belongsToMany(User::class, 'saved_drivers', 'driver_id', 'user_id');
    }

    public function driver_routes()
    {
        return $this->hasMany(DriverRoute::class);
    }

    public function schedules()
    {
        return $this->hasMany(Schedule::class);
    }

    public function activeRoute()
    {
        return $this->driverRoutes()->where('available', true)->latest('updated_at');
    }

    public function licences()
    {
        return $this->hasMany(DriverLicence::class)->latest('expiry_date');
    }

    public function primaryLicence()
    {
        return $this->hasOne(DriverLicence::class)->where('is_primary', true)->latest('expiry_date');
    }

    public function bankAccount()
    {
        return $this->hasOne(DriverBankAccount::class);
    }

    public function verifier()
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function invoices()
    {
        return $this->hasMany(Invoice::class)->latest('issue_date');
    }
}
