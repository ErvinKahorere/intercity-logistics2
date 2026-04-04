<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ParcelStatusUpdate extends Model
{
    protected $fillable = [
        'parcel_request_id',
        'status',
        'actor_role',
        'title',
        'message',
    ];

    public function parcelRequest()
    {
        return $this->belongsTo(ParcelRequest::class);
    }
}
