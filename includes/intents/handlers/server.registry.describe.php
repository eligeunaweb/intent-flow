<?php
if (!defined('ABSPATH')) { exit; }

require_once __DIR__ . '/../modules/RegistryMeta.php';

return function(IF_IntentRegistry $reg) : void {

	$reg->register(
		'server.registry.describe',
		function(array $payload) use ($reg) {
			$ref = new ReflectionClass($reg);
			$prop = $ref->getProperty('map');
			$prop->setAccessible(true);
			$map = (array)$prop->getValue($reg);

			$want = isset($payload['id']) ? trim((string)$payload['id']) : '';
			$ids = array_keys($map);
			sort($ids);

			$one = function(string $id) use ($map) : array {
				$entry = $map[$id] ?? null;
				$policy = is_array($entry) && isset($entry['policy']) && is_array($entry['policy']) ? $entry['policy'] : [];

				$meta = IF_IntentMeta::get($id);
				$title = $meta['title'] ?? $id;
				$desc  = $meta['description'] ?? '';
				$risk  = $meta['risk'] ?? (!empty($policy['unsafe']) ? 'unsafe' : 'safe');
				$ex    = $meta['example_payload'] ?? [];

				return [
					'id' => $id,
					'title' => $title,
					'description' => $desc,
					'risk' => $risk,
					'example_payload' => $ex,
					'schema' => isset($meta['schema']) && is_array($meta['schema']) ? $meta['schema'] : null,
					'policy' => [
						'capability' => (string)($policy['capability'] ?? 'manage_options'),
						'scope' => (string)($policy['scope'] ?? 'core'),
						'unsafe' => !empty($policy['unsafe']),
						'requires_live' => !empty($policy['requires_live']),
						'dry_run_exempt' => !empty($policy['dry_run_exempt']),
						'notes' => (string)($policy['notes'] ?? ''),
						'tags' => isset($policy['tags']) && is_array($policy['tags']) ? array_values($policy['tags']) : [],
					],
				];
			};

			if ($want !== '') {
				if (!isset($map[$want])) {
					return [
						'ok' => false,
						'error' => 'unknown_intent',
						'message' => 'Intent no encontrado',
					];
				}
				return [
					'ok' => true,
					'intent' => $one($want),
				];
			}

			$out = [];
			foreach ($ids as $id) {
				$out[$id] = $one($id);
			}

			return [
				'ok' => true,
				'intents' => $out,
				'count' => count($out),
			];
		},
		[
			'capability' => 'manage_options',
			'scope' => 'diagnostics',
		]
	);
};
