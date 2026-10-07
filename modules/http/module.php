<?php
if (!defined('ABSPATH')) { exit; }
// HTTP module
define('IF_MODULE_HTTP_VERSION', '1.0.0');

add_action('if_register_intents', function($reg){
    if (!is_object($reg) || !method_exists($reg, 'register')) return;

    // Lightweight ping to verify module is loaded
    $reg->register('module.http.ping', function($payload, $meta, $ctx) {
        return [ 'ok' => true, 'module' => 'http', 'version' => '1.0.0' ];
    }, ['capability' => 'manage_options']);
}, 50);
