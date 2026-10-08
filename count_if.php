<?php
$file = $argv[1] ?? 'storage/framework/views/9fb7a84d882b4528b3dba1e5ab60cf33.php';
$content = file_get_contents($file);
$lines = explode("\n", $content);
$ifStack = [];
$lineNum = 0;
foreach ($lines as $line) {
    $lineNum++;
    // Count <?php if ( ... ) : ?>
    preg_match_all('/<\?php\s+if\s*\(.*?\)\s*:\s*\?>/s', $line, $ifs);
    foreach ($ifs[0] as $if) {
        $ifStack[] = $lineNum;
    }
    // Count <?php endif; ?>
    preg_match_all('/<\?php\s+endif\s*;\s*\?>/s', $line, $endifs);
    for ($i = 0; $i < count($endifs[0]); $i++) {
        if (!empty($ifStack)) {
            array_pop($ifStack);
        } else {
            echo "Line $lineNum: Extra endif found!\n";
        }
    }
}
if (!empty($ifStack)) {
    echo "Unclosed if statements at lines: " . implode(', ', $ifStack) . "\n";
} else {
    echo "All if statements are balanced.\n";
}
echo "Total lines: $lineNum\n";
