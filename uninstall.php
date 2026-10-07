<?php
if (!defined('WP_UNINSTALL_PLUGIN')) { exit; }

// Limpiar licencia y opciones al desinstalar completamente
delete_option('inteflow_license_key');
delete_option('inteflow_license_last_good');
delete_option('inteflow_license_last_check');
delete_option('inteflow_settings');
delete_option('mmi_event_rules_v1');
delete_transient('inteflow_license_cache');
delete_transient('inteflow_license_cache_sig');
delete_transient('inteflow_pending_update');
wp_clear_scheduled_hook('inteflow_daily_license_check');
wp_clear_scheduled_hook('mmi_scheduler_tick');
