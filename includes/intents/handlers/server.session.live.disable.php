<?php
if (!defined('ABSPATH')) { exit; }
return [
  'intent' => 'server.session.live.disable',
  'scope'  => 'settings',
  'capability' => 'manage_options',
  'unsafe' => false,
  'run' => function(array $ctx) {
    $payload = $ctx['payload'] ?? [];
    $token = $payload['token'] ?? '';

    $res = MacroIntent_Settings::validate_token($token, get_current_user_id());
    if (!$res['ok']) return $res;

    delete_option('macro_intent_live_until');

    return [
      'ok' => true,
      'live' => false,
    ];
  },
];
