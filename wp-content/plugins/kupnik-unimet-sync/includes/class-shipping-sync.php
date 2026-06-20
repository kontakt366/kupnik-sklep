<?php
defined('ABSPATH') || exit;

class Kunimet_Shipping_Sync {

    // Courier types that require Unimet's own label (no customer label needed)
    public const UNIMET_LABEL = ['3. KURIER STD', '4. KURIER NST', '5. PÓŁ PALETY', '6. PALETA STD', '7. PALETA NST', '8. DŁUŻYCA', '10. GABARYT NST'];

    // Courier types that are parcel lockers
    public const PACZKOMAT    = ['2A.PACZKOMAT-A', '2B.PACZKOMAT-B', '2C.PACZKOMAT-C'];

    public function run(): array {
        Kunimet_Logger::info('shipping', 'Sync typów wysyłki start');

        $url      = KUNIMET_FEED_BASE . 'kurier.xml';
        $response = wp_remote_get($url, ['timeout' => 60, 'sslverify' => false]);

        if (is_wp_error($response)) {
            Kunimet_Logger::error('shipping', $response->get_error_message());
            return ['updated' => 0, 'errors' => 1];
        }

        $xml = simplexml_load_string(wp_remote_retrieve_body($response));
        if (!$xml) {
            Kunimet_Logger::error('shipping', 'Nieprawidłowy XML');
            return ['updated' => 0, 'errors' => 1];
        }

        $updated = 0;
        $errors  = 0;

        foreach ($xml->produkt as $p) {
            try {
                $sku          = trim((string)$p->indeks);
                $courier_type = trim((string)$p->rodzaj_kuriera);

                $product_id = wc_get_product_id_by_sku($sku);
                if (!$product_id) continue;

                $current = get_post_meta($product_id, '_unimet_courier_type', true);
                if ($current === $courier_type) continue;

                update_post_meta($product_id, '_unimet_courier_type', $courier_type);

                // Update WC shipping class
                $product = wc_get_product($product_id);
                if ($product) {
                    $sc_id = $this->get_or_create_shipping_class($courier_type);
                    if ($sc_id) {
                        $product->set_shipping_class_id($sc_id);
                        $product->save();
                    }
                }

                $updated++;
            } catch (\Throwable $e) {
                $errors++;
                Kunimet_Logger::error('shipping', 'SKU ' . ($sku ?? '?') . ': ' . $e->getMessage());
            }
        }

        Kunimet_Logger::success('shipping', "Wysyłka: updated={$updated} errors={$errors}");
        return compact('updated', 'errors');
    }

    private function get_or_create_shipping_class(string $type): int {
        $slug     = sanitize_title($type);
        $existing = get_term_by('slug', $slug, 'product_shipping_class');
        if ($existing) return (int)$existing->term_id;
        $result = wp_insert_term($type, 'product_shipping_class', ['slug' => $slug]);
        return is_wp_error($result) ? 0 : (int)$result['term_id'];
    }
}
