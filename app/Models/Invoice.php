<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Invoice extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'driver_id',
        'parcel_request_id',
        'quotation_id',
        'invoice_number',
        'status',
        'payment_status',
        'booking_reference',
        'tracking_number',
        'issue_date',
        'due_date',
        'base_fee',
        'distance_fee',
        'weight_fee',
        'urgency_fee',
        'special_handling_fee',
        'subtotal',
        'total',
        'pricing_breakdown',
        'customer_snapshot',
        'driver_snapshot',
        'route_snapshot',
        'notes',
        'paid_at',
        'cancelled_at',
    ];

    protected $casts = [
        'issue_date' => 'date',
        'due_date' => 'date',
        'paid_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'pricing_breakdown' => 'array',
        'customer_snapshot' => 'array',
        'driver_snapshot' => 'array',
        'route_snapshot' => 'array',
        'base_fee' => 'decimal:2',
        'distance_fee' => 'decimal:2',
        'weight_fee' => 'decimal:2',
        'urgency_fee' => 'decimal:2',
        'special_handling_fee' => 'decimal:2',
        'subtotal' => 'decimal:2',
        'total' => 'decimal:2',
    ];

    public function customer()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function driver()
    {
        return $this->belongsTo(Driver::class);
    }

    public function parcelRequest()
    {
        return $this->belongsTo(ParcelRequest::class);
    }

    public function quotation()
    {
        return $this->belongsTo(Quotation::class);
    }
}
