<?php
if (!defined('ABSPATH')) { exit; }

return [
  'intent' => 'server.webhooks.list',
  'run' => function(array $ctx) : array {
    if (!class_exists('INTEFLOW_Webhooks')) return ['ok'=>false,'error'=>'webhooks_missing'];
    $all = INTEFLOW_Webhooks::get_all();
    $out = [];
    foreach ($all as $id => $item) {
      if (!is_array($item)) continue;
      $out[] = array_merge($item, [
        'id' => $id,
        'url' => function_exists('get_rest_url')
          ? get_rest_url(null, 'inteflow/v1/webhook/' . $id)
          : rest_url('if/v1/webhook/' . $id),
      ]);
    }
    usort($out, function($a,$b){
      return intval($b['created_at'] ?? 0) <=> intval($a['created_at'] ?? 0);
    });
    return ['ok'=>true,'result'=>['items'=>$out]];
  }
];
