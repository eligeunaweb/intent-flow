<?php
if (!defined('ABSPATH')) { exit; }
return function(IF_IntentRegistry $reg) : void {
    $reg->register('server.connections.save',
        function(array $payload, array $meta, array $ctx) : array {
            return IF_Connections::save($payload);
        },
        ['capability'=>'manage_options']
    );
};
