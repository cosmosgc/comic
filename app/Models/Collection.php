<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Collection extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
    ];

    public function comics()
    {
        return $this->belongsToMany(Comic::class)
                    ->withPivot('order') // Include the 'order' column in the pivot table
                    ->orderBy('pivot_order'); // Order comics by the 'order' field in the pivot table
    }
}

