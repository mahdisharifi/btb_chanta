<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SupplierProfile extends Model
{
    use HasFactory;

    protected $fillable = [
        'supplier_id',
        'company_name',
        'registration_number',
        'national_id',
        'phone',
        'address',
        'website',
        // سایر فیلدهایی که در جدول supplier_profiles دارید را اینجا اضافه کنید
    ];

    /**
     * ارتباط با مدل تامین‌کننده (یک به یک یا یک به چند بستگی به ساختار شما دارد)
     */
    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }
}
