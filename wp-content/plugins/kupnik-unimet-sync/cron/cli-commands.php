<?php
defined('ABSPATH') || exit;

/**
 * WP-CLI commands for Unimet sync.
 *
 * Usage:
 *   wp kunimet sync products      # Full product import
 *   wp kunimet sync products --incremental
 *   wp kunimet sync stock
 *   wp kunimet sync shipping
 *   wp kunimet sync gpsr
 *   wp kunimet sync all
 *   wp kunimet status
 */
class Kunimet_CLI {

    /**
     * Run sync tasks.
     *
     * ## OPTIONS
     *
     * <task>
     * : Task to run: products, stock, shipping, gpsr, all
     *
     * [--incremental]
     * : For products: only sync changed items (indeksy_24)
     *
     * ## EXAMPLES
     *
     *   wp kunimet sync products
     *   wp kunimet sync products --incremental
     *   wp kunimet sync stock
     *   wp kunimet sync all
     */
    public function sync(array $args, array $assoc): void {
        $task        = $args[0] ?? 'all';
        $incremental = !empty($assoc['incremental']);

        switch ($task) {
            case 'products':
                WP_CLI::log('Sync produktów' . ($incremental ? ' (incremental)' : ' (full)') . '...');
                if ($incremental) Kunimet_Sync_Manager::run_products_incremental();
                else              Kunimet_Sync_Manager::run_products_full();
                break;

            case 'stock':
                WP_CLI::log('Sync stanów...');
                Kunimet_Sync_Manager::run_stock();
                break;

            case 'shipping':
                WP_CLI::log('Sync typów wysyłki...');
                Kunimet_Sync_Manager::run_shipping();
                break;

            case 'gpsr':
                WP_CLI::log('Sync GPSR...');
                Kunimet_Sync_Manager::run_gpsr();
                break;

            case 'all':
                WP_CLI::log('Sync wszystkiego...');
                Kunimet_Sync_Manager::run_products_full();
                Kunimet_Sync_Manager::run_stock();
                Kunimet_Sync_Manager::run_shipping();
                Kunimet_Sync_Manager::run_gpsr();
                break;

            default:
                WP_CLI::error("Nieznane zadanie: {$task}. Użyj: products, stock, shipping, gpsr, all");
        }
    }

    /**
     * Show sync status.
     *
     * ## EXAMPLES
     *
     *   wp kunimet status
     */
    public function status(): void {
        $syncs = get_option('kunimet_last_syncs', []);
        $now   = time();

        $rows = [];
        foreach (['products', 'stock', 'shipping', 'gpsr'] as $key) {
            $last    = $syncs[$key] ?? 0;
            $rows[]  = [
                'Task'      => $key,
                'Last sync' => $last ? date('Y-m-d H:i:s', $last) : 'NEVER',
                'Minutes ago' => $last ? round(($now - $last) / 60) : 'N/A',
            ];
        }
        WP_CLI\Utils\format_items('table', $rows, ['Task', 'Last sync', 'Minutes ago']);
    }
}

WP_CLI::add_command('kunimet', 'Kunimet_CLI');
