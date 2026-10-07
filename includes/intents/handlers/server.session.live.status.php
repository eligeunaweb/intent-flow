<?php
if (!defined('ABSPATH')) { exit; }

return [
  'intent' => 'server.session.live.status',
  'scope'  => 'settings',
  'capability' => 'manage_options',
  'unsafe' => false,
  'run' => function(array $ctx) {
    $until = (int) get_option('macro_intent_live_until', 0);
    $now   = time();

    $active = ($until > $now);
    $remaining = $active ? max(0, $until - $now) : 0;

    return [
      'ok'         => true,
      'live_active'=> $active,
      'live'       => $active,      // compat
      'live_until' => $active ? $until : 0,
      'remaining'  => $remaining,
    ];
  },
  'notes' => 'Devuelve el estado actual de Live (si está activo y cuánto queda).',
  'tags'  => ['live'],
];
