<?php
defined('ABSPATH') || exit;

class Kunimet_Admin {

    public static function init(): void {
        add_action('admin_menu', [__CLASS__, 'add_menu']);
        add_action('admin_post_kunimet_save_settings', [__CLASS__, 'save_settings']);
        add_action('admin_post_kunimet_run_sync',      [__CLASS__, 'run_sync_manually']);
        add_action('admin_enqueue_scripts',            [__CLASS__, 'enqueue']);
    }

    public static function add_menu(): void {
        add_menu_page(
            'Unimet Sync',
            'Unimet Sync',
            'manage_woocommerce',
            'kunimet-sync',
            [__CLASS__, 'render_dashboard'],
            'dashicons-update',
            58
        );
        add_submenu_page('kunimet-sync', 'Ustawienia', 'Ustawienia', 'manage_woocommerce', 'kunimet-settings', [__CLASS__, 'render_settings']);
        add_submenu_page('kunimet-sync', 'Logi',       'Logi',       'manage_woocommerce', 'kunimet-logs',     [__CLASS__, 'render_logs']);
    }

    public static function enqueue(string $hook): void {
        if (strpos($hook, 'kunimet') === false) return;
        wp_add_inline_style('wp-admin', '
            .kunimet-status-ok   { color: #00a32a; font-weight: bold; }
            .kunimet-status-warn { color: #d63638; font-weight: bold; }
            .kunimet-log-box     { background:#1d2327; color:#a7aaad; font-family:monospace; font-size:12px; padding:12px; height:400px; overflow-y:scroll; white-space:pre-wrap; border-radius:4px; }
            .kunimet-grid        { display:grid; grid-template-columns:repeat(4,1fr); gap:16px; margin-bottom:24px; }
            .kunimet-card        { background:#fff; border:1px solid #c3c4c7; border-radius:4px; padding:16px; }
            .kunimet-card h3     { margin:0 0 8px; font-size:13px; color:#646970; }
            .kunimet-card .val   { font-size:22px; font-weight:700; color:#1d2327; }
            .kunimet-card .sub   { font-size:11px; color:#646970; margin-top:4px; }
        ');
    }

    public static function render_dashboard(): void {
        $syncs = get_option('kunimet_last_syncs', []);
        $now   = time();
        $checks = [
            'products' => ['label' => 'Produkty',      'max' => 26 * 3600],
            'stock'    => ['label' => 'Stany',          'max' => 20 * 60],
            'shipping' => ['label' => 'Typy wysyłki',  'max' => 70 * 60],
            'gpsr'     => ['label' => 'GPSR',           'max' => 26 * 3600],
        ];

        $product_count = wp_count_posts('product');
        ?>
        <div class="wrap">
        <h1>Unimet Sync — Dashboard</h1>

        <div class="kunimet-grid">
        <?php foreach ($checks as $key => $cfg):
            $last    = $syncs[$key] ?? 0;
            $age     = $last ? ($now - $last) : PHP_INT_MAX;
            $healthy = $age < $cfg['max'];
            $cls     = $healthy ? 'kunimet-status-ok' : 'kunimet-status-warn';
            $label_v = $healthy ? '✅ OK' : '❌ BŁĄD';
            $sub     = $last ? human_time_diff($last, $now) . ' temu' : 'Nigdy';
        ?>
        <div class="kunimet-card">
            <h3><?= esc_html($cfg['label']) ?></h3>
            <div class="val <?= $cls ?>"><?= $label_v ?></div>
            <div class="sub"><?= esc_html($sub) ?></div>
        </div>
        <?php endforeach; ?>
        </div>

        <div class="kunimet-grid">
            <div class="kunimet-card">
                <h3>Produktów w sklepie</h3>
                <div class="val"><?= (int)($product_count->publish ?? 0) ?></div>
                <div class="sub">opublikowanych</div>
            </div>
            <div class="kunimet-card">
                <h3>Ukrytych (brak stanu)</h3>
                <div class="val"><?= (int)($product_count->private ?? 0) ?></div>
                <div class="sub">stan = 0</div>
            </div>
        </div>

        <h2>Uruchom sync ręcznie</h2>
        <form method="post" action="<?= admin_url('admin-post.php') ?>">
            <?php wp_nonce_field('kunimet_run_sync'); ?>
            <input type="hidden" name="action" value="kunimet_run_sync">
            <select name="sync_task">
                <option value="stock">Sync stanów (szybki)</option>
                <option value="shipping">Sync wysyłki</option>
                <option value="gpsr">Sync GPSR</option>
                <option value="products_incremental">Sync produktów (incremental)</option>
                <option value="products_full">Sync produktów PEŁNY (długi!)</option>
            </select>
            <?php submit_button('Uruchom', 'secondary', 'submit', false) ?>
        </form>
        <?php if ($msg = get_transient('kunimet_admin_msg')): delete_transient('kunimet_admin_msg'); ?>
        <div class="notice notice-success"><p><?= esc_html($msg) ?></p></div>
        <?php endif; ?>
        </div>
        <?php
    }

    public static function render_settings(): void {
        $tiers    = get_option('kunimet_markup_tiers', Kunimet_Pricing::default_tiers());
        $excluded = get_option('kunimet_excluded_cats', []);
        $email    = get_option('kunimet_alert_email', get_option('admin_email'));
        ?>
        <div class="wrap">
        <h1>Unimet Sync — Ustawienia</h1>
        <form method="post" action="<?= admin_url('admin-post.php') ?>">
            <?php wp_nonce_field('kunimet_save_settings'); ?>
            <input type="hidden" name="action" value="kunimet_save_settings">

            <h2>Progi narzutu cenowego</h2>
            <table class="widefat" style="max-width:500px">
                <thead><tr><th>Cena netto Unimet (do)</th><th>Narzut %</th></tr></thead>
                <tbody>
                <?php foreach ($tiers as $i => $tier): ?>
                <tr>
                    <td>
                        <?php if ($tier['max']): ?>
                        <input type="number" name="tier_max[<?= $i ?>]" value="<?= esc_attr($tier['max']) ?>" style="width:80px"> zł
                        <?php else: ?>
                        <em>powyżej wszystkich</em>
                        <input type="hidden" name="tier_max[<?= $i ?>]" value="">
                        <?php endif; ?>
                    </td>
                    <td><input type="number" name="tier_markup[<?= $i ?>]" value="<?= esc_attr($tier['markup']) ?>" style="width:60px"> %</td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>

            <h2>Wykluczone kategorie Unimet</h2>
            <textarea name="excluded_cats" rows="6" style="width:400px"><?= esc_textarea(implode("\n", $excluded)) ?></textarea>
            <p class="description">Jedna kategoria na linię. Produkty z tych kategorii nie będą importowane.</p>

            <h2>Email alertów</h2>
            <input type="email" name="alert_email" value="<?= esc_attr($email) ?>" style="width:300px">
            <p class="description">Na ten adres będą wysyłane alerty gdy sync się nie powiedzie.</p>

            <?php submit_button('Zapisz ustawienia') ?>
        </form>
        </div>
        <?php
    }

    public static function render_logs(): void {
        $files   = Kunimet_Logger::get_log_files();
        $current = sanitize_file_name($_GET['log'] ?? ($files[0] ?? ''));
        $lines   = $current ? Kunimet_Logger::get_recent(pathinfo($current, PATHINFO_FILENAME), 200) : [];
        ?>
        <div class="wrap">
        <h1>Unimet Sync — Logi</h1>
        <p>
        <?php foreach ($files as $f): ?>
        <a href="?page=kunimet-logs&log=<?= urlencode($f) ?>" class="button <?= $f === $current ? 'button-primary' : '' ?>"><?= esc_html($f) ?></a>
        <?php endforeach; ?>
        </p>
        <div class="kunimet-log-box"><?= esc_html(implode("\n", $lines)) ?></div>
        </div>
        <?php
    }

    public static function save_settings(): void {
        check_admin_referer('kunimet_save_settings');
        if (!current_user_can('manage_woocommerce')) wp_die('Brak uprawnień');

        $max_arr    = $_POST['tier_max']    ?? [];
        $markup_arr = $_POST['tier_markup'] ?? [];
        $tiers = [];
        foreach ($max_arr as $i => $max) {
            $tiers[] = [
                'max'    => $max !== '' ? (int)$max : null,
                'markup' => (int)($markup_arr[$i] ?? 25),
            ];
        }
        update_option('kunimet_markup_tiers', $tiers);

        $excluded = array_filter(array_map('trim', explode("\n", $_POST['excluded_cats'] ?? '')));
        update_option('kunimet_excluded_cats', $excluded);

        if (!empty($_POST['alert_email'])) {
            update_option('kunimet_alert_email', sanitize_email($_POST['alert_email']));
        }

        wp_redirect(admin_url('admin.php?page=kunimet-settings&saved=1'));
        exit;
    }

    public static function run_sync_manually(): void {
        check_admin_referer('kunimet_run_sync');
        if (!current_user_can('manage_woocommerce')) wp_die('Brak uprawnień');

        $task = $_POST['sync_task'] ?? '';
        switch ($task) {
            case 'stock':                  Kunimet_Sync_Manager::run_stock(); $msg = 'Sync stanów zakończony'; break;
            case 'shipping':               Kunimet_Sync_Manager::run_shipping(); $msg = 'Sync wysyłki zakończony'; break;
            case 'gpsr':                   Kunimet_Sync_Manager::run_gpsr(); $msg = 'Sync GPSR zakończony'; break;
            case 'products_incremental':   Kunimet_Sync_Manager::run_products_incremental(); $msg = 'Incremental sync zakończony'; break;
            case 'products_full':          Kunimet_Sync_Manager::run_products_full(); $msg = 'Pełny sync zakończony'; break;
            default:                       $msg = 'Nieznane zadanie';
        }

        set_transient('kunimet_admin_msg', $msg, 60);
        wp_redirect(admin_url('admin.php?page=kunimet-sync'));
        exit;
    }
}
