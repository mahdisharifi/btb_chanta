<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Feature extends Model
{
    protected $fillable = [
        'slug', 'name', 'description', 'base_price', 'base_duration_days', 'is_active'
    ];
}
