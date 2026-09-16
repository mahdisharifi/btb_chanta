<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ProductGallery extends Model
{
    use HasFactory, SoftDeletes;

    // ──────────────────────────────────────────────────────────
    //  CONSTANTS
    // ──────────────────────────────────────────────────────────

    /** فرمت‌های مجاز تصویر */
    public const ALLOWED_IMAGE_EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp', 'gif', 'svg'];

    /** فرمت‌های مجاز ویدیو */
    public const ALLOWED_VIDEO_EXTENSIONS = ['mp4', 'webm', 'ogg', 'mov'];

    /** حداکثر حجم تصویر (بایت) — 2MB */
    public const MAX_IMAGE_SIZE = 2 * 1024 * 1024;

    /** حداکثر حجم ویدیو (بایت) — 50MB */
    public const MAX_VIDEO_SIZE = 50 * 1024 * 1024;

    /** حداکثر تعداد تصاویر هر محصول */
    public const MAX_IMAGES_PER_PRODUCT = 10;

    /** حداکثر تعداد ویدیو هر محصول */
    public const MAX_VIDEOS_PER_PRODUCT = 1;

    /** اندازه‌های thumbnail */
    public const THUMBNAIL_SIZES = [
        'xs'  => ['width' => 80,  'height' => 80],
        'sm'  => ['width' => 150, 'height' => 150],
        'md'  => ['width' => 300, 'height' => 300],
        'lg'  => ['width' => 600, 'height' => 600],
        'xl'  => ['width' => 1200, 'height' => 1200],
    ];

    /** سایزهای نسخه جدید (WebP + JPG) ✅ */
    public const VERSION_SIZES = [
        'thumbnail' => ['width' => 150, 'height' => 150, 'format' => 'webp'],
        'small'     => ['width' => 300, 'height' => 300, 'format' => 'webp'],
        'medium'    => ['width' => 600, 'height' => 600, 'format' => 'webp'],
        'large'     => ['width' => 1200, 'height' => 1200, 'format' => 'webp'],
        'full'      => ['width' => null, 'height' => null, 'format' => 'webp'],
        'jpg'       => ['width' => 300, 'height' => 300, 'format' => 'jpg'],
    ];

    /** دیسک ذخیره‌سازی */
    public const STORAGE_DISK = 'public';

    // ──────────────────────────────────────────────────────────
    //  FILLABLE
    // ──────────────────────────────────────────────────────────

    protected $fillable = [
        'product_id',
        'file_path',
        'file_name',
        'base_name',        // ✅ نام پایه برای نسخه‌ها
        'file_type',
        'poster_path',      // ✅ اضافه شد - thumbnail ویدئو
        'title',
        'alt_text',
        'sort_order',
        'is_primary',
        'file_size',
        'mime_type',
        'width',
        'height',
        'versions',         // ✅ JSON نسخه‌ها
        'duration',
    ];

    // ──────────────────────────────────────────────────────────
    //  CASTS ✅ اصلاح شده
    // ──────────────────────────────────────────────────────────

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'is_primary' => 'boolean',
            'file_size'  => 'integer',
            'width'      => 'integer',
            'height'     => 'integer',
            'duration'   => 'integer',
            'versions'   => 'array',  // ✅ اضافه شد
        ];
    }

    // ──────────────────────────────────────────────────────────
    //  APPENDS ✅ اصلاح شده
    // ──────────────────────────────────────────────────────────

    protected $appends = [
        'full_url',
        'thumbnail_url',
        'is_image',
        'is_video',
        'version_urls',     // ✅ اضافه شد - دسترسی به URL نسخه‌ها
    ];

    // ──────────────────────────────────────────────────────────
    //  BOOT
    // ──────────────────────────────────────────────────────────

    protected static function boot(): void
    {
        parent::boot();

        static::forceDeleting(function (self $gallery) {
            $gallery->deletePhysicalFile();
        });

        static::creating(function (self $gallery) {
            if (is_null($gallery->sort_order)) {
                $gallery->sort_order = static::getNextSortOrder($gallery->product_id);
            }
        });
    }

    // ══════════════════════════════════════════════════════════
    //  RELATIONSHIPS
    // ══════════════════════════════════════════════════════════

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    // ══════════════════════════════════════════════════════════
    //  ACCESSORS ✅ اضافه شده
    // ══════════════════════════════════════════════════════════

    /**
     * URL تمام نسخه‌ها ✅
     */
    public function getVersionUrl(string $size): ?string
    {
        $versions = $this->versions;

        if (is_string($versions)) {
            $decoded = json_decode($versions, true);

            if (is_string($decoded)) {
                $decoded = json_decode($decoded, true);
            }

            $versions = is_array($decoded) ? $decoded : [];
        }

        $aliases = [
            'xs' => ['xs', 'thumbnail'],
            'sm' => ['sm', 'small'],
            'md' => ['md', 'medium'],
            'lg' => ['lg', 'large'],
            'xl' => ['xl', 'full'],
            'thumbnail' => ['thumbnail', 'xs'],
            'small' => ['small', 'sm'],
            'medium' => ['medium', 'md'],
            'large' => ['large', 'lg'],
            'full' => ['full', 'xl'],
        ];

        $keys = $aliases[$size] ?? [$size];

        foreach ($keys as $key) {
            if (!empty($versions[$key])) {
                $version = $versions[$key];

                $path = is_array($version)
                    ? ($version['path'] ?? null)
                    : $version;

                if ($path) {
                    // ✅ بررسی وجود فایل فیزیکی
                    if (Storage::disk(self::STORAGE_DISK)->exists($path)) {
                        return asset('storage/' . $path);
                    }
                }
            }
        }

        return null;
    }

    /**
     * URL کامل فایل اصلی
     */
    public function getFullUrlAttribute(): string
    {
        if (empty($this->file_path)) {
            return $this->getDefaultImageUrl();
        }

        if (Str::startsWith($this->file_path, ['http://', 'https://'])) {
            return $this->file_path;
        }

        return asset('storage/' . $this->file_path);
    }

    /**
     * URL تصویر thumbnail (برگرداندن نسخه sm یا کوچکترین نسخه موجود)
     */
    public function getThumbnailUrlAttribute(): string
    {
        if (!empty($this->versions['thumbnail'])) {
            $version = $this->versions['thumbnail'];

            $path = is_array($version)
                ? ($version['path'] ?? null)
                : $version;

            if ($path) {
                return asset('storage/' . $path);
            }
        }

        if (!empty($this->versions['small'])) {
            $version = $this->versions['small'];

            $path = is_array($version)
                ? ($version['path'] ?? null)
                : $version;

            if ($path) {
                return asset('storage/' . $path);
            }
        }

        if (!empty($this->versions['sm'])) {
            $version = $this->versions['sm'];

            $path = is_array($version)
                ? ($version['path'] ?? null)
                : $version;

            if ($path) {
                return asset('storage/' . $path);
            }
        }

        return $this->full_url;
    }

    /**
     * آیا فایل تصویر است؟
     */
    public function getIsImageAttribute(): bool
    {
        return $this->file_type === 'image';
    }

    /**
     * آیا فایل ویدیو است؟
     */
    public function getIsVideoAttribute(): bool
    {
        return $this->file_type === 'video';
    }

    // ══════════════════════════════════════════════════════════
    //  PUBLIC METHODS ✅ اضافه شده
    // ══════════════════════════════════════════════════════════


    /**
     * دریافت مسیر یک نسخه خاص (برای PDF یا پردازش) ✅
     */
    public function getVersionPath(string $size): ?string
    {
        if (!empty($this->versions[$size])) {
            $version = $this->versions[$size];

            return is_array($version)
                ? ($version['path'] ?? null)
                : $version;
        }

        return null;
    }

    // ══════════════════════════════════════════════════════════
    //  EXISTING METHODS (با اصلاحات)
    // ══════════════════════════════════════════════════════════

    /**
     * حذف فیزیکی فایل و تمام نسخه‌ها ✅ اصلاح شده
     */
    protected function deletePhysicalFile(): void
    {
        $paths = [];

        if ($this->file_path) {
            $paths[] = $this->file_path;
        }

        if (!empty($this->versions) && is_array($this->versions)) {
            foreach ($this->versions as $version) {
                $path = is_array($version)
                    ? ($version['path'] ?? null)
                    : $version;

                if ($path) {
                    $paths[] = $path;
                }
            }
        }

        foreach ($paths as $path) {
            if (Storage::disk(self::STORAGE_DISK)->exists($path)) {
                Storage::disk(self::STORAGE_DISK)->delete($path);
            }
        }
    }

    // ══════════════════════════════════════════════════════════
    //  PRIVATE HELPERS
    // ══════════════════════════════════════════════════════════

    /**
     * مسیر thumbnail قدیمی (legacy)
     */
    private function getLegacyThumbnailPath(string $size): ?string
    {
        if (empty($this->file_path)) {
            return null;
        }

        $directory = pathinfo($this->file_path, PATHINFO_DIRNAME);
        $filename  = pathinfo($this->file_path, PATHINFO_FILENAME);
        $extension = pathinfo($this->file_path, PATHINFO_EXTENSION);

        return "{$directory}/thumbnails/{$filename}_{$size}.{$extension}";
    }

    /**
     * مسیر WebP قدیمی (legacy)
     */
    private function getLegacyWebpPath(): ?string
    {
        if (empty($this->file_path)) {
            return null;
        }

        $directory = pathinfo($this->file_path, PATHINFO_DIRNAME);
        $filename  = pathinfo($this->file_path, PATHINFO_FILENAME);

        return "{$directory}/webp/{$filename}.webp";
    }

    /**
     * URL پیش‌فرض
     */
    private function getDefaultImageUrl(): string
    {
        return asset('images/placeholders/product-default.png');
    }

    /**
     * URL thumbnail ویدیو
     * 
     * اولویت:
     * 1. poster_path (آپلود دستی کاربر)
     * 2. auto-generated poster در posters/{filename}.jpg
     * 3. fallback placeholder
     */
    public function getVideoThumbnailUrl(): string
    {
        // 1️⃣ اولویت اول: poster_path (آپلود دستی)
        if (!empty($this->poster_path)) {
            if (Str::startsWith($this->poster_path, ['http://', 'https://'])) {
                return $this->poster_path;
            }
            
            if (Storage::disk(self::STORAGE_DISK)->exists($this->poster_path)) {
                return asset('storage/' . $this->poster_path);
            }
        }

        // 2️⃣ اولویت دوم: auto-generated poster
        if (!empty($this->file_path)) {
            $directory = pathinfo($this->file_path, PATHINFO_DIRNAME);
            $filename  = pathinfo($this->file_path, PATHINFO_FILENAME);
            $posterPath = "{$directory}/posters/{$filename}.jpg";

            if (Storage::disk(self::STORAGE_DISK)->exists($posterPath)) {
                return asset('storage/' . $posterPath);
            }
        }

        // 3️⃣ fallback: placeholder
        return asset('images/placeholders/video-default.svg');
    }

    /**
     * محاسبه sort_order بعدی
     */
    public static function getNextSortOrder(int $productId): int
    {
        $maxOrder = static::where('product_id', $productId)->max('sort_order');
        return ($maxOrder ?? 0) + 1;
    }


    /**
     * تولید تگ HTML ویدیو
     */
    public function getVideoHtml(array $attributes = []): string
    {
        if (!$this->is_video) {
            return '';
        }

        $url = $this->full_url;
        $mime = $this->effective_mime_type;
        $poster = $this->thumbnail_url;

        $attrString = '';
        $defaults = [
            'controls'  => true,
            'preload'   => 'metadata',
            'playsinline' => true,
        ];

        $merged = array_merge($defaults, $attributes);

        foreach ($merged as $key => $value) {
            if ($value === true) {
                $attrString .= " {$key}";
            } elseif ($value !== false) {
                $attrString .= " {$key}=\"" . e($value) . "\"";
            }
        }

        return <<<HTML
<video poster="{$poster}"{$attrString}>
    <source src="{$url}" type="{$mime}">
    مرورگر شما از ویدیو پشتیبانی نمی‌کند.
</video>
HTML;
    }

    public function variantUrl(string $size = null): string
    {
        if ($size === null) {
            return asset('storage/' . $this->file_path);
        }
        $dir = dirname($this->file_path);
        return asset("storage/{$dir}/{$size}/" . basename($this->file_path));
    }

    public function pdfJpgPath(): string
    {
        $dir  = dirname($this->file_path);
        $name = pathinfo($this->file_path, PATHINFO_FILENAME);
        return storage_path("app/public/{$dir}/pdf/{$name}.jpg");
    }



}
