<?php
if (!defined('ABSPATH')) { exit; }

return function(INTEFLOW_IntentRegistry $reg) : void {
    $reg->register(
        'server.flows.delete',
        function(array $payload, array $meta, array $ctx) : array {
            $id = isset($payload['id']) ? (string)$payload['id'] : ''; return INTEFLOW_FlowRegistry::delete($id);
        },
        ['capability' => 'manage_options']
    );
};
