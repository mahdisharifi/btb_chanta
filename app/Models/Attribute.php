<?php
namespace App\Models;

use App\Enums\AttributeType;
use Illuminate\Database\Eloquent\Model;

class Attribute extends Model
{
    use \Illuminate\Database\Eloquent\Factories\HasFactory;
    // فیلد unit هم به fillable اضافه شده است
    protected $fillable = [
        'name',
        'type',
        'unit',
        'is_filterable',
         'is_multiple', // اضافه شد
        'is_visible',  // اضافه شد
    ];

    // این بخش ضروری است تا type به عنوان Enum شناخته شود
    protected $casts = [
        'type' => AttributeType::class,
        'is_filterable' => 'boolean',
        'is_multiple' => 'boolean', // اضافه شد
        'is_visible' => 'boolean',  // اضافه شد
    ];
    public function values()
    {
        return $this->hasMany(AttributeValue::class);
    }

    public function categories()
    {
        return $this->belongsToMany(Category::class)
            ->withPivot(['display_order', 'is_required'])
            ->orderByPivot('display_order')
            ->orderBy('categories.id');
    }

    public function products()
    {
        return $this->belongsToMany(Product::class)
            ->withPivot('value')
            ->withTimestamps();
    }
}
