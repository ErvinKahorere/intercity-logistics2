<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DriverAlert extends Model
{
    protected $fillable = [
        'driver_id',
        'parcel_request_id',
        'title',
        'message',
        'severity',
        'is_read',
        'meta',
    ];

    protected $casts = [
        'is_read' => 'boolean',
        'meta' => 'array',
    ];

    public function driver()
    {
        return $this->belongsTo(Driver::class);
    }

    public function parcelRequest()
    {
        return $this->belongsTo(ParcelRequest::class);
    }
}
