<?php

namespace App\Services;

use App\Models\CatalogTemplate;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CatalogCustomCoverService
{
    public function defaultForSupplier(int $supplierId): ?CatalogTemplate
    {
        return CatalogTemplate::where('supplier_id', $supplierId)
            ->where('settings->custom_cover_table->is_default_template', true)
            ->latest('updated_at')
            ->orderByDesc('id')
            ->first();
    }

    public function applyTheme(array $settings, int $supplierId, array $existingSettings = []): array
    {
        foreach (['custom_cover_table', 'custom_cover'] as $key) {
            if (isset($existingSettings[$key])) {
                $settings[$key] = $existingSettings[$key];
            }
        }

        if (($settings['cover_theme'] ?? 'default') !== 'custom_default') {
            return $settings;
        }

        // Keep the saved copy when only unrelated catalog settings change.
        if (($existingSettings['cover_theme'] ?? null) === 'custom_default'
            && isset($existingSettings['custom_cover_table'])) {
            return $settings;
        }

        $default = $this->defaultForSupplier($supplierId);
        if (!$default) {
            throw ValidationException::withMessages([
                'coverTheme' => 'ابتدا یک کاور سفارشی را به عنوان سفارشی پیش‌فرض ذخیره کنید.',
            ]);
        }

        // Each catalog owns its copy; later edits to the default must not overwrite it.
        $settings['custom_cover_table'] = $default->settings['custom_cover_table'];
        $settings['custom_cover_table']['source_catalog_id'] = null;
        $settings['custom_cover_table']['is_default_template'] =
            (bool) ($existingSettings['custom_cover_table']['is_default_template'] ?? false);

        return $settings;
    }

    public function save(CatalogTemplate $template, array $coverSettings, array $rows, bool $isDefault): void
    {
        DB::transaction(function () use ($template, $coverSettings, $rows, $isDefault) {
            // Serialize changes to this supplier's default, including concurrent promotions.
            DB::table('suppliers')->where('id', $template->supplier_id)->lockForUpdate()->first();

            if ($isDefault) {
                $others = CatalogTemplate::where('supplier_id', $template->supplier_id)
                    ->where('id', '!=', $template->id)
                    ->where('settings->custom_cover_table->is_default_template', true)
                    ->lockForUpdate()->get();

                foreach ($others as $other) {
                    $settings = $other->settings;
                    $settings['custom_cover_table']['is_default_template'] = false;
                    $other->update(['settings' => $settings]);
                }
            }

            $current = CatalogTemplate::where('supplier_id', $template->supplier_id)
                ->whereKey($template->id)->lockForUpdate()->firstOrFail();
            $settings = $current->settings ?? [];
            $settings['cover_theme'] = 'custom';
            $settings['custom_cover_table'] = [
                'source_catalog_id' => null,
                'is_default_template' => $isDefault,
                'settings' => $coverSettings,
                'rows' => $rows,
            ];
            $current->update(['settings' => $settings]);
            $template->setRawAttributes($current->getAttributes(), true);
        });
    }
}
