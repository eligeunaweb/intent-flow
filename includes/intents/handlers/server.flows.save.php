<?php
if (!defined('ABSPATH')) { exit; }

return function(INTEFLOW_IntentRegistry $reg) : void {
    $reg->register(
        'server.flows.save',
        function(array $payload, array $meta, array $ctx) : array {
            return INTEFLOW_FlowRegistry::save($payload);
        },
        ['capability' => 'manage_options']
    );
};
