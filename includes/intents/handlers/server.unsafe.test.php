<?php
if (!defined('ABSPATH')) { exit; }

return function(IF_IntentRegistry $reg) : void {
    $reg->register(
        'server.unsafe.test',
        function(array $payload, array $meta, array $ctx) : array {
            return [
                'note' => 'Si safe_mode está ON, esto no debería ejecutarse.',
                'ctx'  => [
                    'dry_run'   => !empty($ctx['dry_run']),
                    'safe_mode' => !empty($ctx['safe_mode']),
                ],
            ];
        },
        [
            'capability'    => 'manage_options',
            'unsafe'        => true,
            'requires_live' => false,
            'tags'          => ['unsafe'],
        ]
    );
};
