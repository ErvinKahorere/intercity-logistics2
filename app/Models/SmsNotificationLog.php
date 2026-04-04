<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SmsNotificationLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'parcel_request_id',
        'event_type',
        'template_key',
        'provider',
        'recipient_name',
        'recipient_phone',
        'normalized_phone',
        'message',
        'status',
        'provider_message_id',
        'provider_response',
        'meta',
        'error_message',
        'attempts',
        'queued_at',
        'sent_at',
        'failed_at',
    ];

    protected $casts = [
        'provider_response' => 'array',
        'meta' => 'array',
        'queued_at' => 'datetime',
        'sent_at' => 'datetime',
        'failed_at' => 'datetime',
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
