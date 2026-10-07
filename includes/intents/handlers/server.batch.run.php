<?php
if (!defined('ABSPATH')) { exit; }

return function(IF_IntentRegistry $reg) : void {

  $reg->register(
    'server.batch.run',
    function(array $payload, array $meta, array $ctx) use ($reg) : array {

      $steps = isset($payload['steps']) && is_array($payload['steps']) ? $payload['steps'] : [];
      $maxSteps = 25;

      if (count($steps) === 0) {
        return [
          'ok' => false,
          'error' => 'missing_steps',
        ];
      }

      if (count($steps) > $maxSteps) {
        return [
          'ok' => false,
          'error' => 'too_many_steps',
          'max' => $maxSteps,
        ];
      }

      $results = [];
      $okCount = 0;
      $blockedCount = 0;
      $failCount = 0;

      foreach ($steps as $i => $step) {
        $id = isset($step['id']) ? sanitize_text_field((string)$step['id']) : '';
        $pl = (isset($step['payload']) && is_array($step['payload'])) ? $step['payload'] : [];
        $mt = (isset($step['meta']) && is_array($step['meta'])) ? $step['meta'] : [];

        if ($id === '') {
          $results[] = [
            'index' => $i,
            'ok' => false,
            'error' => 'missing_intent_id',
          ];
          $failCount++;
          continue;
        }

        // Dispatch interno reutilizando el mismo registry/policy
        $out = $reg->dispatch($id, $pl, $mt, $ctx);

        $item = [
          'index'   => $i,
          'intentId'=> $id,
          'ok'      => !empty($out['ok']),
          'blocked' => !empty($out['blocked']),
          'reason'  => $out['reason'] ?? '',
          'error'   => $out['error'] ?? '',
        ];

        if (!empty($out['ok'])) {
          $item['result'] = $out['result'] ?? null;
          $okCount++;
        } else {
          if (!empty($out['blocked'])) {
            $blockedCount++;
          } else {
            $failCount++;
          }
          if (!empty($out['message'])) {
            $item['message'] = $out['message'];
          }
        }

        $results[] = $item;
      }

      return [
        'results' => $results,
        'summary' => [
          'total'   => count($results),
          'ok'      => $okCount,
          'blocked' => $blockedCount,
          'failed'  => $failCount,
        ],
      ];
    },
    [
      'capability'    => 'manage_options',
      'unsafe'        => false,
      'requires_live' => false,
      'tags'          => ['batch'],
      'notes'         => 'Run multiple intents server-side with unified report',
    ]
  );
};
