<?php
if (!defined('ABSPATH')) { exit; }

return [
  'intent' => 'server.webhooks.delete',
  'run' => function(array $ctx) : array {
    if (!current_user_can('manage_options')) return ['ok'=>false,'error'=>'forbidden'];
    if (!class_exists('INTEFLOW_Webhooks')) return ['ok'=>false,'error'=>'webhooks_missing'];
    $p = is_array($ctx['payload'] ?? null) ? $ctx['payload'] : [];
    $id = sanitize_key((string)($p['id'] ?? ''));
    if (!$id) return ['ok'=>false,'error'=>'missing_id'];
    $ok = INTEFLOW_Webhooks::delete($id);
    return ['ok'=>true,'result'=>['deleted'=>$ok]];
  }
];
