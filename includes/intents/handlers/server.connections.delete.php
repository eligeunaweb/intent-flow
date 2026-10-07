<?php
if (!defined('ABSPATH')) { exit; }
return function(IF_IntentRegistry $reg) : void {
    $reg->register('server.connections.delete',
        function(array $payload, array $meta, array $ctx) : array {
            $id = isset($payload['id']) ? (string)$payload['id'] : '';
            return IF_Connections::delete($id);
        },
        ['capability'=>'manage_options']
    );
};
