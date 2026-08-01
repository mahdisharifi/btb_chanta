<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Slider;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\File;

class SliderSeeder extends Seeder
{
    public function run(): void
    {
        // پاک‌سازی رکوردهای قبلی
        Slider::truncate();

        // ایجاد پوشه sliders اگر وجود ندارد
        $sliderPath = storage_path('app/public/sliders');
        if (!File::exists($sliderPath)) {
            File::makeDirectory($sliderPath, 0755, true);
        }

        // داده‌های اسلایدرها (UTF-8)
        $sliders = [
            [
                'title'            => 'به مارکت‌پلیس B2B خوش آمدید',
                'subtitle'         => 'بهترین محصولات با بهترین قیمت‌ها برای کسب‌وکار شما',
                'description'      => 'دسترسی به هزاران محصول از تأمین‌کنندگان معتبر با قیمت‌های رقابتی',
                'image_path'       => 'sliders/slider-1.jpg',
                'mobile_image_path'=> null,
                'link_url'         => '/products',
                'link_text'        => 'مشاهده محصولات',
                'link_target'      => '_self',
                'bg_color'         => '#1e40af',
                'text_color'       => '#ffffff',
                'overlay_color'    => 'rgba(0,0,0,0.4)',
                'text_position'    => 'right',
                'sort_order'       => 1,
                'is_active'        => true,
            ],
            [
                'title'            => 'تخفیف‌های ویژه فروشندگان',
                'subtitle'         => 'تا ۳۰ درصد تخفیف روی سفارشات عمده',
                'description'      => 'فرصت محدود! همین الان از تخفیف‌های ویژه بهره‌مند شوید',
                'image_path'       => 'sliders/slider-2.jpg',
                'mobile_image_path'=> null,
                'link_url'         => '/products?featured=1',
                'link_text'        => 'مشاهده تخفیف‌ها',
                'link_target'      => '_self',
                'bg_color'         => '#059669',
                'text_color'       => '#ffffff',
                'overlay_color'    => 'rgba(0,0,0,0.35)',
                'text_position'    => 'right',
                'sort_order'       => 2,
                'is_active'        => true,
            ],
            [
                'title'            => 'ثبت‌نام تأمین‌کنندگان',
                'subtitle'         => 'به شبکه فروش ما بپیوندید و فروش خود را افزایش دهید',
                'description'      => 'با پیوستن به مارکت‌پلیس ما، به هزاران خریدار دسترسی پیدا کنید',
                'image_path'       => 'sliders/slider-3.jpg',
                'mobile_image_path'=> null,
                'link_url'         => '/supplier/register',
                'link_text'        => 'همین الان ثبت‌نام کنید',
                'link_target'      => '_self',
                'bg_color'         => '#7c3aed',
                'text_color'       => '#ffffff',
                'overlay_color'    => 'rgba(0,0,0,0.4)',
                'text_position'    => 'center',
                'sort_order'       => 3,
                'is_active'        => true,
            ],
        ];

        // ساخت تصاویر placeholder و درج رکوردها
        foreach ($sliders as $index => $data) {
            // ساخت تصویر placeholder
            $this->createPlaceholderImage(
                $sliderPath . '/' . basename($data['image_path']),
                $data['bg_color'],
                $index + 1
            );

            // درج در دیتابیس
            Slider::create($data);
        }

        $this->command->info('✅ Sliders seeded with placeholder images!');
    }

    /**
     * ساخت تصویر placeholder با GD
     */
    private function createPlaceholderImage(string $path, string $bgColor, int $slideNumber): void
    {
        $width = 1920;
        $height = 600;

        // تبدیل Hex به RGB
        $hex = ltrim($bgColor, '#');
        $r = hexdec(substr($hex, 0, 2));
        $g = hexdec(substr($hex, 2, 2));
        $b = hexdec(substr($hex, 4, 2));

        if (extension_loaded('gd')) {
            // ساخت با GD
            $image = imagecreatetruecolor($width, $height);
            
            // رنگ پس‌زمینه
            $backgroundColor = imagecolorallocate($image, $r, $g, $b);
            imagefill($image, 0, 0, $backgroundColor);

            // اضافه کردن گرادیانت ساده
            for ($y = 0; $y < $height; $y++) {
                $alpha = (int)($y / $height * 50);
                $lineColor = imagecolorallocate($image, 
                    max(0, $r - $alpha), 
                    max(0, $g - $alpha), 
                    max(0, $b - $alpha)
                );
                imageline($image, 0, $y, $width, $y, $lineColor);
            }

            // رنگ متن سفید
            $textColor = imagecolorallocate($image, 255, 255, 255);

            // متن ساده (شماره اسلاید)
            $text = "Slider " . $slideNumber;
            $fontSize = 5; // بزرگترین فونت داخلی
            $textWidth = imagefontwidth($fontSize) * strlen($text);
            $textHeight = imagefontheight($fontSize);
            $x = ($width - $textWidth) / 2;
            $y = ($height - $textHeight) / 2;
            imagestring($image, $fontSize, (int)$x, (int)$y, $text, $textColor);

            // ذخیره با کیفیت بالا
            imagejpeg($image, $path, 90);
            imagedestroy($image);
        } else {
            // Fallback: ساخت JPEG ساده بدون GD
            $this->createMinimalJpeg($path, $r, $g, $b, $width, $height);
        }
    }

    /**
     * ساخت JPEG حداقلی بدون GD
     */
    private function createMinimalJpeg(string $path, int $r, int $g, int $b, int $w, int $h): void
    {
        // یک فایل JPEG معتبر با رنگ ثابت
        $header = "\xFF\xD8\xFF\xE0\x00\x10JFIF\x00\x01\x01\x00\x00\x01\x00\x01\x00\x00";
        
        // Quantization table
        $dqt = "\xFF\xDB\x00\x43\x00";
        for ($i = 0; $i < 64; $i++) {
            $dqt .= chr(16);
        }
        
        // Start of Frame
        $sof = "\xFF\xC0\x00\x0B\x08" . pack('n', $h) . pack('n', $w) . "\x01\x01\x11\x00";
        
        // Huffman tables (minimal)
        $dht = "\xFF\xC4\x00\x1F\x00\x00\x01\x05\x01\x01\x01\x01\x01\x01\x00\x00\x00\x00\x00\x00\x00\x00\x01\x02\x03\x04\x05\x06\x07\x08\x09\x0A\x0B";
        
        // Start of Scan
        $sos = "\xFF\xDA\x00\x08\x01\x01\x00\x00\x3F\x00";
        
        // Image data (solid color approximation)
        $y = (int)(0.299 * $r + 0.587 * $g + 0.114 * $b);
        $data = str_repeat(chr($y), 100);
        
        // End of Image
        $eoi = "\xFF\xD9";
        
        file_put_contents($path, $header . $dqt . $sof . $dht . $sos . $data . $eoi);
    }
}
