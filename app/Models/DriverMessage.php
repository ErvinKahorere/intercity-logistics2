<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DriverMessage extends Model
{
    use HasFactory;

    protected $fillable = ['driver_id','admin_id','schedule_id','message'];

    public function Driver() { return $this->belongsTo(Driver::class);}
    public function schedule() { return $this->belongsTo(Schedule::class);}
    public function admin() { return $this->belongsTo(User::class, 'admin_id'); }
}
