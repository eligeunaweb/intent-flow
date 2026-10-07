<?php
if (!defined('ABSPATH')) { exit; }
// Webhook Incoming module
define('IF_MODULE_WEBHOOK_IN_VERSION', '1.0.0');

add_action('if_register_intents', function($reg){
    if (!is_object($reg) || !method_exists($reg, 'register')) return;

    // Lightweight ping to verify module is loaded
    $reg->register('module.webhook_in.ping', function($payload, $meta, $ctx) {
        return [ 'ok' => true, 'module' => 'webhook_in', 'version' => '1.0.0' ];
    }, ['capability' => 'manage_options']);
}, 50);
