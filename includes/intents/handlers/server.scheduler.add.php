<?php
if (!defined('ABSPATH')) { exit; }

return [
  'intent' => 'server.scheduler.add',
  'run' => function(array $ctx) : array {
    if (!class_exists('IF_Scheduler')) return ['ok'=>false,'error'=>'scheduler_missing'];

    $payload = is_array($ctx['payload'] ?? null) ? $ctx['payload'] : [];

    $jobs = IF_Scheduler::all();

    $id = sanitize_key($payload['id'] ?? uniqid('job_'));
    $interval = max(60, intval($payload['interval'] ?? 3600));

	// Target v1 (preferred)
	$target = $payload['target'] ?? $payload;
	$target = class_exists('IF_Target') ? IF_Target::normalize($target) : (array)$target;
	$type   = (string)($target['type'] ?? 'intent');
	$tid    = (string)($target['id'] ?? '');
	if ($tid === '') return ['ok'=>false,'error'=>'missing_target'];
	$intent = $type === 'intent' ? $tid : '';
	$app_id = $type === 'app' ? $tid : '';

    $jobs[$id] = [
      'id'       => $id,
	  'target'   => $target,
	  // Legacy fields kept for UI compatibility
	  'type'     => $type,
      'intent'   => $intent,
	  'app_id'   => $app_id !== '' ? sanitize_text_field($app_id) : '',
      'payload'  => is_array($payload['payload'] ?? null) ? $payload['payload'] : [],
	  'input'    => is_array($payload['input'] ?? null) ? $payload['input'] : (is_array($payload['payload'] ?? null) ? $payload['payload'] : []),
      'interval' => $interval,
      'enabled'  => true,
      'next_run' => time() + $interval,
      'last_run' => 0,
      'last_result' => null,
    ];

    IF_Scheduler::save($jobs);

    return ['ok'=>true,'id'=>$id,'job'=>$jobs[$id]];
  },
  'capability' => 'manage_options',
  'scope' => 'core',
  // Not unsafe: this is configuration, not execution.
];
