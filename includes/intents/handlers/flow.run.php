<?php
if (!defined('ABSPATH')) { exit; }

require_once __DIR__ . '/../../Flows.php';

return function(IF_IntentRegistry $reg) : void {

	$reg->register(
		'flow.run',
		function(array $payload, array $meta, array $ctx) use ($reg) : array {
			// payload: {id} or {flow:{steps:[]}, stop_on_error?:bool}
			$stop = isset($payload['stop_on_error']) ? (bool)$payload['stop_on_error'] : true;
			$maxSteps = 50;

			$flowId = isset($payload['id']) ? sanitize_key((string)$payload['id']) : '';
			$flow = null;

			if ($flowId !== '') {
				$flow = IF_Flows::get($flowId);
				if (!$flow) return ['ok'=>false,'error'=>'not_found'];
			} else if (isset($payload['flow']) && is_array($payload['flow'])) {
				$flow = $payload['flow'];
			} else {
				return ['ok'=>false,'error'=>'missing_flow'];
			}

			$steps = isset($flow['steps']) && is_array($flow['steps']) ? $flow['steps'] : [];
			if (count($steps) === 0) return ['ok'=>false,'error'=>'missing_steps'];
			if (count($steps) > $maxSteps) return ['ok'=>false,'error'=>'too_many_steps','max'=>$maxSteps];

			$results = [];
			$okCount = 0;
			$blockedCount = 0;
			$failCount = 0;
			$startAll = microtime(true);

			foreach ($steps as $i => $step) {
				$id = isset($step['id']) ? sanitize_text_field((string)$step['id']) : '';
				$pl = (isset($step['payload']) && is_array($step['payload'])) ? $step['payload'] : [];
				$mt = (isset($step['meta']) && is_array($step['meta'])) ? $step['meta'] : [];

				if ($id === '') {
					$results[] = ['index'=>$i,'ok'=>false,'error'=>'missing_intent_id'];
					$failCount++;
					if ($stop) break;
					continue;
				}

				$out = $reg->dispatch($id, $pl, $mt, $ctx);

				$item = [
					'index'=>$i,
					'intentId'=>$id,
					'ok'=>!empty($out['ok']),
					'blocked'=>!empty($out['blocked']),
					'reason'=>$out['reason'] ?? '',
					'error'=>$out['error'] ?? '',
					'result'=>$out['result'] ?? null,
				];

				if (!empty($out['blocked'])) $blockedCount++;
				else if (!empty($out['ok'])) $okCount++;
				else $failCount++;

				$results[] = $item;
				if ($stop && (empty($out['ok']) || !empty($out['blocked']))) break;
			}

			$ms = (int)round((microtime(true) - $startAll) * 1000);

			return [
				'ok' => ($failCount === 0 && $blockedCount === 0),
				'flow_id' => $flowId,
				'stop_on_error' => $stop,
				'total_steps' => count($steps),
				'executed_steps' => count($results),
				'ok_count' => $okCount,
				'blocked_count' => $blockedCount,
				'fail_count' => $failCount,
				'duration_ms' => $ms,
				'results' => $results,
			];
		},
		[
			'capability' => 'manage_options',
			'scope' => 'automation',
			'unsafe' => true,
			'requires_live' => true,
		]
	);

};
