<?php
if (!defined('ABSPATH')) { exit; }

class INTEFLOW_ActionExecutor {

	public static function execute(string $action_id, array $input = [], array $meta = [], array $options = []) : array {

		$action = INTEFLOW_ActionRegistry::get($action_id);
		if (!$action) {
			return [ 'ok' => false, 'error' => 'action_not_found', 'action_id' => $action_id ];
		}

		$intent = isset($action['intent']) ? (string)$action['intent'] : '';
		if ($intent === '') {
			return [ 'ok' => false, 'error' => 'action_missing_intent', 'action_id' => $action_id ];
		}

		$meta = is_array($meta) ? $meta : [];
		$meta['action_id'] = $action_id;
		$meta['integration'] = $action['integration'] ?? '';

		return INTEFLOW_Runner::run_target(
			[ 'type' => 'intent', 'id' => $intent ],
			$input,
			$meta,
			$options
		);
	}
}
