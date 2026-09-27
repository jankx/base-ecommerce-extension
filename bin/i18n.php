<?php
/**
 * Regenerate every i18n artifact for the base-ecommerce extension.
 *
 *   .pot  -> extract PHP, block.json and built JS strings
 *   .po   -> fill with the Vietnamese strings from bin/translations.json
 *   .mo   -> compiled PHP translations
 *   .json -> Jed translations for the block editor scripts
 *
 * Usage: php bin/i18n.php
 *
 * Requires: wp-cli (`wp i18n`), gettext (`msguniq`, `msgfmt`).
 *
 * @package base-ecommerce
 */
$ext    = dirname(__DIR__);
$lang   = $ext . '/languages';
$domain = 'base-ecommerce';
$slug   = 'base-ecommerce';
$po     = $lang . '/base-ecommerce-vi.po';
$mo     = $lang . '/base-ecommerce-vi.mo';
$pot    = $lang . '/base-ecommerce.pot';
$uniq   = $lang . '/_uniq.pot';
$map    = __DIR__ . '/translations.json';

$steps = [
    'extract (php + block.json)' => sprintf(
        'wp i18n make-pot %s %s --slug=%s --domain=%s --exclude=tests,docs,build,.github,assets,bin --skip-js --skip-audit',
        escapeshellarg($ext),
        escapeshellarg($pot),
        $slug,
        $domain
    ),
    'extract (built js) + merge' => sprintf(
        'wp i18n make-pot %s %s --slug=%s --domain=%s --include=blocks --skip-php --skip-block-json --skip-audit --merge',
        escapeshellarg($ext),
        escapeshellarg($pot),
        $slug,
        $domain
    ),
    'deduplicate' => sprintf('msguniq %s -o %s', escapeshellarg($pot), escapeshellarg($uniq)),
    'translate (vi)' => sprintf(
        'php %s %s %s %s',
        escapeshellarg(__DIR__ . '/build-po.php'),
        escapeshellarg($uniq),
        escapeshellarg($map),
        escapeshellarg($po)
    ),
    'compile .mo' => sprintf(
        'msgfmt --check -o %s %s',
        escapeshellarg($mo),
        escapeshellarg($po)
    ),
    'compile .json (jed)' => sprintf(
        'wp i18n make-json %s %s --domain=%s --pretty-print',
        escapeshellarg($po),
        escapeshellarg($lang),
        $domain
    ),
    'rename .json per block handle' => sprintf(
        'php %s',
        escapeshellarg(__DIR__ . '/rename-json.php')
    ),
];

foreach ($steps as $label => $cmd) {
    echo "--- {$label}\n{$cmd}\n";
    exec($cmd . ' 2>&1', $out, $code);
    echo implode("\n", $out) . "\n";
    $out = [];
    if ($code !== 0) {
        fwrite(STDERR, "FAILED (exit {$code}): {$label}\n");
        exit($code);
    }
}

if (is_file($uniq)) {
    unlink($uniq);
}

echo "done.\n";
