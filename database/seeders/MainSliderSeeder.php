<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class MainSliderSeeder extends Seeder
{
    /**
     * اسلایدر اصلی سایت - بهینه شده با توصیه کارشناسان
     * 
     * - برندینگ: رنگ‌های حرفه‌ای و مناسب B2B
     * - گرافیک: گرادینت‌های جذاب استاندارد
     * - UI/UX: چیدمان بهینه متن
     * - SEO: کلمات کلیدی مناسب
     * - کپی‌رایتینگ: متن‌های کوتاه و تأثیرگذار
     */
    public function run(): void
    {
        // ✅ اگر قبلاً اسلایدها ایجاد شده‌اند، خارج شو
        if (DB::table('sliders')->count() > 0) {
            $this->command->info('⚠️ اسلایدها قبلاً ایجاد شده‌اند. رد شدن...');
            $this->command->info('💡 برای ایجاد مجدد، ابتدا جدول sliders را خالی کنید.');
            return;
        }

        $sliders = [
            // ═══════════════════════════════════════════════════════════
            // اسلاید ۱: خرید عمده آنلاین با قیمت تولیدکننده
            // ═══════════════════════════════════════════════════════════
            [
                'title'          => 'خرید عمده آنلاین با قیمت تولیدکننده',
                'subtitle'       => 'بازار B2B ایران — ارتباط مستقیم خرده‌فروشان با تأمین‌کنندگان و واردکنندگان اصلی',
                'description'    => null,
                'image_path'     => '',
                'mobile_image_path' => '',
                'link_url'       => '/products',
                'link_text'      => 'شروع خرید عمده',
                'link_target'    => '_self',
                'bg_color'       => 'transparent',
                'bg_gradient'    => 'background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);',
                'text_color'     => '#ffffff',
                'overlay_color'  => 'transparent',
                'text_position'  => 'center',
                'sort_order'     => 1,
                'is_active'      => true,
                'starts_at'      => null,
                'ends_at'        => null,
                'created_at'     => now(),
                'updated_at'     => now(),
            ],

            // ═══════════════════════════════════════════════════════════
            // اسلاید ۲: قیمت‌گذاری شفاف بدون واسطه
            // ═══════════════════════════════════════════════════════════
            [
                'title'          => 'قیمت‌گذاری شفاف بدون واسطه',
                'subtitle'       => 'دسترسی مستقیم به قیمت عمده کالا از تولیدکنندگان — بدون کمیسیون و کارمزد پنهان',
                'description'    => null,
                'image_path'     => '',
                'mobile_image_path' => '',
                'link_url'       => '/products',
                'link_text'      => 'مشاهده قیمت‌ها',
                'link_target'    => '_self',
                'bg_color'       => 'transparent',
                'bg_gradient'    => 'background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);',
                'text_color'     => '#ffffff',
                'overlay_color'  => 'transparent',
                'text_position'  => 'right',
                'sort_order'     => 2,
                'is_active'      => true,
                'starts_at'      => null,
                'ends_at'        => null,
                'created_at'     => now(),
                'updated_at'     => now(),
            ],

            // ═══════════════════════════════════════════════════════════
            // اسلاید ۳: همکاری با تأمین‌کنندگان معتبر
            // ═══════════════════════════════════════════════════════════
            [
                'title'          => 'همکاری با تأمین‌کنندگان و واردکنندگان معتبر',
                'subtitle'       => 'دسترسی به شبکه گسترده عمده‌فروشان، تولیدکنندگان و واردکنندگان اصلی در سراسر ایران',
                'description'    => null,
                'image_path'     => '',
                'mobile_image_path' => '',
                'link_url'       => '/suppliers',
                'link_text'      => 'مشاهده تأمین‌کنندگان',
                'link_target'    => '_self',
                'bg_color'       => 'transparent',
                'bg_gradient'    => 'background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);',
                'text_color'     => '#ffffff',
                'overlay_color'  => 'transparent',
                'text_position'  => 'left',
                'sort_order'     => 3,
                'is_active'      => true,
                'starts_at'      => null,
                'ends_at'        => null,
                'created_at'     => now(),
                'updated_at'     => now(),
            ],

            // ═══════════════════════════════════════════════════════════
            // اسلاید ۴: فروش عمده در تمام استان‌های ایران
            // ═══════════════════════════════════════════════════════════
            [
                'title'          => 'فروش عمده در تمام استان‌های ایران',
                'subtitle'       => 'سامانه عمده‌فروشی آنلاین با امکان سفارش و تحویل در سراسر کشور',
                'description'    => null,
                'image_path'     => '',
                'mobile_image_path' => '',
                'link_url'       => '/products',
                'link_text'      => 'ثبت سفارش عمده',
                'link_target'    => '_self',
                'bg_color'       => 'transparent',
                'bg_gradient'    => 'background: linear-gradient(135deg, #fa709a 0%, #fee140 100%);',
                'text_color'     => '#1f2937', // تیره برای تضاد با زرد
                'overlay_color'  => 'transparent',
                'text_position'  => 'center',
                'sort_order'     => 4,
                'is_active'      => true,
                'starts_at'      => null,
                'ends_at'        => null,
                'created_at'     => now(),
                'updated_at'     => now(),
            ],
        ];

        DB::table('sliders')->insert($sliders);

        $this->command->info('✅ اسلایدرهای اصلی با موفقیت اضافه شدند!');
        $this->command->info('📊 تعداد: ' . count($sliders) . ' اسلاید');
        $this->command->newLine();
        $this->command->info('🎨 ویژگی‌ها:');
        $this->command->info('  - گرادینت‌های حرفه‌ای و جذاب');
        $this->command->info('  - محتوای بهینه‌شده برای SEO');
        $this->command->info('  - متن‌های واضح و تأثیرگذار');
        $this->command->info('  - رنگ‌های سازگار با برند B2B');
    }
}
