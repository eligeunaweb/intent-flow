<?php
if (!defined('ABSPATH')) { exit; }
return [
  'intent' => 'server.session.live.enable',
  'scope'  => 'settings',
  'capability' => 'manage_options',
  'unsafe' => false,
  'run' => function(array $ctx) {
    $payload = $ctx['payload'] ?? [];
    $token = $payload['token'] ?? '';
    $ttl   = isset($payload['ttl']) ? (int)$payload['ttl'] : 600; // 10 min

    // valida token igual que server.settings.set
    $res = MacroIntent_Settings::validate_token($token, get_current_user_id());
    if (!$res['ok']) return $res;

    if ($ttl < 60) $ttl = 60;
    if ($ttl > 3600) $ttl = 3600;

    $until = time() + $ttl;
    update_option('macro_intent_live_until', $until, false);

    return [
      'ok' => true,
      'live' => true,
      'live_until' => $until,
      'ttl' => $ttl,
    ];
  },
];
