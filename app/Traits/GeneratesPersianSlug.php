<?php

namespace App\Traits;

trait GeneratesPersianSlug
{
    protected function makePersianSlug(string $title): string
    {
        $slug = strtr(trim($title), [
            'ي' => 'ی', 'ك' => 'ک', 'ۀ' => 'ه', 'ة' => 'ه',
            'ؤ' => 'و', 'إ' => 'ا', 'أ' => 'ا', '‌' => ' ',
        ]);
        $slug = preg_replace('/\s+/u', '-', $slug);
        $slug = preg_replace('/[^آ-یءa-zA-Z0-9\-_]+/u', '-', $slug);
        $slug = preg_replace('/-+/u', '-', $slug);
        $slug = trim($slug, '-_');

        return $slug !== '' ? $slug : 'محصول';
    }
}
