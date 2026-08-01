<?php echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n"; ?>
<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
    @foreach ($sitemaps as $s)
    <sitemap>
        <loc>{{ $s['loc'] }}</loc>
        <lastmod>{{ $s['lastmod'] }}</lastmod>
    </sitemap>
    @endforeach
</sitemapindex>
