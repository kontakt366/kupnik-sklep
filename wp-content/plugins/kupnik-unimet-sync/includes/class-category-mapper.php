<?php
defined('ABSPATH') || exit;

class Kunimet_Category_Mapper {

    private static array $cache = [];

    /**
     * Get or create WC category for Unimet path.
     * Unimet uses > separator: "OSPRZĘT DO ELEKTRONARZĘDZI>WIERTŁA>ZESTAWY WIERTEŁ"
     */
    public static function get_or_create_by_separator(string $path, string $sep = '>'): int {
        if (isset(self::$cache[$path])) return self::$cache[$path];

        $parts     = array_filter(array_map('trim', explode($sep, $path)));
        $parent_id = 0;
        $term_id   = 0;

        foreach ($parts as $name) {
            $term_id   = self::find_or_create($name, $parent_id);
            $parent_id = $term_id;
        }

        self::$cache[$path] = $term_id;
        return $term_id;
    }

    // Legacy method kept for compatibility
    public static function get_or_create(string $path): int {
        return self::get_or_create_by_separator($path, '>');
    }

    private static function find_or_create(string $name, int $parent): int {
        $slug = sanitize_title($name);

        // Search by name + parent
        $terms = get_terms([
            'taxonomy'   => 'product_cat',
            'hide_empty' => false,
            'name'       => $name,
            'parent'     => $parent,
        ]);
        if (!is_wp_error($terms) && !empty($terms)) {
            return (int)$terms[0]->term_id;
        }

        // Create
        $result = wp_insert_term($name, 'product_cat', [
            'parent' => $parent,
            'slug'   => $slug . ($parent ? '-' . $parent : ''),
        ]);

        if (is_wp_error($result)) {
            // Might already exist under different parent
            $existing = get_term_by('slug', $slug, 'product_cat');
            return $existing ? (int)$existing->term_id : 0;
        }

        return (int)$result['term_id'];
    }

    public static function is_excluded(string $path): bool {
        $excluded = get_option('kunimet_excluded_cats', []);
        foreach ($excluded as $ex) {
            if (mb_stripos($path, $ex) !== false) return true;
        }
        return false;
    }
}
