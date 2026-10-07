<?php
if (!defined('ABSPATH')) { exit; }

return [
  'intent' => 'server.scheduler.runs.clear',
  'capability' => 'manage_options',
  'scope' => 'core',
  'unsafe' => false,
  'tags' => ['scheduler'],
  'run' => function(array $ctx) : array {
    if (!class_exists('IF_SchedulerRuns')) return ['ok'=>false,'error'=>'runs_store_missing'];
    $p = (array)($ctx['payload'] ?? []);
    $job_id = isset($p['id']) ? sanitize_text_field((string)$p['id']) : '';
    if ($job_id === '') return ['ok'=>false,'error'=>'missing_id'];
    IF_SchedulerRuns::clear($job_id);
    return ['ok'=>true,'id'=>$job_id];
  },
];
