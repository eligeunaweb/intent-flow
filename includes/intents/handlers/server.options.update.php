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

            if (strpos($key, 'inteflow_') !== 0) { return ['ok'=>false,'error'=>'option_must_use_inteflow_prefix']; }
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
