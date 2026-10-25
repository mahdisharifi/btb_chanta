<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ProductCompletionStorageGuard
{
    public function assertTransactional(): void
    {
        $connection = DB::connection();
        if (! in_array($connection->getDriverName(), ['mysql', 'mariadb'], true)) {
            return;
        }

        $tables = array_map(fn (string $table): string => $connection->getTablePrefix().$table, [
            'products', 'product_completion_tasks', 'product_completion_submissions',
            'product_completion_audits', 'product_completion_commissions',
            'product_completion_media', 'product_completion_revisions', 'product_completion_operations',
        ]);
        $engines = $connection->query()->fromRaw('information_schema.TABLES')->useWritePdo()
            ->where('TABLE_SCHEMA', $connection->getDatabaseName())
            ->whereIn('TABLE_NAME', $tables)
            ->pluck('ENGINE', 'TABLE_NAME');

        foreach ($tables as $table) {
            if (strcasecmp((string) $engines->get($table), 'InnoDB') !== 0) {
                throw ValidationException::withMessages([
                    'storage' => 'برای ثبت امن عملیات، ابتدا مایگریشن تبدیل جدول‌های برون‌سپاری به InnoDB را اجرا کنید.',
                ]);
            }
        }
    }
}
