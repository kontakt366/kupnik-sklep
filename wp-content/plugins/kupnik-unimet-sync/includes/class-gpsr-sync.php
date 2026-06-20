<?php
defined('ABSPATH') || exit;

class Kunimet_Gpsr_Sync {

    public function run(): array {
        Kunimet_Logger::info('gpsr', 'Sync GPSR start');
        $response = wp_remote_get(KUNIMET_FEED_BASE . 'cennik_gpsr.xml', ['timeout' => 120, 'sslverify' => false]);

        if (is_wp_error($response)) {
            Kunimet_Logger::error('gpsr', $response->get_error_message());
            return ['updated' => 0, 'errors' => 1];
        }

        $xml = simplexml_load_string(wp_remote_retrieve_body($response));
        if (!$xml) {
            Kunimet_Logger::error('gpsr', 'Nieprawidłowy XML');
            return ['updated' => 0, 'errors' => 1];
        }

        $updated = 0;
        $errors  = 0;

        foreach ($xml->item as $item) {
            try {
                $sku = trim((string)$item->INDEKS);
                $product_id = wc_get_product_id_by_sku($sku);
                if (!$product_id) continue;

                $addr = implode(', ', array_filter([
                    trim((string)$item->ADRES_LIN_1),
                    trim((string)$item->ADRES_LIN_2),
                    trim((string)$item->POSTAL_CODE) . ' ' . trim((string)$item->CITY),
                    trim((string)$item->KRAJ),
                ]));

                $meta = [
                    '_gpsr_producent'   => trim((string)$item->NAME_OF_COMPANY),
                    '_gpsr_adres'       => $addr,
                    '_gpsr_kraj'        => trim((string)$item->KRAJ),
                    '_gpsr_kontakt'     => trim((string)$item->E_MAIL) . ' | ' . trim((string)$item->TELEFON),
                    '_gpsr_ostrzezenia' => trim((string)$item->GPSR_BHP),
                ];

                foreach ($meta as $key => $val) {
                    update_post_meta($product_id, $key, sanitize_text_field($val));
                }
                $updated++;

            } catch (\Throwable $e) {
                $errors++;
                Kunimet_Logger::error('gpsr', 'SKU ' . ($sku ?? '?') . ': ' . $e->getMessage());
            }
        }

        Kunimet_Logger::success('gpsr', "updated={$updated} errors={$errors}");
        return compact('updated', 'errors');
    }
}
