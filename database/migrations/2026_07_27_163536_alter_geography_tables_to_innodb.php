<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('countries') || ! Schema::hasTable('provinces') || ! Schema::hasTable('cities')) {
            return;
        }

        // تبدیل موتور جداول به InnoDB
        DB::statement('ALTER TABLE `countries` ENGINE=InnoDB');
        DB::statement('ALTER TABLE `provinces` ENGINE=InnoDB');
        DB::statement('ALTER TABLE `cities` ENGINE=InnoDB');

        // افزودن FK استان -> کشور
        if (! $this->foreignKeyExists('provinces', 'provinces_country_id_foreign')) {
            DB::statement('
                ALTER TABLE `provinces`
                ADD CONSTRAINT `provinces_country_id_foreign`
                FOREIGN KEY (`country_id`) REFERENCES `countries`(`id`)
                ON DELETE CASCADE
                ON UPDATE CASCADE
            ');
        }

        // افزودن FK شهر -> استان
        if (! $this->foreignKeyExists('cities', 'cities_province_id_foreign')) {
            DB::statement('
                ALTER TABLE `cities`
                ADD CONSTRAINT `cities_province_id_foreign`
                FOREIGN KEY (`province_id`) REFERENCES `provinces`(`id`)
                ON DELETE CASCADE
                ON UPDATE CASCADE
            ');
        }

        // افزودن FK شهر -> کشور
        if (! $this->foreignKeyExists('cities', 'cities_country_id_foreign')) {
            DB::statement('
                ALTER TABLE `cities`
                ADD CONSTRAINT `cities_country_id_foreign`
                FOREIGN KEY (`country_id`) REFERENCES `countries`(`id`)
                ON DELETE CASCADE
                ON UPDATE CASCADE
            ');
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('countries') || ! Schema::hasTable('provinces') || ! Schema::hasTable('cities')) {
            return;
        }

        // حذف FKها با ترتیب درست
        if ($this->foreignKeyExists('cities', 'cities_country_id_foreign')) {
            DB::statement('ALTER TABLE `cities` DROP FOREIGN KEY `cities_country_id_foreign`');
        }

        if ($this->foreignKeyExists('cities', 'cities_province_id_foreign')) {
            DB::statement('ALTER TABLE `cities` DROP FOREIGN KEY `cities_province_id_foreign`');
        }

        if ($this->foreignKeyExists('provinces', 'provinces_country_id_foreign')) {
            DB::statement('ALTER TABLE `provinces` DROP FOREIGN KEY `provinces_country_id_foreign`');
        }

        // بازگردانی engine به ساختار قبلی
        DB::statement('ALTER TABLE `cities` ENGINE=MyISAM');
        DB::statement('ALTER TABLE `provinces` ENGINE=MyISAM');
        DB::statement('ALTER TABLE `countries` ENGINE=MyISAM');
    }

    private function foreignKeyExists(string $table, string $constraintName): bool
    {
        $database = DB::getDatabaseName();

        $result = DB::selectOne('
            SELECT COUNT(*) AS count
            FROM information_schema.TABLE_CONSTRAINTS
            WHERE CONSTRAINT_SCHEMA = ?
              AND TABLE_NAME = ?
              AND CONSTRAINT_NAME = ?
              AND CONSTRAINT_TYPE = "FOREIGN KEY"
        ', [$database, $table, $constraintName]);

        return (int) ($result->count ?? 0) > 0;
    }
};
