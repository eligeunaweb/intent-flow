<?php
if (!defined('ABSPATH')) { exit; }

return [
    'intent' => 'server.bundle.export',
    'run' => function(array $payload, array $meta, array $ctx) : array {

        $apps = get_option('if_apps', []);
        if (!is_array($apps)) {
            $apps = json_decode((string)$apps, true);
            if (!is_array($apps)) { $apps = []; }
        }

        $flows = class_exists('IF_Flows') ? IF_Flows::all() : [];
        $presets = class_exists('IF_Presets') ? IF_Presets::all() : [];
        $settings = get_option('inteflow_settings', []);

        $bundle = [
            'schema' => 'if_bundle_v1',
            'plugin_version' => defined('IF_VERSION') ? IF_VERSION : 'unknown',
            'exported_at' => gmdate('c'),
            'site_url' => home_url('/'),
            'apps' => array_values($apps),
            'flows' => $flows,
            'presets' => $presets,
            'settings' => $settings,
        ];

        $json = wp_json_encode($bundle, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
        $checksum = 'sha256:' . hash('sha256', $json);
        $bundle['checksum'] = $checksum;
        $json = wp_json_encode($bundle, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);

        return [
            'ok' => true,
            'bundle' => $bundle,
            'json' => $json,
            'checksum' => $checksum,
        ];
    },
    'capability' => 'manage_options',
    'scope' => 'core',
];
