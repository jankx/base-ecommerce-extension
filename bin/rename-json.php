<?php
/**
 * Rename the Jed JSON files produced by `wp i18n make-json` from
 *   <domain>-<locale>-<md5(source-path)>.json
 * to the name WordPress actually requests for a block script:
 *   <domain>-<locale>-<block-asset-handle>.json
 *
 * Usage: php rename-json.php [--dry]
 *
 * @package base-ecommerce
 */
$dry    = in_array('--dry', $argv, true);
$ext    = dirname(__DIR__);
$lang   = $ext . '/languages';
$domain = 'base-ecommerce';
$locale = 'vi';

$FIELDS = [
    'editorScript'      => 'editor-script',
    'script'            => 'script',
    'viewScript'        => 'view-script',
    'viewScriptModule'  => 'view-script-module',
];

$dirs    = glob($ext . '/blocks/*', GLOB_ONLYDIR) ?: [];
$renamed = 0;
$missing = [];

foreach ($dirs as $dir) {
    $bj = $dir . '/block.json';
    if (!is_file($bj)) {
        continue;
    }
    $meta = json_decode((string) file_get_contents($bj), true);
    if (!is_array($meta) || empty($meta['name'])) {
        continue;
    }

    foreach ($FIELDS as $field => $suffix) {
        if (empty($meta[$field])) {
            continue;
        }
        $values = is_array($meta[$field]) ? $meta[$field] : [$meta[$field]];
        foreach ($values as $index => $value) {
            if (!is_string($value) || strpos($value, 'file:./') !== 0) {
                continue;
            }

            $source = 'blocks/' . basename($dir) . '/' . substr($value, strlen('file:./'));
            $handle = str_replace('/', '-', $meta['name']) . '-' . $suffix;
            if ($index > 0) {
                $handle .= '-' . $index;
            }

            $from = sprintf('%s/%s-%s-%s.json', $lang, $domain, $locale, md5($source));
            $to   = sprintf('%s/%s-%s-%s.json', $lang, $domain, $locale, $handle);

            if (!is_file($from)) {
                continue;
            }
            echo basename($from) . ' -> ' . basename($to) . PHP_EOL;
            if (!$dry) {
                rename($from, $to);
            }
            $renamed++;
        }
    }
}

echo "renamed: $renamed" . PHP_EOL;
