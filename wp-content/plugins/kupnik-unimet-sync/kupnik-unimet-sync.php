<?php
/**
 * Plugin Name: Kupnik — Unimet Sync
 * Description: Integracja z hurtownią Unimet (produkty, stany, wysyłka, GPSR)
 * Version: 1.0.0
 * Author: Kupnik.pl
 * Requires WC: 7.0
 */

defined('ABSPATH') || exit;

define('KUNIMET_VERSION',   '1.0.0');
define('KUNIMET_DIR',       plugin_dir_path(__FILE__));
define('KUNIMET_LOG_DIR',   KUNIMET_DIR . 'logs/');
define('KUNIMET_FEED_BASE', 'https://img.unimet.pl/');

// ── Autoload includes ────────────────────────────────────────────────────────
foreach ([
    'class-logger',
    'class-pricing',
    'class-category-mapper',
    'class-product-importer',
    'class-stock-sync',
    'class-shipping-sync',
    'class-gpsr-sync',
    'class-sync-manager',
    'class-unit-pricing',
    'class-cart-shipping-notice',
] as $file) {
    require_once KUNIMET_DIR . 'includes/' . $file . '.php';
}

require_once KUNIMET_DIR . 'admin/class-admin.php';

// ── Boot ─────────────────────────────────────────────────────────────────────
add_action('plugins_loaded', function () {
    if (!class_exists('WooCommerce')) return;
    Kunimet_Unit_Pricing::init();
    Kunimet_Cart_Shipping_Notice::init();
    Kunimet_Admin::init();
});

// ── WP-CLI commands ───────────────────────────────────────────────────────────
if (defined('WP_CLI') && WP_CLI) {
    require_once KUNIMET_DIR . 'cron/cli-commands.php';
}

// ── REST health endpoint ──────────────────────────────────────────────────────
add_action('rest_api_init', function () {
    register_rest_route('kupnik/v1', '/health', [
        'methods'             => 'GET',
        'callback'            => function () {
            $log = get_option('kunimet_last_syncs', []);
            $ok  = true;
            $now = time();
            $checks = [
                'products'  => 26 * HOUR_IN_SECONDS,   // daily + 2h buffer
                'stock'     => 20 * MINUTE_IN_SECONDS,  // 15min + 5min buffer
                'shipping'  => 70 * MINUTE_IN_SECONDS,  // hourly + 10min buffer
                'gpsr'      => 26 * HOUR_IN_SECONDS,
            ];
            $status = [];
            foreach ($checks as $key => $max_age) {
                $last     = $log[$key] ?? 0;
                $age      = $now - $last;
                $healthy  = $age < $max_age;
                if (!$healthy) $ok = false;
                $status[$key] = [
                    'last_sync'   => $last ? date('Y-m-d H:i:s', $last) : 'never',
                    'age_minutes' => round($age / 60),
                    'healthy'     => $healthy,
                ];
            }
            // Always return HTTP 200 — Kuma json-query checks individual $.syncs.X.healthy fields
            // Returning 503 would block ALL monitors even if only one sync is late
            return new WP_REST_Response(['ok' => $ok, 'syncs' => $status], 200);
        },
        'permission_callback' => '__return_true',
    ]);
});

// ── Activation ────────────────────────────────────────────────────────────────
register_activation_hook(__FILE__, function () {
    if (!is_dir(KUNIMET_LOG_DIR)) mkdir(KUNIMET_LOG_DIR, 0755, true);
    // Default markup tiers
    if (!get_option('kunimet_markup_tiers')) {
        update_option('kunimet_markup_tiers', [
            ['max' => 10,  'markup' => 80],
            ['max' => 50,  'markup' => 60],
            ['max' => 100, 'markup' => 40],
            ['max' => 200, 'markup' => 30],
            ['max' => null,'markup' => 25],
        ]);
    }
    // Default excluded categories
    if (!get_option('kunimet_excluded_cats')) {
        update_option('kunimet_excluded_cats', [
            'Maszyny i urządzenia',
            'Maszyny ogrodowe',
            'Odzież i BHP',
            'Ogrodzenia i odwodnienia',
        ]);
    }
    // Alert email
    if (!get_option('kunimet_alert_email')) {
        update_option('kunimet_alert_email', get_option('admin_email'));
    }
});
