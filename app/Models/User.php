<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable implements MustVerifyEmail
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'phone',
        'phone_e164',
        'location',
        'password',
        'role',
        'email_verified_at',
        'sms_notifications_enabled',
        'sms_notification_preferences',
    ];

    public function driver()
    {
        return $this->hasOne(Driver::class);
    }

    public function driverRoutes()
    {
        return $this->hasManyThrough(
            DriverRoute::class,
            Driver::class,
            'user_id',
            'driver_id',
            'id',
            'id'
        );
    }

    public function profile()
    {
        return $this->hasOne(UserProfile::class);
    }

    public function notificationsFeed()
    {
        return $this->hasMany(AppNotification::class)->latest();
    }

    public function quotations()
    {
        return $this->hasMany(Quotation::class)->latest('issue_date');
    }

    public function invoices()
    {
        return $this->hasMany(Invoice::class)->latest('issue_date');
    }

    public function smsNotifications()
    {
        return $this->hasMany(SmsNotificationLog::class)->latest();
    }

    public function getProfilePhotoUrlAttribute(): string
    {
        return $this->profile_photo_path
            ? asset('storage/' . $this->profile_photo_path)
            : asset('/images/Default_pfp.jpg');
    }

    protected $appends = ['profile_photo_url'];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'sms_notifications_enabled' => 'boolean',
        'sms_notification_preferences' => 'array',
    ];

    public function savedDrivers()
    {
        return $this->belongsToMany(Driver::class, 'saved_drivers', 'user_id', 'driver_id')
            ->withTimestamps();
    }

    public function hasRole(string ...$roles): bool
    {
        $userRole = strtolower((string) $this->role);

        foreach ($roles as $role) {
            if ($userRole === strtolower($role)) {
                return true;
            }
        }

        return false;
    }
}
