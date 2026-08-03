<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Feature extends Model
{
    use \Illuminate\Database\Eloquent\Factories\HasFactory;
    protected $fillable = [
        'slug', 'name', 'description', 'base_price', 'base_duration_days', 'is_active'
    ];
}
