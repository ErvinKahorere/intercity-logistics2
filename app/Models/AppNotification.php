<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AppNotification extends Model
{
    protected $fillable = [
        'user_id',
        'parcel_request_id',
        'title',
        'message',
        'badge',
        'tone',
        'event_type',
        'is_read',
        'meta',
    ];

    protected $casts = [
        'is_read' => 'boolean',
        'meta' => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function parcelRequest()
    {
        return $this->belongsTo(ParcelRequest::class);
    }
}
