<?php
if (!defined('ABSPATH')) { exit; }

return function(IF_IntentRegistry $reg) : void {

  // ✅ allowlist de opciones que permites tocar desde el motor
  $ALLOW = [
    'inteflow_settings',
    'inteflow_test_option',
    // añade aquí las que quieras permitir
  ];

  $reg->register(
    'server.options.set',
    function(array $payload, array $meta, array $ctx) use ($ALLOW) : array {

      $key = isset($payload['key']) ? sanitize_key((string)$payload['key']) : '';
      if ($key === '') {
        return ['ok'=>false,'error'=>'missing_key'];
      }

      if (!in_array($key, $ALLOW, true)) {
        return ['ok'=>false,'blocked'=>true,'reason'=>'option_not_allowed','key'=>$key];
      }

      $value = $payload['value'] ?? null;

      update_option($key, $value, false);

      return ['updated'=>true,'key'=>$key];
    },
    [
      'capability'    => 'manage_options',
      'unsafe'        => true,   // ✅ safe_mode ON lo bloquea
      'requires_live' => true,   // ✅ dry_run ON lo bloquea
      'tags'          => ['options'],
      'notes'         => 'Allowlisted option setter (unsafe + live-only)',
    ]
  );

};
