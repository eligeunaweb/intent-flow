<?php
if (!defined('ABSPATH')) {
    exit;
}

final class IF_Settings {
    const OPTION_KEY = 'inteflow_settings';

    public static function init() : void {
        add_action('admin_init', [__CLASS__, 'register']);
    }

    public static function register() : void {
        register_setting(
            'if_settings_group',
            self::OPTION_KEY,
            [
                'type'              => 'array',
                'sanitize_callback' => [__CLASS__, 'sanitize'],
                'default'           => self::defaults(),
            ]
        );
        // Sin UI.
    }

    public static function defaults() : array {
        return [
            'safe_mode' => true,
            'dry_run'   => true,
            'enabled'   => true,
        ];
    }

    public static function ensure_defaults() : void {
        $existing = get_option(self::OPTION_KEY, null);
        if (!is_array($existing)) {
            update_option(self::OPTION_KEY, self::defaults(), false);
            return;
        }
        $merged = array_merge(self::defaults(), $existing);
        update_option(self::OPTION_KEY, $merged, false);
    }

    public static function get() : array {
        $opt = get_option(self::OPTION_KEY, self::defaults());
        if (!is_array($opt)) {
            return self::defaults();
        }
        return array_merge(self::defaults(), $opt);
    }

    public static function sanitize($value) : array {
        $value = is_array($value) ? $value : [];

        return [
            'safe_mode' => !empty($value['safe_mode']),
            'dry_run'   => !empty($value['dry_run']),
            'enabled'   => array_key_exists('enabled', $value) ? !empty($value['enabled']) : true,
        ];
    }
}
