<?php
if (!defined('ABSPATH')) { exit; }
return function(INTEFLOW_IntentRegistry $reg) : void {
    $reg->register('server.templates.list',
        function(array $payload, array $meta, array $ctx) : array {
            return ['ok'=>true,'items'=>INTEFLOW_Templates::all()];
        },
        ['capability'=>'manage_options']
    );
};
