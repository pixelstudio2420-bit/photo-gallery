<?php
// Convert byte-based substr() → char-based mb_substr() for Thai-safe initials.
// Pattern: strtoupper(substr($X, 0, 1)) → mb_strtoupper(mb_substr($X, 0, 1, 'UTF-8'), 'UTF-8')
// Run with: php scripts/fix-substr-thai.php

$root = __DIR__ . '/../resources/views';
if (!is_dir($root)) {
    fwrite(STDERR, "ERROR: views dir not found: $root\n");
    exit(1);
}

$rii = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root));
$totalFixed = 0;
$filesFixed = 0;

foreach ($rii as $f) {
    if ($f->isDir() || !str_ends_with($f->getFilename(), '.blade.php')) continue;
    $path = $f->getPathname();
    $c = file_get_contents($path);

    $new = preg_replace(
        '/strtoupper\(substr\(([^,]+),\s*0,\s*1\)\)/',
        "mb_strtoupper(mb_substr($1, 0, 1, 'UTF-8'), 'UTF-8')",
        $c,
        -1,
        $count
    );
    if ($count > 0) {
        file_put_contents($path, $new);
        $totalFixed += $count;
        $filesFixed++;
        echo "  + " . str_replace('\\', '/', $path) . " ($count)\n";
    }
}
echo "Total: $totalFixed fixes in $filesFixed files\n";
