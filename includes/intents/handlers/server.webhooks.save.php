<?php
if (!defined('ABSPATH')) { exit; }

return [
  'intent' => 'server.webhooks.save',
  'run' => function(array $ctx) : array {
    if (!current_user_can('manage_options')) return ['ok'=>false,'error'=>'forbidden'];
    if (!class_exists('INTEFLOW_Webhooks')) return ['ok'=>false,'error'=>'webhooks_missing'];
    $p = is_array($ctx['payload'] ?? null) ? $ctx['payload'] : [];
    $item = INTEFLOW_Webhooks::save($p);
    return ['ok'=>true,'result'=>['item'=>$item]];
  }
];
