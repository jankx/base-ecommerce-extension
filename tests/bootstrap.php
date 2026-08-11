<?php
/**
 * Base Ecommerce Extension - PHPUnit bootstrap.
 *
 * Loads:
 *  1. Composer autoloader (dev deps: phpunit, brain/monkey, mockery).
 *  2. PSR-4 fallback autoloader for extension src.
 *  3. Framework classes (AbstractExtension / ExtensionInterface).
 *  4. WordPress function stubs via Brain\Monkey.
 */

use Brain\Monkey;

if (!defined('JANKX_ECOMMERCE_TEST_DIR')) {
    define('JANKX_ECOMMERCE_TEST_DIR', __DIR__);
}

// 1. Composer autoloader (dev dependencies).
$composerAutoload = __DIR__ . '/../libs/autoload.php';
if (file_exists($composerAutoload)) {
    require_once $composerAutoload;
}

// 2. PSR-4 fallback autoloader for this extension.
spl_autoload_register(function ($class) {
    $prefixes = [
        'Jankx\\Extensions\\Ecommerce\\' => __DIR__ . '/../src/',
    ];

    foreach ($prefixes as $prefix => $baseDir) {
        $len = strlen($prefix);
        if (strncmp($prefix, $class, $len) !== 0) {
            continue;
        }

        $relativeClass = substr($class, $len);
        $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';
        if (file_exists($file)) {
            require_once $file;
            return;
        }
    }
});

// 3. Framework classes used by the extension.
$frameworkDir = __DIR__ . '/../../../../jankx/includes/framework';
if (file_exists($frameworkDir . '/Contracts/Extension/ExtensionInterface.php')) {
    require_once $frameworkDir . '/Contracts/Extension/ExtensionInterface.php';
}
if (file_exists($frameworkDir . '/Extensions/AbstractExtension.php')) {
    require_once $frameworkDir . '/Extensions/AbstractExtension.php';
}

// 3b. WordPress class stubs (wpdb, WP_Post, etc.)
if (!defined('OBJECT')) {
    define('OBJECT', 'OBJECT');
}
if (!defined('ARRAY_A')) {
    define('ARRAY_A', 'ARRAY_A');
}
if (!class_exists('wpdb')) {
    class wpdb
    {
        public $prefix = 'wp_';
        public $insert_id = 0;
        public $num_rows = 0;
        public $last_error = '';
        public $rows_affected = 0;

        public function prepare($query, ...$args) { return $query; }
        public function get_row($query, $output = OBJECT, $offset = 0) { return null; }
        public function get_results($query, $output = ARRAY_A) { return []; }
        public function get_var($query = null, $x = 0, $y = 0) { return null; }
        public function insert($table, $data, $format = null) { return true; }
        public function update($table, $data, $where = null, $format = null, $where_format = null) { return 1; }
        public function delete($table, $where = null, $format = null) { return 1; }
        public function esc_like($text) { return addcslashes($text, '%_'); }
        public function get_charset_collate() { return 'utf8mb4_unicode_ci'; }
    }
}

// 4. WordPress function stubs via Brain\Monkey.
// These are called in setUp() of each test case.
function stub_wp_ecommerce_functions()
{
    Monkey\Functions\when('__')->returnArg();
    Monkey\Functions\when('add_action')->justReturn(true);
    Monkey\Functions\when('add_filter')->justReturn(true);
    Monkey\Functions\when('apply_filters')->alias(function ($tag, $value) {
        return $value;
    });
    Monkey\Functions\when('do_action')->justReturn(null);
    Monkey\Functions\when('get_option')->alias(function ($key, $default = false) {
        return $GLOBALS['__wp_options'][$key] ?? $default;
    });
    Monkey\Functions\when('update_option')->alias(function ($key, $value) {
        $GLOBALS['__wp_options'][$key] = $value;
        return true;
    });
    Monkey\Functions\when('current_time')->justReturn('2026-08-11 12:00:00');
    Monkey\Functions\when('get_current_user_id')->justReturn(1);
    Monkey\Functions\when('is_user_logged_in')->justReturn(true);
    Monkey\Functions\when('wp_get_current_user')->alias(function () {
        return (object) ['ID' => get_current_user_id(), 'user_email' => 'test@example.com'];
    });
    Monkey\Functions\when('get_user_meta')->justReturn('');
    Monkey\Functions\when('update_user_meta')->justReturn(true);
    Monkey\Functions\when('get_userdata')->alias(function ($userId) {
        return (object) ['ID' => $userId, 'display_name' => "User {$userId}"];
    });
    Monkey\Functions\when('sanitize_key')->alias(function ($key) {
        return strtolower(preg_replace('/[^a-z0-9_]/', '', $key));
    });
    Monkey\Functions\when('absint')->alias(function ($val) {
        return abs((int) $val);
    });
    Monkey\Functions\when('wp_json_encode')->alias(function ($data, $options = 0) {
        return json_encode($data, $options);
    });
    Monkey\Functions\when('esc_html')->returnArg();
    Monkey\Functions\when('esc_html__')->returnArg();
    Monkey\Functions\when('esc_attr')->returnArg();
    Monkey\Functions\when('esc_url')->returnArg();
    Monkey\Functions\when('home_url')->alias(function ($path = '', $scheme = null) {
        return 'http://example.com' . $path;
    });
    Monkey\Functions\when('get_permalink')->alias(function ($post) {
        $id = is_object($post) ? $post->ID : $post;
        return 'http://example.com/?p=' . $id;
    });
    Monkey\Functions\when('get_block_wrapper_attributes')->alias(function ($attrs = []) {
        $pairs = [];
        foreach ($attrs as $key => $value) {
            $pairs[] = $key . '="' . $value . '"';
        }
        return $pairs ? ' ' . implode(' ', $pairs) : '';
    });
    Monkey\Functions\when('date_i18n')->alias(function ($format, $timestamp = null) {
        return date($format, $timestamp ?: time());
    });
    Monkey\Functions\when('get_option')->alias(function ($key, $default = false) {
        return $GLOBALS['__wp_options'][$key] ?? $default;
    });
    Monkey\Functions\when('dbDelta')->justReturn([]);

    $GLOBALS['__wp_options'] = [];
}
