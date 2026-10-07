<?php
/**
 * Plugin Name: Intent Flow — Workflow Automation
 * Description: Automate WordPress without limits. No credits, no subscription, no external servers. Triggers, actions, scheduler and WCAG-accessible UI included free.
 * Version: 0.9.4.2.33
 * Author: Álvaro Martínez
 * Author URI: https://adaptatuweb.com
 * License: GPLv2 or later
 * Text Domain: intent-flow
 */

if (!defined('ABSPATH')) {
    exit;
}

// Load textdomain
add_action('init', function() {
});

// Limpiar al desinstalar (no al desactivar)
// Ver uninstall.php

add_action('plugins_loaded', function() {
    // phpcs:ignore PluginCheck.CodeAnalysis.DiscouragedFunctions.load_plugin_textdomainFound
	load_plugin_textdomain(
        'intent-flow',
        false,
        dirname(plugin_basename(__FILE__)) . '/languages/'
    );
});

define('IF_VERSION','0.9.4.2.33');
define('IF_PLUGIN_FILE', __FILE__);
define('IF_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('IF_PLUGIN_URL', plugin_dir_url(__FILE__));

require_once IF_PLUGIN_DIR . 'includes/I18n.php';
require_once IF_PLUGIN_DIR . 'includes/Settings.php';
require_once IF_PLUGIN_DIR . 'includes/MacroIntent_Settings.php';
require_once IF_PLUGIN_DIR . 'includes/Enqueue.php';
require_once IF_PLUGIN_DIR . 'includes/AuditLog.php';
require_once IF_PLUGIN_DIR . 'includes/PlanState.php';
require_once IF_PLUGIN_DIR . 'includes/Redact.php';
require_once IF_PLUGIN_DIR . 'includes/Allowlist.php';
require_once IF_PLUGIN_DIR . 'includes/PluginSafelist.php';
require_once IF_PLUGIN_DIR . 'includes/Scopes.php';
require_once IF_PLUGIN_DIR . 'includes/Target.php';
require_once IF_PLUGIN_DIR . 'includes/ExecutionLog.php';
require_once IF_PLUGIN_DIR . 'includes/Runner.php';
require_once IF_PLUGIN_DIR . 'includes/AppsRunner.php';
require_once IF_PLUGIN_DIR . 'includes/Events.php';
require_once IF_PLUGIN_DIR . 'includes/FlowTriggers.php';
require_once IF_PLUGIN_DIR . 'includes/Webhooks.php';
require_once IF_PLUGIN_DIR . 'includes/Integrations.php';
require_once IF_PLUGIN_DIR . 'includes/Modules.php';
require_once IF_PLUGIN_DIR . 'includes/actions/ActionRegistry.php';
require_once IF_PLUGIN_DIR . 'includes/actions/ActionExecutor.php';

require_once IF_PLUGIN_DIR . 'includes/AdminPage.php';
require_once IF_PLUGIN_DIR . 'includes/Scheduler.php';
require_once IF_PLUGIN_DIR . 'includes/SchedulerRuns.php';

add_action('plugins_loaded', function () {



});


$ajax_file = IF_PLUGIN_DIR . 'includes/Ajax.php';
if (file_exists($ajax_file)) {
    require_once $ajax_file;
}

final class IF_Plugin {
    private static $instance = null;

    public static function instance() : self {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action('init', [$this, 'init']);
        register_activation_hook(IF_PLUGIN_FILE, [$this, 'on_activate']);
    }

    public function init() : void {
        IF_Settings::init();
        IF_Enqueue::init();

        if (class_exists('IF_AdminPage')) {
            IF_AdminPage::init();
        }

        if (class_exists('IF_Ajax')) {
            IF_Ajax::init();
        }

        if (class_exists('IF_Scheduler')) {
            IF_Scheduler::init();
        }

        if (class_exists('IF_Events')) {
            IF_Events::init();
        }

        if (class_exists('IF_Webhooks')) {
            IF_Webhooks::init();
        }
    }

    public function on_activate() : void {
        IF_Settings::ensure_defaults();
        if (class_exists('IF_Integrations')) {
            IF_Integrations::ensure_defaults();
        }
        if (class_exists('IF_Modules')) {
            IF_Modules::ensure_defaults();
        }
    }
}

IF_Plugin::instance();


require_once IF_PLUGIN_DIR . 'includes/flows/FlowRegistry.php';
require_once IF_PLUGIN_DIR . 'includes/flows/FlowExecutor.php';

require_once IF_PLUGIN_DIR . 'includes/Connections.php';

require_once IF_PLUGIN_DIR . 'includes/Templates.php';

require_once IF_PLUGIN_DIR . 'includes/ConnectionResolver.php';


// Module Loader (fail-safe)
add_action('plugins_loaded', function(){
    if (class_exists('IF_Modules')) {
        IF_Modules::load_enabled();
    }
}, 3);
