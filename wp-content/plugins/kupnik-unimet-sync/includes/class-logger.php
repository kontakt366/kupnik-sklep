<?php
defined('ABSPATH') || exit;

class Kunimet_Logger {

    private static function log_file(string $type): string {
        return KUNIMET_LOG_DIR . $type . '-' . date('Y-m') . '.log';
    }

    public static function info(string $type, string $msg): void {
        self::write($type, 'INFO', $msg);
    }

    public static function error(string $type, string $msg): void {
        self::write($type, 'ERROR', $msg);
        self::maybe_alert($type, $msg);
    }

    public static function success(string $type, string $msg): void {
        self::write($type, 'OK', $msg);
        // Update last sync timestamp
        $log = get_option('kunimet_last_syncs', []);
        $log[$type] = time();
        update_option('kunimet_last_syncs', $log);
    }

    private static function write(string $type, string $level, string $msg): void {
        $line = sprintf("[%s] [%s] %s\n", date('Y-m-d H:i:s'), $level, $msg);
        file_put_contents(self::log_file($type), $line, FILE_APPEND | LOCK_EX);
    }

    private static function maybe_alert(string $type, string $msg): void {
        $email = get_option('kunimet_alert_email');
        if (!$email) return;

        // Throttle: max 1 alert per type per hour
        $key      = 'kunimet_alert_sent_' . $type;
        $last_sent = get_transient($key);
        if ($last_sent) return;

        set_transient($key, 1, HOUR_IN_SECONDS);
        wp_mail(
            $email,
            '[kupnik.pl] Błąd synca Unimet: ' . $type,
            "Wystąpił błąd podczas synca [{$type}]:\n\n{$msg}\n\nSprawdź logi: wp-content/plugins/kupnik-unimet-sync/logs/"
        );
    }

    public static function get_recent(string $type, int $lines = 100): array {
        $file = self::log_file($type);
        if (!file_exists($file)) return [];
        $all = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        return array_slice($all, -$lines);
    }

    public static function get_log_files(): array {
        $files = glob(KUNIMET_LOG_DIR . '*.log') ?: [];
        return array_map('basename', $files);
    }
}
