<?php
/**
 * Build languages/jankx-vi.po from the POT + a msgid->msgstr JSON map.
 * Usage: php build-po.php <pot> <translations.json> <out.po>
 */
error_reporting(E_ALL);
ini_set('display_errors', '1');
mb_internal_encoding('UTF-8');

if ($argc < 4) {
    fwrite(STDERR, "usage: php build-po.php <pot> <translations.json> <out.po>\n");
    exit(1);
}

list(, $potFile, $mapFile, $outFile) = $argc > 4 ? array_slice($argv, 0, 5) : $argv;

$pot = file_get_contents($potFile);
if ($pot === false) { fwrite(STDERR, "cannot read $potFile\n"); exit(1); }

$map = json_decode(file_get_contents($mapFile), true);
if (!is_array($map)) { fwrite(STDERR, "bad JSON in $mapFile\n"); exit(1); }

function po_unescape($s) {
    return stripcslashes($s);
}
function po_escape($s) {
    return str_replace(['\\', '"', "\n", "\r", "\t"], ['\\\\', '\\"', '\\n', '\\r', '\\t'], $s);
}
function is_vietnamese($s) {
    return (bool) preg_match('/[ăâđêôơư]|[àáảãạăằắẳẵặâầấẩẫậèéẻẽẹêềếểễệìíỉĩịòóỏõọôồốổỗộơờớởỡợùúủũụưừứửữựỳýỷỹỵđĐĂÂĐÊÔƠƯ]/u', $s);
}

// Split into blocks on blank lines.
$blocks = preg_split("/\n\s*\n/", trim($pot));

$entries = [];
$missing = [];
$header = null;

foreach ($blocks as $block) {
    $block = trim($block, "\r");
    if ($block === '') continue;
    $lines = explode("\n", $block);

    $comments = [];
    $fields = [];   // ordered: [['key'=>..., 'value'=>...], ...]
    $i = 0;
    $n = count($lines);
    while ($i < $n) {
        $line = rtrim($lines[$i], "\r");
        if ($line === '') { $i++; continue; }
        if ($line[0] === '#') { $comments[] = $line; $i++; continue; }
        if (preg_match('/^(msgctxt|msgid|msgid_plural|msgstr(?:\[\d+\])?)\s+"(.*)"$/s', $line, $m)) {
            $key = $m[1];
            $val = $m[2];
            $i++;
            while ($i < $n && preg_match('/^"(.*)"$/s', rtrim($lines[$i], "\r"), $mm)) {
                $val .= $mm[1];
                $i++;
            }
            $fields[] = ['key' => $key, 'value' => po_unescape($val)];
            continue;
        }
        $i++;
    }

    if (!$fields) continue;

    $msgid = null;
    $msgctxt = null;
    foreach ($fields as $f) {
        if ($f['key'] === 'msgid') $msgid = $f['value'];
        if ($f['key'] === 'msgctxt') $msgctxt = $f['value'];
    }
    if ($msgid === null) continue;

    if ($msgid === '') {          // header entry
        $header = ['comments' => $comments, 'fields' => $fields];
        continue;
    }

    $entries[] = ['comments' => $comments, 'fields' => $fields, 'msgid' => $msgid, 'msgctxt' => $msgctxt];
}

// Header: rewrite metadata for Vietnamese.
$meta = [
    'Project-Id-Version: base-ecommerce',
    'Report-Msgid-Bugs-To: https://nibitour.vn',
    'POT-Creation-Date: ' . gmdate('Y-m-d H:i:s') . '+0000',
    'MIME-Version: 1.0',
    'Content-Type: text/plain; charset=UTF-8',
    'Content-Transfer-Encoding: 8bit',
    'PO-Revision-Date: YEAR-MO-DA HO:MI+ZONE',
    'Last-Translator: FULL NAME <EMAIL@ADDRESS>',
    'Language-Team: LANGUAGE <LL@li.org>',
    'Language: vi',
    'Plural-Forms: nplurals=1; plural=0;',
    'X-Generator: base-ecommerce i18n build',
];

$out = "# Copyright (C) 2026 base-ecommerce\n";
$out .= "# This file is distributed under the same license as the base-ecommerce package.\n";
$out .= "msgid \"\"\nmsgstr \"\"\n";
foreach ($meta as $m) { $out .= '"' . po_escape($m) . "\\n\"\n"; }
$out .= "\n";

$filled = 0;
foreach ($entries as $e) {
    $msgid = $e['msgid'];
    if (isset($map[$msgid])) {
        $msgstr = $map[$msgid];
    } elseif (is_vietnamese($msgid)) {
        $msgstr = $msgid;
    } else {
        $missing[] = $msgid;
        $msgstr = '';
    }
    if ($msgstr !== '') $filled++;

    foreach ($e['comments'] as $c) { $out .= $c . "\n"; }
    if ($e['msgctxt'] !== null) { $out .= 'msgctxt "' . po_escape($e['msgctxt']) . '"' . "\n"; }
    $out .= 'msgid "' . po_escape($msgid) . '"' . "\n";
    $out .= 'msgstr "' . po_escape($msgstr) . '"' . "\n\n";
}

file_put_contents($outFile, $out);

echo "entries: " . count($entries) . "\n";
echo "filled:  $filled\n";
echo "missing: " . count($missing) . "\n";
foreach (array_slice($missing, 0, 60) as $m) { echo "  ! " . $m . "\n"; }
exit(count($missing) ? 2 : 0);
