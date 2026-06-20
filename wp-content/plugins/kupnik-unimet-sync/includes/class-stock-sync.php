<?php
defined('ABSPATH') || exit;

class Kunimet_Stock_Sync {

    public function run(): array {
        Kunimet_Logger::info('stock', 'Sync stanów start');
        $response = wp_remote_get(KUNIMET_FEED_BASE . 'products_stock.xml', ['timeout' => 60, 'sslverify' => false]);

        if (is_wp_error($response)) {
            Kunimet_Logger::error('stock', $response->get_error_message());
            return ['updated' => 0, 'errors' => 1];
        }

        libxml_use_internal_errors(true);
        $xml = simplexml_load_string(wp_remote_retrieve_body($response));
        if (!$xml) {
            Kunimet_Logger::error('stock', 'Nieprawidłowy XML');
            return ['updated' => 0, 'errors' => 1];
        }

        $updated = 0;
        $hidden  = 0;
        $errors  = 0;

        foreach ($xml->item as $item) {
            try {
                // products_stock.xml uses SYMBOL as SKU
                $sku     = trim((string)$item->SYMBOL);
                $qty     = (int)$item->STAN;
                $instock = $qty > 0;

                $product_id = wc_get_product_id_by_sku($sku);
                if (!$product_id) continue;

                $product = wc_get_product($product_id);
                if (!$product) continue;

                if ($product->get_stock_quantity() === $qty) continue;

                $product->set_stock_quantity($qty);
                $product->set_stock_status($instock ? 'instock' : 'outofstock');
                $product->set_status($instock ? 'publish' : 'private');
                $product->save();

                $updated++;
                if (!$instock) $hidden++;

            } catch (\Throwable $e) {
                $errors++;
                Kunimet_Logger::error('stock', 'SKU ' . ($sku ?? '?') . ': ' . $e->getMessage());
            }
        }

        Kunimet_Logger::success('stock', "updated={$updated} hidden={$hidden} errors={$errors}");
        return compact('updated', 'hidden', 'errors');
    }
}
