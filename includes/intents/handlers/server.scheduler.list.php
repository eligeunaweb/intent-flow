<?php
if (!defined('ABSPATH')) { exit; }

return function(INTEFLOW_IntentRegistry $reg) : void {
    $reg->register(
        'server.scheduler.list',
        function(array $payload) : array {
            if (!class_exists('INTEFLOW_Scheduler')) return ['ok'=>false,'error'=>'scheduler_missing'];
            return ['ok'=>true,'now'=>time(),'jobs'=>INTEFLOW_Scheduler::all()];
        },
        ['capability'=>'manage_options','scope'=>'core','unsafe'=>false,'tags'=>['scheduler']]
    );
};
