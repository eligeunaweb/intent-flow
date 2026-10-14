<?php
if (!defined('ABSPATH')) { exit; }

return function(INTEFLOW_IntentRegistry $reg) : void {
    $reg->register(
        'server.flows.list',
        function(array $payload, array $meta, array $ctx) : array {
            return ['ok'=>true,'flows'=>INTEFLOW_FlowRegistry::all()];
        },
        ['capability' => 'manage_options']
    );
};
