<?php
if (!defined('ABSPATH')) { exit; }

return function(IF_IntentRegistry $reg) : void {

  // ✅ Lista de opciones permitidas (ajústala a tu proyecto)
  $ALLOW = [
    'inteflow_settings',      // tu plugin
    'inteflow_test_option',   // test
  ];

  $reg->register(
    'server.options.set',
    function(array $payload, array $meta, array $ctx) use ($ALLOW) : array {

      $key = isset($payload['key']) ? sanitize_key((string)$payload['key']) : '';
      if ($key === '') return ['ok'=>false,'error'=>'missing_key'];

      if (!in_array($key, $ALLOW, true)) {
        return ['ok'=>false,'blocked'=>true,'reason'=>'option_not_allowed'];
      }

      $value = $payload['value'] ?? null;
      update_option($key, $value, false);

      return ['updated'=>true,'key'=>$key];
    },
    [
      'capability'    => 'manage_options',
      'unsafe'        => true,        // 🔥 marcar como unsafe
      'requires_live' => true,        // bloquea en dry_run
      'tags'          => ['options'],
      'notes'         => 'Allowlist option setter',
    ]
  );
};
