<?php
if (!defined('ABSPATH')) { exit; }

return [
  'intent' => 'server.webhooks.rotate_secret',
  'run' => function(array $ctx) : array {
    if (!current_user_can('manage_options')) return ['ok'=>false,'error'=>'forbidden'];
    if (!class_exists('IF_Webhooks')) return ['ok'=>false,'error'=>'webhooks_missing'];
    $p = is_array($ctx['payload'] ?? null) ? $ctx['payload'] : [];
    $id = sanitize_key((string)($p['id'] ?? ''));
    if (!$id) return ['ok'=>false,'error'=>'missing_id'];
    $item = IF_Webhooks::rotate_secret($id);
    if (!$item) return ['ok'=>false,'error'=>'not_found'];
    return ['ok'=>true,'result'=>['item'=>$item]];
  }
];
