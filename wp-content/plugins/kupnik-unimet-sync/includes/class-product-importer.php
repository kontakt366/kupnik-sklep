<?php
defined('ABSPATH') || exit;

class Kunimet_Product_Importer {

    private int $created = 0;
    private int $updated = 0;
    private int $skipped = 0;
    private int $errors  = 0;

    private const SKIP_COURIER = ['9. BRAK'];

    // ── Public entry points ──────────────────────────────────────────────────

    public function run_full(bool $verbose = false): array {
        Kunimet_Logger::info('products', 'Pełny sync start');
        $xml = $this->fetch_xml(KUNIMET_FEED_BASE . 'cennik.xml');
        if (!$xml) return $this->result();

        $gpsr_map = $this->load_gpsr_map();
        $total    = count($xml->item);
        Kunimet_Logger::info('products', "Załadowano {$total} produktów");

        foreach ($xml->item as $item) {
            try {
                $this->process($item, $gpsr_map, $verbose);
            } catch (\Throwable $e) {
                $this->errors++;
                Kunimet_Logger::error('products', 'Błąd [' . (string)$item->INDEKS . ']: ' . $e->getMessage());
            }
        }

        Kunimet_Logger::success('products', "created={$this->created} updated={$this->updated} skipped={$this->skipped} errors={$this->errors}");
        return $this->result();
    }

    public function run_incremental(): array {
        Kunimet_Logger::info('products', 'Incremental sync start');
        $idx_xml = $this->fetch_xml(KUNIMET_FEED_BASE . 'indeksy_24.xml');
        if (!$idx_xml) return $this->result();

        $indices = [];
        foreach ($idx_xml->item as $i) {
            $indices[trim((string)$i->INDEKS)] = true;
        }
        // Fallback: flat text values
        if (empty($indices)) {
            foreach ($idx_xml->indeks as $i) {
                $indices[trim((string)$i)] = true;
            }
        }

        if (empty($indices)) {
            Kunimet_Logger::success('products', 'Brak zmian w 24h');
            return $this->result();
        }

        Kunimet_Logger::info('products', 'Zmian: ' . count($indices));
        $full_xml = $this->fetch_xml(KUNIMET_FEED_BASE . 'cennik.xml');
        $gpsr_map = $this->load_gpsr_map();
        if (!$full_xml) return $this->result();

        foreach ($full_xml->item as $item) {
            $sku = trim((string)$item->INDEKS);
            if (!isset($indices[$sku])) continue;
            try {
                $this->process($item, $gpsr_map);
            } catch (\Throwable $e) {
                $this->errors++;
                Kunimet_Logger::error('products', 'Incr błąd [' . $sku . ']: ' . $e->getMessage());
            }
        }

        Kunimet_Logger::success('products', "Incremental: created={$this->created} updated={$this->updated}");
        return $this->result();
    }

    // ── Core processor ───────────────────────────────────────────────────────

    private function process(\SimpleXMLElement $item, array $gpsr_map, bool $verbose = false): void {
        $sku          = trim((string)$item->INDEKS);
        $courier_type = trim((string)$item->TYP_KURIERA);
        $cat_path     = trim((string)$item->KATEGORIE); // uses > separator

        // Skip excluded categories
        if (Kunimet_Category_Mapper::is_excluded($cat_path)) {
            $this->skipped++;
            return;
        }

        // Skip unshippable
        if (in_array($courier_type, self::SKIP_COURIER, true)) {
            $this->skipped++;
            return;
        }

        // Price
        $price_netto = (float)str_replace(',', '.', (string)$item->CENA_KLIENT);
        if ($price_netto <= 0) {
            $this->skipped++;
            return;
        }

        $sell_price = Kunimet_Pricing::sell_price($price_netto);
        $stock_qty  = (int)$item->STAN_NA_MAGAZYNIE;

        // Get or create WC product
        $product_id = wc_get_product_id_by_sku($sku);
        $is_new     = !$product_id;
        $product    = $is_new ? new WC_Product_Simple() : wc_get_product($product_id);
        if (!$product) { $product = new WC_Product_Simple(); $is_new = true; }

        // Basic WC fields
        $product->set_sku($sku);
        $product->set_name(trim((string)$item->NAZWA));
        $product->set_description(wp_kses_post((string)$item->OPIS));
        $product->set_short_description(wp_kses_post((string)$item->KROTKI_OPIS));
        $product->set_regular_price((string)$sell_price);
        $product->set_status($stock_qty > 0 ? 'publish' : 'private');
        $product->set_catalog_visibility('visible');

        // Stock
        $product->set_manage_stock(true);
        $product->set_stock_quantity($stock_qty);
        $product->set_stock_status($stock_qty > 0 ? 'instock' : 'outofstock');

        // Weight
        $weight = str_replace(',', '.', (string)$item->WAGA);
        if ($weight > 0) $product->set_weight($weight);

        // VAT — strip % sign
        $vat_str  = trim((string)$item->VAT);
        $vat_rate = rtrim($vat_str, '%');
        $product->set_tax_class($this->vat_to_tax_class($vat_rate));
        $product->set_tax_status('taxable');

        // Category (> separator in Unimet XML)
        $term_id = Kunimet_Category_Mapper::get_or_create_by_separator($cat_path, '>');
        if ($term_id) $product->set_category_ids([$term_id]);

        // Shipping class
        if ($courier_type) {
            $sc_id = $this->get_or_create_shipping_class($courier_type);
            if ($sc_id) $product->set_shipping_class_id($sc_id);
        }

        $saved_id = $product->save();

        // ── Meta ──
        $jm       = trim((string)$item->JEDNOSTKA_MIARY);
        $pkg_size = max(1, (int)$item->OPAKOWANIE);
        $min_zam  = max(1, (int)$item->MIN_ZAM);

        update_post_meta($saved_id, '_unimet_indeks',       $sku);
        update_post_meta($saved_id, '_unimet_jm',           $jm);
        update_post_meta($saved_id, '_unimet_qty_per_pack', $pkg_size);
        update_post_meta($saved_id, '_unimet_min_qty',      $min_zam);
        update_post_meta($saved_id, '_unimet_price_netto',  $price_netto);
        update_post_meta($saved_id, '_unimet_courier_type', $courier_type);
        update_post_meta($saved_id, '_unimet_last_sync',    time());

        $ean = trim((string)$item->EAN);
        if ($ean) update_post_meta($saved_id, '_unimet_ean', $ean);

        $producent = trim((string)$item->PRODUCENT);
        if ($producent) {
            update_post_meta($saved_id, '_unimet_producent', $producent);
            $this->set_attribute($saved_id, 'Producent', $producent);
        }

        // Images (from CDN, no download)
        if ($is_new) {
            $images = [];
            $main   = trim((string)$item->MAIN_PICTURE);
            if ($main) $images[] = $main;
            if (isset($item->PICTURES->PICTURE)) {
                foreach ($item->PICTURES->PICTURE as $pic) {
                    $url = trim((string)$pic);
                    if ($url && !in_array($url, $images)) $images[] = $url;
                }
            }
            if ($images) update_post_meta($saved_id, '_unimet_images', $images);
        }

        // GPSR
        if (isset($gpsr_map[$sku])) {
            foreach ($gpsr_map[$sku] as $key => $val) {
                if ($val !== '') update_post_meta($saved_id, $key, sanitize_text_field($val));
            }
        }

        if ($is_new) $this->created++; else $this->updated++;
        if ($verbose) WP_CLI::log(($is_new ? 'NEW' : 'UPD') . " [{$sku}] " . trim((string)$item->NAZWA));
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function fetch_xml(string $url): ?\SimpleXMLElement {
        $response = wp_remote_get($url, ['timeout' => 180, 'sslverify' => false]);
        if (is_wp_error($response)) {
            Kunimet_Logger::error('products', 'HTTP błąd: ' . $response->get_error_message());
            return null;
        }
        libxml_use_internal_errors(true);
        $xml = simplexml_load_string(wp_remote_retrieve_body($response));
        if (!$xml) {
            Kunimet_Logger::error('products', 'Nieprawidłowy XML: ' . $url);
            return null;
        }
        return $xml;
    }

    private function load_gpsr_map(): array {
        $xml = $this->fetch_xml(KUNIMET_FEED_BASE . 'cennik_gpsr.xml');
        if (!$xml) return [];
        $map = [];
        foreach ($xml->item as $item) {
            $sku = trim((string)$item->INDEKS);
            $addr = implode(', ', array_filter([
                trim((string)$item->ADRES_LIN_1),
                trim((string)$item->ADRES_LIN_2),
                trim((string)$item->POSTAL_CODE) . ' ' . trim((string)$item->CITY),
                trim((string)$item->KRAJ),
            ]));
            $map[$sku] = [
                '_gpsr_producent'   => trim((string)$item->NAME_OF_COMPANY),
                '_gpsr_adres'       => $addr,
                '_gpsr_kraj'        => trim((string)$item->KRAJ),
                '_gpsr_kontakt'     => trim((string)$item->E_MAIL) . ' | ' . trim((string)$item->TELEFON),
                '_gpsr_ostrzezenia' => trim((string)$item->GPSR_BHP),
                '_gpsr_certyfikaty' => '',
                '_gpsr_plik_url'    => '',
            ];
        }
        return $map;
    }

    private function set_attribute(int $product_id, string $name, string $value): void {
        $attrs = get_post_meta($product_id, '_product_attributes', true) ?: [];
        $key   = sanitize_title($name);
        $attrs[$key] = [
            'name'         => $name,
            'value'        => $value,
            'position'     => count($attrs),
            'is_visible'   => 1,
            'is_variation' => 0,
            'is_taxonomy'  => 0,
        ];
        update_post_meta($product_id, '_product_attributes', $attrs);
    }

    private function get_or_create_shipping_class(string $type): int {
        $slug     = sanitize_title($type);
        $existing = get_term_by('slug', $slug, 'product_shipping_class');
        if ($existing) return (int)$existing->term_id;
        $result = wp_insert_term($type, 'product_shipping_class', ['slug' => $slug]);
        return is_wp_error($result) ? 0 : (int)$result['term_id'];
    }

    private function vat_to_tax_class(string $vat): string {
        return match($vat) {
            '8'  => 'reduced-rate',
            '5'  => 'zero-rate',
            '0'  => 'zero-rate',
            default => '',
        };
    }

    private function result(): array {
        return ['created' => $this->created, 'updated' => $this->updated, 'skipped' => $this->skipped, 'errors' => $this->errors];
    }
}
