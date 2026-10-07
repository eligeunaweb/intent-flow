<?php
if (!defined('ABSPATH')) {
    exit;
}

final class IF_Enqueue {
    const HANDLE_BOOTSTRAP = 'mmi-bootstrap';

    public static function init() : void {
        add_action('wp_enqueue_scripts', [__CLASS__, 'enqueue_frontend']);
        add_action('admin_enqueue_scripts', [__CLASS__, 'enqueue_admin']);
        add_filter('script_loader_tag', [__CLASS__, 'filter_script_loader_tag'], 10, 3);
    }

    private static function is_admin_allowed() : bool {
        return is_user_logged_in() && current_user_can('manage_options');
    }

    public static function enqueue_frontend() : void {
        if (!self::is_admin_allowed()) return;
        self::enqueue_common();
    }

    public static function enqueue_admin() : void {
        if (!self::is_admin_allowed()) return;
        self::enqueue_common();
    }

    private static function enqueue_common() : void {
        $settings = IF_Settings::get();

        $bootstrap_path = IF_PLUGIN_DIR . 'assets/js/bootstrap.js';
        $ver = file_exists($bootstrap_path) ? filemtime($bootstrap_path) : IF_VERSION;

        wp_register_script(
            self::HANDLE_BOOTSTRAP,
            IF_PLUGIN_URL . 'assets/js/bootstrap.js',
            [],
            $ver,
            true
        );

        wp_localize_script(self::HANDLE_BOOTSTRAP, 'IF_BOOT', [
            'version'   => IF_VERSION,
            'safe_mode' => !empty($settings['safe_mode']),
            'dry_run'   => !empty($settings['dry_run']),
            'enabled'   => !empty($settings['enabled']),
            'cap'       => 'manage_options',
            'nonce'     => wp_create_nonce('inteflow_intent'),
            'homeUrl'   => home_url('/'),
            'ajaxUrl'   => admin_url('admin-ajax.php'),
        ]);

        wp_enqueue_script(self::HANDLE_BOOTSTRAP);

        // Localizar strings de i18n al JS
        if (class_exists('IntentFlow_I18n')) {
            IntentFlow_I18n::localize();
        }
    }

    public static function filter_script_loader_tag(string $tag, string $handle, string $src) : string {
        if ($handle === self::HANDLE_BOOTSTRAP) {
            return str_replace( '<script ', '<script type="module" ', $tag );
        }
        return $tag;
    }
}
