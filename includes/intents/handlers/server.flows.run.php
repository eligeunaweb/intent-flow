<?php
if (!defined('ABSPATH')) { exit; }

return function(IF_IntentRegistry $reg) : void {
    $reg->register(
        'server.flows.run',
        function(array $payload, array $meta, array $ctx) : array {
            $id = isset($payload['id']) ? (string)$payload['id'] : ''; if ($id === '') return ['ok'=>false,'error'=>'missing_id']; $event_payload = (isset($payload['payload']) && is_array($payload['payload'])) ? $payload['payload'] : []; return IF_FlowExecutor::run($id, $event_payload, array_merge($meta, ['source'=>'manual.flow']), []);
        },
        ['capability' => 'manage_options']
    );
};
