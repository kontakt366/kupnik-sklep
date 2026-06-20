<?php
defined('ABSPATH') || exit;

class Kunimet_Unit_Pricing {

    // JM units that represent a single piece — no sub-unit conversion needed
    private const JM_SINGLE = ['SZT', 'KPL', 'PAR', 'SET', 'KG', 'MB', 'MB.', 'M', 'L', 'ML'];

    // JM units that are bulk packs — we can show price per piece
    private const JM_BULK_STO = ['STO'];         // 100 pieces per unit
    private const JM_PACKAGE  = ['OP', 'OPK', 'PAK', 'OPAK']; // N pieces per package (N = OPAKOWANIE)

    public static function init(): void {
        add_action('woocommerce_after_shop_loop_item_title',     [__CLASS__, 'show_archive'],  5);
        add_action('woocommerce_single_product_summary',         [__CLASS__, 'show_single'],  11);
        add_filter('woocommerce_quantity_input_args',            [__CLASS__, 'quantity_args'], 10, 2);
        add_filter('woocommerce_add_to_cart_validation',         [__CLASS__, 'validate_qty'],  10, 3);
        // Images: archive loop
        add_filter('woocommerce_product_get_image',                   [__CLASS__, 'external_image'],        10, 5);
        // Images: single product page (WC uses a different filter here)
        add_filter('woocommerce_single_product_image_thumbnail_html', [__CLASS__, 'single_product_image'], 10, 2);
        add_filter('post_thumbnail_html',                             [__CLASS__, 'external_thumbnail'],   10, 5);
        add_action('woocommerce_product_additional_information',      [__CLASS__, 'show_gpsr']);
        add_action('wp_head',                                    [__CLASS__, 'inline_css']);
    }

    public static function show_archive(): void {
        global $product;
        if (!$product) return;
        $html = self::build_info($product->get_id());
        if ($html) echo $html;
    }

    public static function show_single(): void {
        global $product;
        if (!$product) return;
        $html = self::build_info($product->get_id());
        if ($html) echo $html;
    }

    // ── Core: build pricing info HTML ─────────────────────────────────────────

    private static function build_info(int $id): string {
        $jm      = strtoupper(trim(get_post_meta($id, '_unimet_jm', true)));
        $min_qty = (int) get_post_meta($id, '_unimet_min_qty', true);
        $opak    = (int) get_post_meta($id, '_unimet_qty_per_pack', true);
        $netto   = (float) get_post_meta($id, '_unimet_price_netto', true);

        if (!$jm || !$netto) return '';

        $sell = Kunimet_Pricing::sell_price($netto);
        $parts = [];

        if (in_array($jm, self::JM_BULK_STO, true)) {
            // STO = 100 SZT — show price per single piece
            $per_szt = $sell / 100;
            $parts[] = '<span class="uni-unit">' . wc_price($per_szt) . ' / SZT</span>'
                     . '<span class="uni-pack">(cena za 100 SZT)</span>';

        } elseif (in_array($jm, self::JM_PACKAGE, true) && $opak > 1) {
            // OP/OPK — price is per package; show price per single piece inside
            $per_szt = $sell / $opak;
            $parts[] = '<span class="uni-unit">' . wc_price($per_szt) . ' / SZT</span>'
                     . '<span class="uni-pack">(opakowanie: ' . $opak . ' SZT)</span>';

        } elseif (in_array($jm, self::JM_SINGLE, true)) {
            // SZT / KG / MB etc. — price IS per unit, no conversion needed
            // Only show minimum if explicitly set via MIN_ZAM > 1
            if ($min_qty > 1) {
                $parts[] = '<span class="uni-min">Minimum: ' . $min_qty . ' ' . esc_html($jm) . '</span>';
            }
        }

        if (empty($parts)) return '';

        return '<div class="unimet-unit-price">' . implode(' ', $parts) . '</div>';
    }

    // ── Quantity: min & step ──────────────────────────────────────────────────

    public static function quantity_args(array $args, WC_Product $product): array {
        $id      = $product->get_id();
        $min_qty = (int) get_post_meta($id, '_unimet_min_qty', true) ?: 1;

        // MIN_ZAM = minimum AND step — you always buy in whole package multiples
        $args['min_value']   = max(1, $min_qty);
        $args['step']        = max(1, $min_qty);
        $args['input_value'] = max(1, $min_qty);

        return $args;
    }

    public static function validate_qty(bool $passed, int $product_id, int $qty): bool {
        $min = (int) get_post_meta($product_id, '_unimet_min_qty', true) ?: 1;

        if ($qty < $min) {
            wc_add_notice(sprintf('Minimalna ilość zamówienia: %d.', $min), 'error');
            return false;
        }
        if ($min > 1 && $qty % $min !== 0) {
            wc_add_notice(sprintf('Ilość musi być wielokrotnością %d (rozmiar opakowania).', $min), 'error');
            return false;
        }

        return $passed;
    }

    // ── External images from Unimet CDN ──────────────────────────────────────

    /** Used by archive/loop */
    public static function external_image(string $html, WC_Product $product, string $size, array $attr, bool $placeholder): string {
        if ($html && strpos($html, 'placeholder') === false) return $html;
        return self::build_img($product->get_id(), $attr) ?: $html;
    }

    /** Used by single product page — WC renders this separately from the loop */
    public static function single_product_image(string $html, $attachment_id): string {
        $attachment_id = (int) $attachment_id;
        // Only replace when there's no real image (attachment_id = 0 or placeholder)
        if ($attachment_id && strpos($html, 'placeholder') === false && strpos($html, 'Awaiting') === false) {
            return $html;
        }
        global $product;
        if (!$product) return $html;
        $images = get_post_meta($product->get_id(), '_unimet_images', true);
        if (empty($images) || !is_array($images)) return $html;

        $url = esc_url($images[0]);
        $alt = esc_attr($product->get_name());
        return '<div class="woocommerce-product-gallery__image">'
             . "<img src=\"{$url}\" alt=\"{$alt}\" class=\"wp-post-image\" loading=\"lazy\">"
             . '</div>';
    }

    /** Fallback for post_thumbnail_html */
    public static function external_thumbnail(string $html, int $post_id, int $thumbnail_id, string $size, array $attr): string {
        if ($html && strpos($html, 'placeholder') === false) return $html;
        $product = wc_get_product($post_id);
        if (!$product) return $html;
        return self::build_img($post_id, $attr) ?: $html;
    }

    private static function build_img(int $id, array $attr): string {
        $images = get_post_meta($id, '_unimet_images', true);
        if (empty($images) || !is_array($images)) return '';
        $url   = esc_url($images[0]);
        $title = esc_attr(get_the_title($id));
        $class = esc_attr($attr['class'] ?? 'wp-post-image');
        return "<img src=\"{$url}\" alt=\"{$title}\" class=\"{$class}\" loading=\"lazy\">";
    }

    // ── GPSR ─────────────────────────────────────────────────────────────────

    public static function show_gpsr(WC_Product $product): void {
        $id     = $product->get_id();
        $fields = [
            '_gpsr_producent'   => 'Producent',
            '_gpsr_adres'       => 'Adres',
            '_gpsr_kraj'        => 'Kraj',
            '_gpsr_kontakt'     => 'Kontakt',
            '_gpsr_ostrzezenia' => 'Informacje bezpieczeństwa',
        ];

        $rows = '';
        foreach ($fields as $key => $label) {
            $val = get_post_meta($id, $key, true);
            if (!$val) continue;
            $rows .= '<tr><th>' . esc_html($label) . '</th><td>' . esc_html($val) . '</td></tr>';
        }
        if (!$rows) return;

        echo '<div class="unimet-gpsr"><h3>Informacje GPSR (bezpieczeństwo produktu)</h3>'
           . '<table>' . $rows . '</table></div>';
    }

    // ── CSS ───────────────────────────────────────────────────────────────────

    public static function inline_css(): void {
        if (!is_woocommerce() && !is_shop() && !is_product()) return;
        echo '<style>
        .unimet-unit-price{font-size:13px;color:#555;margin:4px 0 8px;line-height:1.5;}
        .uni-unit{font-weight:600;color:#333;}
        .uni-pack{margin-left:6px;color:#777;}
        .uni-min{color:#e05a00;font-weight:500;}
        .unimet-gpsr{margin-top:24px;border-top:1px solid #eee;padding-top:16px;}
        .unimet-gpsr h3{font-size:14px;margin-bottom:8px;}
        .unimet-gpsr table{width:100%;border-collapse:collapse;font-size:13px;}
        .unimet-gpsr th{text-align:left;padding:4px 8px 4px 0;color:#555;width:140px;vertical-align:top;}
        .unimet-gpsr td{padding:4px 0;color:#333;}
        </style>';
    }
}
