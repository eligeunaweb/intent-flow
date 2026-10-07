<?php
if (!defined('ABSPATH')) { exit; }
// Email module
define('IF_MODULE_EMAIL_VERSION', '1.0.0');

add_action('if_register_intents', function($reg){
    if (!is_object($reg) || !method_exists($reg, 'register')) return;

    // Lightweight ping to verify module is loaded
    $reg->register('module.email.ping', function($payload, $meta, $ctx) {
        return [ 'ok' => true, 'module' => 'email', 'version' => '1.0.0' ];
    }, ['capability' => 'manage_options']);
}, 50);
