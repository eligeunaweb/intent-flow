<?php
if (!defined('ABSPATH')) { exit; }

return function(IF_IntentRegistry $reg) : void {
    $reg->register(
        'server.diagnostics.whoami',
        function(array $payload, array $meta, array $ctx) : array {
            $user = wp_get_current_user();
            return [
                'user' => [
                    'id'    => (int) $user->ID,
                    'email' => (string) $user->user_email,
                    'roles' => (array) $user->roles,
                ],
                'dry_run'   => !empty($ctx['dry_run']),
                'safe_mode' => !empty($ctx['safe_mode']),
            ];
        },
        [
            'capability'    => 'manage_options',
            'unsafe'        => false,
            'requires_live' => false,
            'tags'          => ['diagnostics'],
        ]
    );
};
