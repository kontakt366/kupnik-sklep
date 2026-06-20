<?php
defined('ABSPATH') || exit;

class Kunimet_Sync_Manager {

    public static function run_products_full(): void {
        $importer = new Kunimet_Product_Importer();
        $result   = $importer->run_full(defined('WP_CLI') && WP_CLI);
        if (defined('WP_CLI') && WP_CLI) {
            WP_CLI::success('Produkty: ' . json_encode($result));
        }
    }

    public static function run_products_incremental(): void {
        $importer = new Kunimet_Product_Importer();
        $result   = $importer->run_incremental();
        if (defined('WP_CLI') && WP_CLI) {
            WP_CLI::success('Incr: ' . json_encode($result));
        }
    }

    public static function run_stock(): void {
        $sync   = new Kunimet_Stock_Sync();
        $result = $sync->run();
        if (defined('WP_CLI') && WP_CLI) {
            WP_CLI::success('Stock: ' . json_encode($result));
        }
    }

    public static function run_shipping(): void {
        $sync   = new Kunimet_Shipping_Sync();
        $result = $sync->run();
        if (defined('WP_CLI') && WP_CLI) {
            WP_CLI::success('Shipping: ' . json_encode($result));
        }
    }

    public static function run_gpsr(): void {
        $sync   = new Kunimet_Gpsr_Sync();
        $result = $sync->run();
        if (defined('WP_CLI') && WP_CLI) {
            WP_CLI::success('GPSR: ' . json_encode($result));
        }
    }
}
