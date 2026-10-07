<?php
if (!defined('ABSPATH')) { exit; }

/**
 * Minimal event system (v1):
 * - Persistent rules stored in WP options
 * - Emit events from WP hooks
 * - Dispatch rules via IF_Runner
 */
final class IF_Events {
	const OPTION_KEY = 'if_event_rules_v1';

	public static function init() : void {
		// Core WP hooks -> events
		add_action('save_post', [__CLASS__, 'on_save_post'], 10, 3);

                // User hooks -> events
                add_action('user_register',          [__CLASS__, 'on_user_register'],       10, 1);
                add_action('wp_login',               [__CLASS__, 'on_user_login'],           10, 2);
                add_action('wp_login_failed',        [__CLASS__, 'on_user_login_failed'],    10, 2);
                add_action('wp_logout',              [__CLASS__, 'on_user_logout'],          10, 1);
                add_action('profile_update',         [__CLASS__, 'on_profile_update'],       10, 2);
                add_action('password_reset',         [__CLASS__, 'on_password_reset'],       10, 2);
                add_action('delete_user',            [__CLASS__, 'on_user_deleted'],         10, 1);
                add_action('set_user_role',          [__CLASS__, 'on_user_role_changed'],    10, 3);

                // Post/comment hooks -> events
                add_action('delete_post',            [__CLASS__, 'on_delete_post'],          10, 1);
                add_action('wp_trash_post',          [__CLASS__, 'on_trash_post'],           10, 1);
                add_action('comment_post',           [__CLASS__, 'on_comment_post'],         10, 3);
                add_action('transition_post_status', [__CLASS__, 'on_post_status_changed'],  10, 3);
		add_action('upgrader_process_complete', [__CLASS__, 'on_upgrader_process_complete'], 10, 2);

		// Scheduler tick -> event
		add_action('inteflow_scheduler_tick', function() {
			self::emit('cron.tick', [
				'ts' => time(),
			], [
				'source' => 'scheduler',
			]);
		}, 20);
	}

	public static function all_rules() : array {
		$v = get_option(self::OPTION_KEY, []);
		$rules = is_array($v) ? $v : [];
		// Lazy normalize to target v1
		foreach ($rules as $ev => $list) {
			if (!is_array($list)) continue;
			foreach ($list as $i => $r) {
				if (!is_array($r)) continue;
				if (!isset($r['target'])) {
					$rules[$ev][$i]['target'] = class_exists('IF_Target') ? IF_Target::normalize($r) : ['type'=>'intent','id'=> (string)($r['intent'] ?? '')];
				}
				if (!isset($rules[$ev][$i]['input'])) {
					$rules[$ev][$i]['input'] = is_array($r['input'] ?? null) ? $r['input'] : (is_array($r['payload'] ?? null) ? $r['payload'] : []);
				}
				// Legacy mirrors for existing UI
				$tt = (string)($rules[$ev][$i]['target']['type'] ?? 'intent');
				$tid = (string)($rules[$ev][$i]['target']['id'] ?? '');
				$rules[$ev][$i]['type'] = $tt;
				$rules[$ev][$i]['intent'] = $tt === 'intent' ? $tid : (string)($r['intent'] ?? '');
				$rules[$ev][$i]['app_id'] = $tt === 'app' ? $tid : (string)($r['app_id'] ?? '');
				$rules[$ev][$i]['payload'] = $rules[$ev][$i]['input'];
			}
		}
		return $rules;
	}

	public static function save_rules(array $rules) : bool {
		return update_option(self::OPTION_KEY, $rules, false);
	}

	public static function list_event_names() : array {
		$rules = self::all_rules();
		$names = array_keys($rules);
		sort($names);
		return $names;
	}

	/**
	 * Save (upsert) a rule.
	 * Rule format (v1):
	 *  - id: string
	 *  - enabled: bool
	 *  - target: {type:'intent'|'app', id:string}
	 *  - input: array (payload/input merged)
	 *  - conditions: array (optional)
	 *  - retries: int (optional, additional attempts)
	 *  - backoff_ms: int (optional)
	 *  - max_backoff_ms: int (optional)
	 */
	public static function upsert_rule(string $event, array $rule) : array {
		$event = preg_replace('/[^a-z0-9._\\-]/', '', strtolower((string)$event));
		if ($event === '') return ['ok'=>false,'error'=>'missing_event'];

		$id = isset($rule['id']) ? sanitize_text_field((string)$rule['id']) : '';
		if ($id === '') {
			$id = function_exists('wp_generate_uuid4') ? wp_generate_uuid4() : uniqid('if_rule_', true);
		}

		// Target v1 (supports legacy type/intent/app_id)
		$target = $rule['target'] ?? $rule;
		$target = class_exists('IF_Target') ? IF_Target::normalize($target) : (array)$target;
		if (($target['id'] ?? '') === '') {
			return ['ok'=>false,'error'=>'missing_target'];
		}

		// Input v1 (supports legacy payload/input)
		$input = [];
		if (isset($rule['input']) && is_array($rule['input'])) {
			$input = $rule['input'];
		}
		if (isset($rule['payload']) && is_array($rule['payload'])) {
			$input = $rule['payload'];
		}
		$enabled = !isset($rule['enabled']) ? true : (bool)$rule['enabled'];

		// Conditions (optional)
		$conditions = [];
		if (isset($rule['conditions']) && is_array($rule['conditions'])) {
			foreach ($rule['conditions'] as $c) {
				if (!is_array($c)) continue;
				$path = isset($c['path']) ? sanitize_text_field((string)$c['path']) : '';
				$op   = isset($c['op']) ? sanitize_key((string)$c['op']) : 'equals';
				$val  = $c['value'] ?? '';
				$neg  = !empty($c['not']);
				if ($path === '') continue;
				$conditions[] = [
					'path'  => $path,
					'op'    => $op,
					'value' => $val,
					'not'   => $neg,
				];
			}
		}

		// Retry policy (optional)
		$retries = isset($rule['retries']) ? (int)$rule['retries'] : 0;
		$retries = max(0, $retries);
		$backoff_ms = isset($rule['backoff_ms']) ? (int)$rule['backoff_ms'] : 250;
		$backoff_ms = max(0, $backoff_ms);
		$max_backoff_ms = isset($rule['max_backoff_ms']) ? (int)$rule['max_backoff_ms'] : 5000;
		$max_backoff_ms = max(0, $max_backoff_ms);

		$norm = [
			'id'      => $id,
			'enabled' => $enabled,
			'tag'     => isset($rule['tag']) ? sanitize_text_field((string)$rule['tag']) : '',
			'target'  => $target,
			'input'   => $input,
			'conditions'    => $conditions,
			'retries'       => $retries,
			'backoff_ms'    => $backoff_ms,
			'max_backoff_ms'=> $max_backoff_ms,
			// Legacy mirrors for UI compatibility (will be removed in v2 UI)
			'type'    => (string)($target['type'] ?? 'intent'),
			'intent'  => (string)(($target['type'] ?? 'intent') === 'intent' ? ($target['id'] ?? '') : ''),
			'app_id'  => (string)(($target['type'] ?? 'intent') === 'app' ? ($target['id'] ?? '') : ''),
			'payload' => $input,
		];

		$rules = self::all_rules();
		$list = (isset($rules[$event]) && is_array($rules[$event])) ? $rules[$event] : [];

		$found = false;
		foreach ($list as $i => $r) {
			if (is_array($r) && isset($r['id']) && (string)$r['id'] === $id) {
				$list[$i] = $norm;
				$found = true;
				break;
			}
		}
		if (!$found) {
			$list[] = $norm;
		}

		$rules[$event] = array_values($list);
		self::save_rules($rules);

		return ['ok'=>true,'event'=>$event,'rule'=>$norm,'created'=>!$found];
	}

	public static function delete_rule(string $event, string $rule_id) : array {
		$event = preg_replace('/[^a-z0-9._\\-]/', '', strtolower((string)$event));
		$rule_id = sanitize_text_field($rule_id);
		if ($event === '' || $rule_id === '') return ['ok'=>false,'error'=>'missing_params'];

		$rules = self::all_rules();
		$list = (isset($rules[$event]) && is_array($rules[$event])) ? $rules[$event] : [];

		$before = count($list);
		$list = array_values(array_filter($list, function($r) use ($rule_id) {
			return !(is_array($r) && isset($r['id']) && (string)$r['id'] === $rule_id);
		}));

		$rules[$event] = $list;
		self::save_rules($rules);

		return ['ok'=>true,'deleted'=>($before !== count($list))];
	}

	/**
	 * Emit an event: resolves rules and dispatches enabled rules.
	 *
	 * @return array{ok:bool,event:string,dispatched:array}
	 */
	public static function emit(string $event, array $payload = [], array $meta = []) : array {
		$event = preg_replace('/[^a-z0-9._\\-]/', '', strtolower((string)$event));
		if ($event === '') return ['ok'=>false,'error'=>'missing_event'];

		$rules = self::all_rules();
		$list = (isset($rules[$event]) && is_array($rules[$event])) ? $rules[$event] : [];
		$dispatched = [];

		foreach ($list as $rule) {
			if (!is_array($rule)) continue;
			if (isset($rule['enabled']) && !$rule['enabled']) continue;

			$type = (string)($rule['type'] ?? 'intent');
			$rule_id = (string)($rule['id'] ?? '');
			$target = isset($rule['target']) ? $rule['target'] : $rule;
			$target = class_exists('IF_Target') ? IF_Target::normalize($target) : (array)$target;
			$target_type = (string)($target['type'] ?? 'intent');
			$target_id   = (string)($target['id'] ?? '');
			$base_meta = array_merge($meta, [
				'event'   => $event,
				'rule_id' => $rule_id,
			]);
			// Conditions gate
			$conditions = (isset($rule['conditions']) && is_array($rule['conditions'])) ? $rule['conditions'] : [];
			if (!empty($conditions) && !self::conditions_match($conditions, $payload, $meta)) {
				$dispatched[] = [
					'rule_id' => $rule_id,
					'target'  => $target,
					'ok'      => true,
					'skipped' => true,
					'reason'  => 'conditions_not_met',
				];
				continue;
			}

			$runner_opts = [
				'source'       => $meta['source'] ?? 'events',
				'ensure_admin' => true,
				'audit'        => true,
				'retries'      => isset($rule['retries']) ? (int)$rule['retries'] : 0,
				'backoff_ms'   => isset($rule['backoff_ms']) ? (int)$rule['backoff_ms'] : 250,
				'max_backoff_ms' => isset($rule['max_backoff_ms']) ? (int)$rule['max_backoff_ms'] : 5000,
			];

			if ($target_id === '') continue;
			$rule_input = (isset($rule['input']) && is_array($rule['input'])) ? $rule['input'] : [];
			if (isset($rule['payload']) && is_array($rule['payload'])) {
				$rule_input = $rule['payload'];
			}
			$resolved_input = self::resolve_payload_templates($rule_input, $payload, $meta);
			$out = class_exists('IF_Runner')
				? IF_Runner::run_target($target, $resolved_input, $base_meta, $runner_opts)
				: ['ok'=>false,'error'=>'runner_missing'];

			$dispatched[] = [
				'rule_id' => $rule_id,
				'target'  => $target,
				'ok'      => !empty($out['ok']),
				'error'   => (string)($out['error'] ?? ''),
				'out'     => $out,
			];
			continue;
		}

		if (class_exists('IF_FlowTriggers')) {
			IF_FlowTriggers::on_event($event, $payload, $meta);
		}

		return [
			'ok'         => true,
			'event'      => $event,
			'dispatched' => $dispatched,
		];
	}

	// ---------------- WP hook adapters ----------------

	public static function on_save_post($post_id, $post, $update) : void {
		// Ignore revisions/autosaves
		if (wp_is_post_revision($post_id)) return;
		if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;

		$post_type = is_object($post) ? (string)$post->post_type : '';
		$status    = is_object($post) ? (string)$post->post_status : '';

		$payload = [
			'post_id'   => (int)$post_id,
			'post_type' => $post_type,
			'status'    => $status,
			'update'    => (bool)$update,
			'ts'        => time(),
		];
		$meta = [
			'source'  => 'wp.save_post',
			'user_id' => get_current_user_id(),
		];

		self::emit($update ? 'post.updated' : 'post.created', $payload, $meta);
	}

	public static function on_upgrader_process_complete($upgrader, $options) : void {
		if (!is_array($options)) return;
		$action = (string)($options['action'] ?? '');
		$type   = (string)($options['type'] ?? '');
		if ($action !== 'update' || $type !== 'plugin') return;

		$plugins = $options['plugins'] ?? [];
		if (!is_array($plugins)) $plugins = [];

		foreach ($plugins as $plugin_file) {
			$payload = [
				'plugin' => (string)$plugin_file,
				'ts'     => time(),
			];
			$meta = [
				'source'  => 'wp.upgrader',
				'user_id' => get_current_user_id(),
			];
			self::emit('plugin.updated', $payload, $meta);
		}
	}

	// ---------------- Template resolver (very small) ----------------

	/**
	 * Evaluate rule conditions against event payload/meta.
	 *
	 * Supported ops (v1):
	 *  - equals
	 *  - contains (string contains)
	 *  - regex (PCRE, pattern in value)
	 *  - in (value is array, or comma-separated string)
	 *
	 * Paths:
	 *  - payload.xxx
	 *  - meta.xxx
	 */
	private static function conditions_match(array $conditions, array $payload, array $meta) : bool {
		foreach ($conditions as $c) {
			if (!is_array($c)) continue;
			$path = isset($c['path']) ? (string)$c['path'] : '';
			$op   = isset($c['op']) ? (string)$c['op'] : 'equals';
			$neg  = !empty($c['not']);
			$expected = $c['value'] ?? null;

			$actual = self::get_by_path($path, $payload, $meta);
			$pass = self::eval_condition($actual, $op, $expected);
			if ($neg) $pass = !$pass;
			if (!$pass) return false;
		}
		return true;
	}

	private static function get_by_path(string $path, array $payload, array $meta) {
		$path = trim($path);
		if ($path === '') return null;
		if (strpos($path, 'payload.') === 0) {
			$key = substr($path, 8);
			return $payload[$key] ?? null;
		}
		if (strpos($path, 'meta.') === 0) {
			$key = substr($path, 5);
			return $meta[$key] ?? null;
		}
		// fallback: allow direct key lookup in payload
		return $payload[$path] ?? null;
	}

	private static function eval_condition($actual, string $op, $expected) : bool {
		$op = sanitize_key($op);
		if ($op === '') $op = 'equals';

		if ($op === 'equals') {
			return (string)$actual === (string)$expected;
		}
		if ($op === 'contains') {
			return (strpos((string)$actual, (string)$expected) !== false);
		}
		if ($op === 'regex') {
			$pattern = (string)$expected;
			if ($pattern === '') return false;
			return @preg_match($pattern, (string)$actual) === 1;
		}
		if ($op === 'in') {
			$list = $expected;
			if (is_string($list)) {
				$list = array_map('trim', explode(',', $list));
			}
			if (!is_array($list)) return false;
			return in_array((string)$actual, array_map('strval', $list), true);
		}

		return false;
	}

	private static function resolve_payload_templates($data, array $eventPayload, array $meta) {
		// Only supports replacing strings like "{{payload.post_id}}" or "{{meta.user_id}}".
		if (is_string($data)) {
			return self::resolve_string_template($data, $eventPayload, $meta);
		}
		if (is_array($data)) {
			$out = [];
			foreach ($data as $k => $v) {
				$out[$k] = self::resolve_payload_templates($v, $eventPayload, $meta);
			}
			return $out;
		}
		return $data;
	}

	private static function resolve_string_template(string $s, array $eventPayload, array $meta) : string {
		return preg_replace_callback('/\{\{\s*(payload|meta)\.([a-zA-Z0-9_\-]+)\s*\}\}/', function($m) use ($eventPayload, $meta) {
			$root = $m[1];
			$key  = $m[2];
			$val = null;
			if ($root === 'payload') { $val = $eventPayload[$key] ?? null; }
			if ($root === 'meta')    { $val = $meta[$key] ?? null; }
			if (is_scalar($val) || $val === null) return (string)($val ?? '');
			return wp_json_encode($val);
		}, $s);
	}

    // ---- New WP hook adapters (v2) ----

    public static function on_user_register( int $user_id ) : void {
        $user = get_userdata( $user_id );
        self::emit( 'user.registered', [
            'user_id'    => $user_id,
            'user_email' => $user ? (string) $user->user_email : '',
            'user_login' => $user ? (string) $user->user_login : '',
            'ts'         => time(),
        ], [ 'source' => 'wp.user_register' ] );
    }

    public static function on_user_login( string $user_login, $user ) : void {
        self::emit( 'user.login', [
            'user_id'    => $user ? (int) $user->ID : 0,
            'user_login' => $user_login,
            'ts'         => time(),
        ], [ 'source' => 'wp.wp_login' ] );
    }

    public static function on_user_login_failed( string $user_login ) : void {
        self::emit( 'user.login_failed', [
            'user_login' => $user_login,
            'ts'         => time(),
        ], [ 'source' => 'wp.wp_login_failed' ] );
    }

    public static function on_user_logout( int $user_id ) : void {
        self::emit( 'user.logout', [
            'user_id' => $user_id,
            'ts'      => time(),
        ], [ 'source' => 'wp.wp_logout' ] );
    }

    public static function on_profile_update( int $user_id, $old_data ) : void {
        self::emit( 'user.profile_updated', [
            'user_id' => $user_id,
            'ts'      => time(),
        ], [ 'source' => 'wp.profile_update', 'user_id' => get_current_user_id() ] );
    }

    public static function on_password_reset( $user, string $new_pass ) : void {
        self::emit( 'user.password_reset', [
            'user_id'    => $user ? (int) $user->ID : 0,
            'user_login' => $user ? (string) $user->user_login : '',
            'ts'         => time(),
        ], [ 'source' => 'wp.password_reset' ] );
    }

    public static function on_user_deleted( int $user_id ) : void {
        self::emit( 'user.deleted', [
            'user_id' => $user_id,
            'ts'      => time(),
        ], [ 'source' => 'wp.delete_user' ] );
    }

    public static function on_user_role_changed( int $user_id, string $role, array $old_roles ) : void {
        self::emit( 'user.role_changed', [
            'user_id'   => $user_id,
            'new_role'  => $role,
            'old_roles' => $old_roles,
            'ts'        => time(),
        ], [ 'source' => 'wp.set_user_role' ] );
    }

    public static function on_delete_post( int $post_id ) : void {
        if ( wp_is_post_revision( $post_id ) ) return;
        $post = get_post( $post_id );
        self::emit( 'post.deleted', [
            'post_id'   => $post_id,
            'post_type' => $post ? (string) $post->post_type : '',
            'ts'        => time(),
        ], [ 'source' => 'wp.delete_post', 'user_id' => get_current_user_id() ] );
    }

    public static function on_trash_post( int $post_id ) : void {
        if ( wp_is_post_revision( $post_id ) ) return;
        $post = get_post( $post_id );
        self::emit( 'post.trashed', [
            'post_id'   => $post_id,
            'post_type' => $post ? (string) $post->post_type : '',
            'ts'        => time(),
        ], [ 'source' => 'wp.wp_trash_post', 'user_id' => get_current_user_id() ] );
    }

    public static function on_comment_post( int $comment_id, $approved, array $data ) : void {
        self::emit( 'comment.posted', [
            'comment_id' => $comment_id,
            'post_id'    => (int) ( $data['comment_post_ID'] ?? 0 ),
            'approved'   => $approved,
            'author'     => (string) ( $data['comment_author'] ?? '' ),
            'ts'         => time(),
        ], [ 'source' => 'wp.comment_post' ] );
    }

    public static function on_post_status_changed( string $new_status, string $old_status, $post ) : void {
        if ( ! is_object( $post ) ) return;
        if ( wp_is_post_revision( $post->ID ) ) return;
        if ( $new_status === $old_status ) return;
        self::emit( 'post.status_changed', [
            'post_id'    => (int) $post->ID,
            'post_type'  => (string) $post->post_type,
            'old_status' => $old_status,
            'new_status' => $new_status,
            'ts'         => time(),
        ], [ 'source' => 'wp.transition_post_status', 'user_id' => get_current_user_id() ] );
    }

}
