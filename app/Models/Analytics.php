<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Analytics extends Model
{
    use HasFactory;

    protected $fillable = [
        'ip_address',
        'url',
        'user_agent',
        'event_type',
        'user_id'
    ];

    // Define relationship with Comic
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
