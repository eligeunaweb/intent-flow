<?php
if (!defined('ABSPATH')) { exit; }

final class IF_AdminPage {

    const SLUG_DASHBOARD   = 'mmi-dashboard';
    const SLUG_FLOWS       = 'mmi-flows';
    const SLUG_EVENTS      = 'mmi-events';
    const SLUG_CONNECTIONS = 'mmi-connections';
    const SLUG_INTEGRATIONS= 'mmi-integrations';
    const SLUG_LOGS        = 'mmi-logs';
    const SLUG_SCHEDULER   = 'mmi-scheduler';
    const SLUG_SETTINGS    = 'mmi-settings';
    const SLUG_HELP        = 'mmi-help';

    public static function init() : void {
        add_action('admin_menu', [__CLASS__, 'register_menu']);
        add_action('admin_enqueue_scripts', [__CLASS__, 'enqueue_assets']);
    }

    public static function register_menu() : void {
        add_menu_page(
            'Intent Flow',
            'Intent Flow',
            'manage_options',
            self::SLUG_DASHBOARD,
            [__CLASS__, 'render_page'],
            'dashicons-controls-repeat',
            65
        );

        add_submenu_page(self::SLUG_DASHBOARD, 'Dashboard',    'Dashboard',    'manage_options', self::SLUG_DASHBOARD,    [__CLASS__, 'render_page']);
        add_submenu_page(self::SLUG_DASHBOARD, 'Flows',        'Flows',        'manage_options', self::SLUG_FLOWS,        [__CLASS__, 'render_page']);
        add_submenu_page(self::SLUG_DASHBOARD, 'Events',       'Events',       'manage_options', self::SLUG_EVENTS,       [__CLASS__, 'render_page']);
        add_submenu_page(self::SLUG_DASHBOARD, 'Connections',  'Connections',  'manage_options', self::SLUG_CONNECTIONS,  [__CLASS__, 'render_page']);
        add_submenu_page(self::SLUG_DASHBOARD, 'Integrations', 'Integrations', 'manage_options', self::SLUG_INTEGRATIONS, [__CLASS__, 'render_page']);
        add_submenu_page(self::SLUG_DASHBOARD, 'Logs',         'Logs',         'manage_options', self::SLUG_LOGS,         [__CLASS__, 'render_page']);
        add_submenu_page(self::SLUG_DASHBOARD, 'Scheduler',    'Scheduler',    'manage_options', self::SLUG_SCHEDULER,    [__CLASS__, 'render_page']);
        add_submenu_page(self::SLUG_DASHBOARD, 'Settings',     'Settings',     'manage_options', self::SLUG_SETTINGS,     [__CLASS__, 'render_page']);
        add_submenu_page(self::SLUG_DASHBOARD, '❓ Help',        '❓ Help',        'manage_options', self::SLUG_HELP,         [__CLASS__, 'render_page']);
    }

    private static function page_key() : string {
        $raw  = isset($_GET['page']) ? sanitize_key(wp_unslash((string) $_GET['page'])) : self::SLUG_DASHBOARD;
        $page = sanitize_key($raw);
        $page = str_replace('_', '-', $page);

        $map = [
            self::SLUG_DASHBOARD    => 'dashboard',
            self::SLUG_FLOWS        => 'flows',
            self::SLUG_EVENTS       => 'events',
            self::SLUG_CONNECTIONS  => 'connections',
            self::SLUG_INTEGRATIONS => 'integrations',
            self::SLUG_LOGS         => 'logs',
            self::SLUG_SCHEDULER    => 'scheduler',
            self::SLUG_SETTINGS     => 'settings',
            self::SLUG_HELP         => 'help',
        ];

        if (isset($map[$page])) return $map[$page];

        if (strpos($page, 'help')        !== false) return 'help';
        if (strpos($page, 'event')       !== false) return 'events';
        if (strpos($page, 'flow')        !== false) return 'flows';
        if (strpos($page, 'connection')  !== false) return 'connections';
        if (strpos($page, 'integration') !== false) return 'integrations';
        if (strpos($page, 'log')         !== false) return 'logs';
        if (strpos($page, 'sched')       !== false) return 'scheduler';
        if (strpos($page, 'setting')     !== false) return 'settings';

        return 'dashboard';
    }

    public static function render_page() : void {
        if (!current_user_can('manage_options')) {
            wp_die('No autorizado');
        }

        $key = self::page_key();

        echo '<div class="wrap">';
        echo '<h1>Intent Flow</h1>';
        echo '<div id="mmi-loading" class="mmi-card" aria-label="Cargando UI"><p style="margin:0">Cargando Intent Flow…</p></div>';
        echo '<div id="mmi-dashboard" class="mmi-dashboard" style="display:none;" data-mmi-page="' . esc_attr($key) . '" data-mmi-slug="' . esc_attr(isset($_GET['page']) ? sanitize_key($_GET['page']) : self::SLUG_DASHBOARD) . '"></div>';

        echo '<div id="mmi-scheduler-ui" class="mmi-card" aria-label="Scheduler (UI)" style="' . ($key === 'scheduler' ? '' : 'display:none;') . '">';
        echo '<h2 style="margin-top:0">Scheduler</h2>';
        echo '<div id="mmi-scheduler-ui-body"></div>';
        echo '</div>';

        echo '</div>';
    }

    public static function enqueue_assets(string $hook) : void {
        if (strpos($hook, 'mmi-') === false) {
            if (strpos($hook, 'toplevel_page_' . self::SLUG_DASHBOARD) === false) return;
        }
        if (!is_user_logged_in() || !current_user_can('manage_options')) return;

        $css_path = IF_PLUGIN_DIR . 'assets/css/admin-dashboard.css';
        $css_ver  = file_exists($css_path) ? filemtime($css_path) : IF_VERSION;
        wp_enqueue_style('mmi-admin-dashboard', IF_PLUGIN_URL . 'assets/css/admin-dashboard.css', [], $css_ver);

        $js_path = IF_PLUGIN_DIR . 'assets/js/admin-dashboard.js';
        $js_ver  = file_exists($js_path) ? filemtime($js_path) : IF_VERSION;
        wp_register_script('mmi-admin-dashboard', IF_PLUGIN_URL . 'assets/js/admin-dashboard.js', ['mmi-bootstrap'], $js_ver, true);
        wp_enqueue_script('mmi-admin-dashboard');
    }
}
