<?php

namespace App\Support;

final class CategoryIcon
{
    public const NAMES = [
        'squares-2x2',
        'cube',
        'cube-transparent',
        'tag',
        'shopping-bag',
        'shopping-cart',
        'gift',
        'archive-box',
        'building-storefront',
        'building-office',
        'truck',
        'wrench',
        'wrench-screwdriver',
        'cog-6-tooth',
        'cpu-chip',
        'device-phone-mobile',
        'computer-desktop',
        'tv',
        'camera',
        'printer',
        'bolt',
        'light-bulb',
        'fire',
        'sparkles',
        'beaker',
        'paint-brush',
        'scissors',
        'home',
        'home-modern',
        'key',
        'lock-closed',
        'heart',
        'star',
        'sun',
        'moon',
        'cloud',
        'globe-alt',
        'map-pin',
        'book-open',
        'academic-cap',
        'briefcase',
        'banknotes',
        'credit-card',
        'chart-bar',
        'clipboard-document-list',
        'folder',
        'document-text',
        'musical-note',
        'film',
        'puzzle-piece',
        'rocket-launch',
        'trophy',
        'wallet',
        'phone',
        'envelope',
        'bell',
        'calendar',
        'clock',
        'user-group',
        'swatch',
        'rectangle-stack',
        'circle-stack',
        'wifi',
        'funnel',
        'cake',
        'photo',
        'newspaper',
        'megaphone',
        'shield-check',
        'ticket',
        'eye',
        'link',
        'server',
        'identification',
        'arrow-path',
        'bookmark',
        'calendar-days',
        'check-badge',
        'flag',
        'map',
        'presentation-chart-bar',
        'video-camera',
    ];

    public static function allowedValues(): array
    {
        return array_merge(self::NAMES, array_map(fn ($name) => 'heroicon-o-'.$name, self::NAMES), array_map(fn ($name) => 'heroicon-s-'.$name, self::NAMES), array_map(fn ($name) => 'heroicon-m-'.$name, self::NAMES));
    }

    public static function resolve(?string $icon): string
    {
        $name = preg_replace('/^heroicon-[osm]-/', '', trim($icon ?? ''));
        if (! in_array($name, self::NAMES, true)) {
            return 'heroicon-o-squares-2x2';
        }

        return 'heroicon-o-'.$name;
    }
}
