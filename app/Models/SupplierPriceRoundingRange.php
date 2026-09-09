<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Validation\Rule;

class SupplierPriceRoundingRange extends Model
{
    protected $fillable = [
        'supplier_id',
        'min_price',
        'max_price',
        'rounding_method',
        'rounding_step',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'min_price' => 'decimal:2',
        'max_price' => 'decimal:2',
        'rounding_step' => 'integer',
        'sort_order' => 'integer',
        'is_active' => 'boolean',
    ];

    /**
     * Allowed rounding methods
     */
    public const ROUNDING_METHODS = [
        'ceil' => 'رُند به بالا',
        'floor' => 'رُند به پایین',
        'nearest' => 'نزدیک‌ترین',
    ];

    /**
     * Allowed rounding steps (100 to 50000)
     */
    public const ALLOWED_STEPS = [
        100, 200, 500,
        1000, 2000, 5000,
        10000, 20000, 50000
    ];

    /**
     * Relation: Range belongs to Supplier
     */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    /**
     * Scope: Only active ranges
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope: Order by min_price (natural order)
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('min_price', 'asc');
    }

    /**
     * Scope: For specific supplier
     */
    public function scopeForSupplier($query, int $supplierId)
    {
        return $query->where('supplier_id', $supplierId);
    }

    /**
     * Scope: Ranges that contain a specific price
     */
    public function scopeContainsPrice($query, float $price)
    {
        return $query->where('min_price', '<=', $price)
            ->where(function($q) use ($price) {
                $q->whereNull('max_price')
                  ->orWhere('max_price', '>', $price);
            });
    }

    /**
     * Get validation rules for creating/updating range
     */
    public static function validationRules(?int $rangeId = null, ?int $supplierId = null): array
    {
        return [
            'supplier_id' => ['required', 'exists:suppliers,id'],
            'min_price' => ['required', 'numeric', 'min:0', 'max:999999999999'],
            'max_price' => ['nullable', 'numeric', 'gt:min_price', 'max:999999999999'],
            'rounding_method' => ['required', Rule::in(array_keys(self::ROUNDING_METHODS))],
            'rounding_step' => ['required', Rule::in(self::ALLOWED_STEPS)],
            'sort_order' => ['integer', 'min:0', 'max:1000'],
            'is_active' => ['boolean'],
        ];
    }

    /**
     * Get validation messages in Persian
     */
    public static function validationMessages(): array
    {
        return [
            'supplier_id.required' => 'تأمین‌کننده الزامی است.',
            'supplier_id.exists' => 'تأمین‌کننده معتبر نیست.',
            'min_price.required' => 'حداقل قیمت الزامی است.',
            'min_price.numeric' => 'حداقل قیمت باید عدد باشد.',
            'min_price.min' => 'حداقل قیمت نمی‌تواند منفی باشد.',
            'max_price.numeric' => 'حداکثر قیمت باید عدد باشد.',
            'max_price.gt' => 'حداکثر قیمت باید بزرگ‌تر از حداقل قیمت باشد.',
            'rounding_method.required' => 'نوع رُند کردن الزامی است.',
            'rounding_method.in' => 'نوع رُند کردن معتبر نیست.',
            'rounding_step.required' => 'مبلغ مرجع الزامی است.',
            'rounding_step.in' => 'مبلغ مرجع معتبر نیست.',
            'sort_order.integer' => 'ترتیب نمایش باید عدد صحیح باشد.',
            'sort_order.min' => 'ترتیب نمایش نمی‌تواند منفی باشد.',
            'is_active.boolean' => 'وضعیت باید true یا false باشد.',
        ];
    }

    /**
     * Check if this range overlaps with another range for same supplier
     */
    public function hasOverlapWith(?int $excludeRangeId = null): bool
    {
        $query = static::where('supplier_id', $this->supplier_id)
            ->where('id', '!=', $this->id ?? 0);

        if ($excludeRangeId) {
            $query->where('id', '!=', $excludeRangeId);
        }

        // Check for overlap
        $overlapping = $query->where(function($q) {
            // Case 1: New range min_price falls within existing range
            $q->where(function($subQ) {
                $subQ->where('min_price', '<=', $this->min_price)
                     ->where(function($maxQ) {
                         $maxQ->whereNull('max_price')
                              ->orWhere('max_price', '>', $this->min_price);
                     });
            })
            // Case 2: New range max_price falls within existing range (if not null)
            ->orWhere(function($subQ) {
                if ($this->max_price !== null) {
                    $subQ->where('min_price', '<', $this->max_price)
                         ->where(function($maxQ) {
                             $maxQ->whereNull('max_price')
                                  ->orWhere('max_price', '>', $this->min_price);
                         });
                }
            })
            // Case 3: New range completely contains existing range
            ->orWhere(function($subQ) {
                $subQ->where('min_price', '>=', $this->min_price);
                if ($this->max_price !== null) {
                    $subQ->where(function($maxQ) {
                        $maxQ->whereNotNull('max_price')
                             ->where('max_price', '<=', $this->max_price);
                    });
                }
            });
        })->exists();

        return $overlapping;
    }

    /**
     * بررسی اینکه آیا رنج‌های این تأمین‌کننده تمام قیمت‌ها را پوشش می‌دهند
     * 
     * @param int $supplierId
     * @param int|null $excludeRangeId
     * @return array ['valid' => bool, 'message' => string, 'gaps' => array]
     */
    public static function validateCompleteCoverage(int $supplierId, ?int $excludeRangeId = null): array
    {
        $query = static::forSupplier($supplierId)->ordered();
        
        if ($excludeRangeId) {
            $query->where('id', '!=', $excludeRangeId);
        }
        
        $ranges = $query->get();
        
        // حداقل یک رنج باید وجود داشته باشد
        if ($ranges->isEmpty()) {
            return [
                'valid' => false,
                'message' => 'حداقل یک رنج قیمتی باید تعریف شود.',
                'gaps' => []
            ];
        }
        
        $gaps = [];
        $expectedStart = 0;
        
        foreach ($ranges as $range) {
            // بررسی شکاف: min_price باید برابر یا 1 بزرگتر از expectedStart باشد
            if ($range->min_price > $expectedStart) {
                $gaps[] = [
                    'from' => $expectedStart,
                    'to' => $range->min_price - 1,
                ];
            }
            
            // اگر max_price = null باشد، یعنی تا بی‌نهایت
            if ($range->max_price === null) {
                // این آخرین رنج است و تا بی‌نهایت ادامه دارد
                if (count($gaps) === 0 && $range->min_price == 0) {
                    return ['valid' => true, 'message' => 'پوشش کامل', 'gaps' => []];
                }
                // اگر شکافی وجود ندارد، بررسی می‌کنیم
                if (count($gaps) === 0) {
                    return ['valid' => true, 'message' => 'پوشش کامل', 'gaps' => []];
                }
                break;
            }
            
            // رنج بعدی باید از max_price + 1 شروع شود
            $expectedStart = $range->max_price + 1;
        }
        
        // بررسی آخرین رنج
        $lastRange = $ranges->last();
        if ($lastRange && $lastRange->max_price !== null) {
            return [
                'valid' => false,
                'message' => 'آخرین رنج باید تا بی‌نهایت ادامه داشته باشد (حداکثر قیمت = خالی).',
                'gaps' => []
            ];
        }
        
        if (count($gaps) > 0) {
            return [
                'valid' => false,
                'message' => 'شکاف در رنج‌های قیمتی وجود دارد.',
                'gaps' => $gaps
            ];
        }
        
        return ['valid' => true, 'message' => 'پوشش کامل', 'gaps' => []];
    }

    /**
     * بررسی اینکه آیا حذف این رنج باعث شکاف می‌شود یا خیر
     */
    public function canBeDeleted(): array
    {
        $coverage = self::validateCompleteCoverage($this->supplier_id, $this->id);
        
        if (!$coverage['valid']) {
            return [
                'can_delete' => false,
                'message' => 'حذف این رنج باعث ایجاد شکاف در پوشش قیمت‌ها می‌شود. ' . $coverage['message']
            ];
        }
        
        return ['can_delete' => true, 'message' => ''];
    }

    /**
     * Get human-readable label for price range
     */
    public function getRangeLabelAttribute(): string
    {
        $min = number_format($this->min_price, 0);
        
        if ($this->max_price === null) {
            return "از {$min} تا بی‌نهایت";
        }
        
        $max = number_format($this->max_price, 0);
        return "از {$min} تا {$max} تومان";
    }

    /**
     * Get human-readable method name
     */
    public function getMethodLabelAttribute(): string
    {
        return self::ROUNDING_METHODS[$this->rounding_method] ?? $this->rounding_method;
    }

    /**
     * Get formatted step with thousand separators
     */
    public function getStepLabelAttribute(): string
    {
        return number_format($this->rounding_step, 0);
    }

    /**
     * Check if a price falls within this range
     */
    public function containsPrice(float $price): bool
    {
        if ($price < $this->min_price) {
            return false;
        }

        if ($this->max_price !== null && $price >= $this->max_price) {
            return false;
        }

        return true;
    }

    /**
     * Generate example rounded prices for preview
     */
    public function generateExamples(): array
    {
        $examples = [];
        
        // Calculate 3 example prices within this range
        $basePrice = (float) $this->min_price;
        $step = $this->rounding_step;
        
        // Example 1: Near min_price
        $price1 = $basePrice + ($step * 0.3);
        
        // Example 2: Middle of range
        if ($this->max_price !== null) {
            $price2 = ($basePrice + $this->max_price) / 2;
        } else {
            $price2 = $basePrice + ($step * 5);
        }
        
        // Example 3: Near max_price (or further up if no max)
        if ($this->max_price !== null) {
            $price3 = $this->max_price - ($step * 0.7);
        } else {
            $price3 = $basePrice + ($step * 10);
        }

        foreach ([$price1, $price2, $price3] as $price) {
            $rounded = $this->roundPrice($price);
            $examples[] = [
                'original' => $price,
                'rounded' => $rounded,
                'original_formatted' => number_format($price, 0),
                'rounded_formatted' => number_format($rounded, 0),
            ];
        }

        return $examples;
    }

    /**
     * Round a price according to this range's settings
     */
    public function roundPrice(float $price): float
    {
        $step = $this->rounding_step;

        return match($this->rounding_method) {
            'ceil' => ceil($price / $step) * $step,
            'floor' => floor($price / $step) * $step,
            'nearest' => round($price / $step) * $step,
            default => $price,
        };
    }
}
