<?php
if (!defined('ABSPATH')) { exit; }

return [
  'intent' => 'server.scheduler.delete',
  'run' => function(array $ctx) : array {
    if (!class_exists('IF_Scheduler')) return ['ok'=>false,'error'=>'scheduler_missing'];

    $payload = is_array($ctx['payload'] ?? null) ? $ctx['payload'] : [];
    $id = sanitize_key($payload['id'] ?? '');

    $jobs = IF_Scheduler::all();
    if (!$id || !isset($jobs[$id])) {
      return ['ok'=>false,'error'=>'not_found'];
    }

    unset($jobs[$id]);
    IF_Scheduler::save($jobs);

    return ['ok'=>true];
  },
  'capability' => 'manage_options',
  'scope' => 'core',
];
