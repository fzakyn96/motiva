<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BusinessTrip extends Model
{
    protected $fillable = ['memo_id', 'activity_name', 'destination', 'start_date', 'end_date'];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
    ];

    public function memo()
    {
        return $this->belongsTo(Memo::class);
    }

    public function sppds()
    {
        return $this->hasMany(Sppd::class);
    }
}
