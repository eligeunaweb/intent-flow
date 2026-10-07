<?php
if (!defined('ABSPATH')) { exit; }

return [
  'intent' => 'server.scheduler.update',
  'run' => function(array $ctx) : array {
    if (!class_exists('IF_Scheduler')) return ['ok'=>false,'error'=>'scheduler_missing'];

    $payload = is_array($ctx['payload'] ?? null) ? $ctx['payload'] : [];
    $id = sanitize_key($payload['id'] ?? '');
    if (!$id) return ['ok'=>false,'error'=>'missing_id'];

    $jobs = IF_Scheduler::all();
    if (!isset($jobs[$id])) return ['ok'=>false,'error'=>'not_found'];

    $job = $jobs[$id];

	// Target v1 (preferred) - supports legacy fields too
	if (array_key_exists('target', $payload)) {
		$job['target'] = is_array($payload['target']) ? $payload['target'] : [];
	}
	// Legacy editing still supported (UI v0.9.4.2.6)
	if (array_key_exists('type', $payload) || array_key_exists('intent', $payload) || array_key_exists('app_id', $payload)) {
		$job['type']  = array_key_exists('type', $payload) ? sanitize_key((string)$payload['type']) : (string)($job['type'] ?? '');
		$job['intent'] = array_key_exists('intent', $payload) ? (string)$payload['intent'] : (string)($job['intent'] ?? '');
		$job['app_id'] = array_key_exists('app_id', $payload) ? sanitize_text_field((string)$payload['app_id']) : (string)($job['app_id'] ?? '');
		$job['target'] = [ 'type' => $job['type'], 'intent' => $job['intent'], 'app_id' => $job['app_id'] ];
	}
	$job['target'] = class_exists('IF_Target') ? IF_Target::normalize($job['target'] ?? []) : (array)($job['target'] ?? []);
	$job['type']   = (string)($job['target']['type'] ?? 'intent');
	$job['intent'] = $job['type'] === 'intent' ? (string)($job['target']['id'] ?? '') : '';
	$job['app_id'] = $job['type'] === 'app' ? (string)($job['target']['id'] ?? '') : '';

	// (legacy keys handled above)
    if (array_key_exists('interval', $payload)) {
      $job['interval'] = max(60, intval($payload['interval']));
    }
    if (array_key_exists('enabled', $payload)) {
      $job['enabled'] = (bool)$payload['enabled'];
    }
    if (array_key_exists('payload', $payload)) {
      $job['payload'] = is_array($payload['payload']) ? $payload['payload'] : [];
    }
	if (array_key_exists('input', $payload)) {
		$job['input'] = is_array($payload['input']) ? $payload['input'] : [];
	}

	// Normalize required fields
	if ($job['type'] === 'intent' && empty($job['intent'])) return ['ok'=>false,'error'=>'missing_intent'];
	if ($job['type'] === 'app' && empty($job['app_id'])) return ['ok'=>false,'error'=>'missing_app_id'];
	// Back-compat: if no explicit input, reuse payload
	if ($job['type'] === 'app' && !isset($job['input'])) $job['input'] = is_array($job['payload'] ?? null) ? $job['payload'] : [];

    // If interval changed or job re-enabled, recompute next_run from now to make it predictable.
    $now = time();
    if (!empty($job['enabled'])) {
      $job['next_run'] = $now + intval($job['interval'] ?? 3600);
    }

    $jobs[$id] = $job;
    IF_Scheduler::save($jobs);

    return ['ok'=>true,'id'=>$id,'job'=>$job];
  },
  'capability' => 'manage_options',
  'scope' => 'core',
  'unsafe' => false,
  'tags' => ['scheduler'],
];
