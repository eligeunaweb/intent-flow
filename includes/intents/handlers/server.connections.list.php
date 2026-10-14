<?php
if (!defined('ABSPATH')) { exit; }
return function(INTEFLOW_IntentRegistry $reg) : void {
    $reg->register('server.connections.list',
        function(array $payload, array $meta, array $ctx) : array {
            return ['ok'=>true,'items'=>INTEFLOW_Connections::all()];
        },
        ['capability'=>'manage_options']
    );
};
