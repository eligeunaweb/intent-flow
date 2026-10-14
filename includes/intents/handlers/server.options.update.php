<?php
if (!defined('ABSPATH')) { exit; }

return function(INTEFLOW_IntentRegistry $reg) : void {
    $reg->register(
        'server.options.update',
        function(array $payload, array $meta, array $ctx) : array {
            $key   = isset($payload['key']) ? sanitize_key($payload['key']) : '';
            $value = $payload['value'] ?? null;

            if ($key === '') {
                return [ 'ok' => false, 'error' => 'missing_key' ];
            }

            // Strict allowlist — only these specific options can be updated via automation
            $allowed_options = [
                'inteflow_settings',
                'inteflow_enabled_modules',
                'inteflow_apps_v1',
                'inteflow_presets_v1',
                'inteflow_event_rules_v1',
                'inteflow_flows_v1',
            ];
            if (!in_array($key, $allowed_options, true)) {
                return ['ok'=>false,'error'=>'option_not_allowed','allowed'=>$allowed_options];
            }
            update_option($key, $value, false);

            return [
                'updated' => true,
                'key'     => $key,
            ];
        },
        [
            'capability'    => 'manage_options',
            'unsafe'        => false,
            'requires_live' => true,
            'tags'          => ['options'],
        ]
    );
};
