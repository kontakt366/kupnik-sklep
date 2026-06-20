<?php
defined('ABSPATH') || exit;

class Kunimet_Pricing {

    /**
     * Calculate sell price netto from Unimet netto price.
     * Tiers stored in option 'kunimet_markup_tiers'.
     */
    public static function sell_price(float $unimet_netto): float {
        $tiers  = get_option('kunimet_markup_tiers', self::default_tiers());
        $markup = 25; // fallback

        foreach ($tiers as $tier) {
            if ($tier['max'] === null || $unimet_netto < $tier['max']) {
                $markup = (float) $tier['markup'];
                break;
            }
        }

        $sell = $unimet_netto * (1 + $markup / 100);
        // Round to .99 for prices > 10 zł, else 2 decimal places
        if ($sell >= 10) {
            return floor($sell) + 0.99;
        }
        return round($sell, 2);
    }

    /**
     * WooCommerce stores prices WITHOUT tax by default (tax-exclusive mode).
     * We store sell price netto; WC adds VAT on display.
     */
    public static function default_tiers(): array {
        return [
            ['max' => 10,   'markup' => 80],
            ['max' => 50,   'markup' => 60],
            ['max' => 100,  'markup' => 40],
            ['max' => 200,  'markup' => 30],
            ['max' => null, 'markup' => 25],
        ];
    }
}
