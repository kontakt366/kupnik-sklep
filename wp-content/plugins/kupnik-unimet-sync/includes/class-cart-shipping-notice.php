<?php
defined('ABSPATH') || exit;

class Kunimet_Cart_Shipping_Notice {

    public static function init(): void {
        add_action('woocommerce_before_cart', [__CLASS__, 'check_mixed_shipping']);
        add_action('woocommerce_checkout_before_customer_details', [__CLASS__, 'check_mixed_shipping']);
    }

    public static function check_mixed_shipping(): void {
        if (!WC()->cart) return;

        $types = [];
        foreach (WC()->cart->get_cart() as $item) {
            $product_id = $item['product_id'];
            $type = get_post_meta($product_id, '_unimet_courier_type', true);
            if ($type) $types[$type] = true;
        }

        if (count($types) <= 1) return;

        $paczkomat_types = Kunimet_Shipping_Sync::PACZKOMAT;
        $has_paczkomat   = !empty(array_intersect(array_keys($types), $paczkomat_types));
        $has_kurier      = isset($types['3. KURIER STD']) || isset($types['4. KURIER NST']);
        $has_paleta      = isset($types['5. PÓŁ PALETY']) || isset($types['6. PALETA STD']) || isset($types['7. PALETA NST']);
        $has_dluzyce     = isset($types['8. DŁUŻYCA']);

        $notices = [];

        if ($has_paczkomat && ($has_kurier || $has_paleta || $has_dluzyce)) {
            $notices[] = 'Część produktów <strong>nie może być dostarczona do paczkomatu</strong> — dla tych produktów zostanie naliczona oddzielna opłata za dostawę kurierem.';
        }

        if ($has_paleta) {
            $notices[] = 'Zamówienie zawiera <strong>towary paletowe</strong> — wymagają oddzielnej wysyłki i będą fakturowane osobno.';
        }

        if ($has_dluzyce) {
            $notices[] = 'Zamówienie zawiera <strong>towary długowymiarowe (dłużyce)</strong> — wymagają specjalnej wysyłki.';
        }

        if (count($types) > 1 && empty($notices)) {
            $notices[] = 'Produkty w koszyku mają <strong>różne typy wysyłki</strong> — zostanie naliczona opłata za każdy rodzaj dostawy.';
        }

        foreach ($notices as $notice) {
            wc_add_notice(
                '⚠️ Uwaga: ' . $notice . ' <a href="/kontakt/">Masz pytania? Skontaktuj się z nami.</a>',
                'notice'
            );
        }
    }
}
