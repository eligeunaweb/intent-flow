<?php
if (!defined('ABSPATH')) { exit; }

return [
  'intent' => 'server.scheduler.run_job',
  'capability' => 'manage_options',
  'scope' => 'core',
  'unsafe' => false,
  'tags' => ['scheduler'],
  'run' => function(array $ctx) : array {
    if (!class_exists('IF_Scheduler')) return ['ok'=>false,'error'=>'scheduler_missing'];
    $p = (array)($ctx['payload'] ?? []);
    $job_id = isset($p['id']) ? sanitize_text_field((string)$p['id']) : '';
    if ($job_id === '') return ['ok'=>false,'error'=>'missing_id'];

    $run = IF_Scheduler::run_job($job_id, 'ui');
    if (empty($run['ok'])) {
      return ['ok'=>false,'error'=>$run['error'] ?? 'run_failed','run'=>$run];
    }
    return ['ok'=>true,'run'=>$run];
  },
];
