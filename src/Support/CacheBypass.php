<?php
namespace Jankx\Extensions\Ecommerce\Support;

/**
 * Marks the current response as uncacheable for LiteSpeed (and other
 * WordPress) page caches.
 *
 * LiteSpeed decides cache eligibility from the LSCWP control flags and the
 * DONOTCACHEPAGE constant at response time - response-time `Cache-Control`
 * headers alone are not honoured. Fire this as early as possible, before
 * the route/page callback runs.
 */
final class CacheBypass
{
    public static function mark(string $reason): void
    {
        if (!headers_sent()) {
            header('X-LiteSpeed-Cache-Control: no-cache, no-vary', true);
            header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0, private', true);
        }

        if (!defined('DONOTCACHEPAGE')) {
            define('DONOTCACHEPAGE', true);
        }

        do_action('litespeed_control_set_nocache', $reason);
    }
}
