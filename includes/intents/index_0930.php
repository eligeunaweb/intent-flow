<?php
if (!defined('ABSPATH')) { exit; }

require_once __DIR__ . '/modules/Registry.php';

/**
 * Build the server intent registry.
 *
 * Handler files can return either:
 *  1) callable(IF_IntentRegistry $reg): void  (legacy / preferred)
 *  2) array{intent:string, run:callable, ...policy} (declarative)
 */
function if_build_intent_registry() : IF_IntentRegistry {

	$registry = new IF_IntentRegistry();

	// Preload apps helpers + ensure server.apps.save is registered early.
	$apps_save_file = __DIR__ . '/handlers/server.apps.save.php';
	if (file_exists($apps_save_file)) {
		$def0 = require_once $apps_save_file;
		if (is_callable($def0)) { $def0($registry); }
	}

	// Preload scheduler run_now registration early.
	$sched_run_now_file = __DIR__ . '/handlers/server.scheduler.run_now.register.php';
	if (file_exists($sched_run_now_file)) {
		$defS = require_once $sched_run_now_file;
		if (is_callable($defS)) { $defS($registry); }
	}

	// Load handlers ALWAYS (no static cache)
	$handlers_dir = __DIR__ . '/handlers/';

	if (is_dir($handlers_dir)) {

		$files = glob($handlers_dir . '*.php');

		if ($files) {

			foreach ($files as $file) {

				$def = require_once $file;

				// 1) Callable style: function(IF_IntentRegistry $reg): void
				if (is_callable($def)) {
					$def($registry);
					continue;
				}

				// 2) Declarative style: ['intent' => 'x', 'run' => function(array $ctx){...}, ...policy]
				if (is_array($def) && !empty($def['intent']) && is_string($def['intent']) && isset($def['run']) && is_callable($def['run'])) {

					$intent_id = $def['intent'];

					$policy = [
						'capability'     => $def['capability'] ?? 'manage_options',
						'scope'          => $def['scope'] ?? 'core',
						'unsafe'         => !empty($def['unsafe']),
						'requires_live'  => !empty($def['requires_live']),
						'dry_run_exempt' => !empty($def['dry_run_exempt']),
						'notes'          => $def['notes'] ?? '',
						'tags'           => is_array($def['tags'] ?? null) ? $def['tags'] : [],
					];

					$run = $def['run'];

					$registry->register(
						$intent_id,
						function(array $payload, array $meta, array $ctx) use ($run) : array {
							// Normalize to the ctx contract expected by declarative handlers
							$ctx = is_array($ctx) ? $ctx : [];
							$ctx['payload'] = $payload;
							$ctx['meta']    = $meta;

							$res = $run($ctx);
							return is_array($res) ? $res : ['ok' => false, 'error' => 'handler_invalid_response'];
						},
						$policy
					);

					continue;
				}

				// Unknown handler format -> ignore (but keep registry working)
			}

		}

	}

	return $registry;
}