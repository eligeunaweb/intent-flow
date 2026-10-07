<?php
if (!defined('ABSPATH')) { exit; }

final class IF_Scheduler {
    const OPTION_KEY = 'if_scheduler_jobs';
    const LOCK_KEY   = 'if_scheduler_tick_lock_v1';

    public static function init() : void {
        add_filter('cron_schedules', [__CLASS__, 'cron_schedules']);
        add_action('inteflow_scheduler_tick', [__CLASS__, 'tick']);
        if (!wp_next_scheduled('inteflow_scheduler_tick')) {
            wp_schedule_event(time()+60, 'if_minute', 'inteflow_scheduler_tick');
        }
    }

    public static function cron_schedules($schedules) {
        $schedules['if_minute'] = [
            'interval' => 60,
            'display' => 'Intent Flow every minute',
        ];
        return $schedules;
    }

    public static function all() : array {
        $v = get_option(self::OPTION_KEY, []);
		$jobs = is_array($v) ? $v : [];
		// Normalize targets lazily (backward compatible)
		foreach ($jobs as $id => $job) {
			if (!is_array($job)) continue;
			if (!isset($job['target']) || !is_array($job['target'])) {
				$jobs[$id]['target'] = class_exists('IF_Target')
					? IF_Target::normalize($job)
					: [ 'type' => (string)($job['type'] ?? 'intent'), 'id' => (string)($job['intent'] ?? '') ];
			}
			// Keep legacy mirrors for UI
			$jobs[$id]['type']   = (string)($jobs[$id]['target']['type'] ?? ($job['type'] ?? 'intent'));
			$jobs[$id]['intent'] = $jobs[$id]['type'] === 'intent' ? (string)($jobs[$id]['target']['id'] ?? '') : '';
			$jobs[$id]['app_id'] = $jobs[$id]['type'] === 'app' ? (string)($jobs[$id]['target']['id'] ?? '') : (string)($job['app_id'] ?? '');
		}
		return $jobs;
    }

    public static function save(array $jobs) : bool {
        return update_option(self::OPTION_KEY, $jobs, false);
    }

    public static function tick() : void {
        // Prevent overlapping ticks (external cron + wp-cron edge cases)
        if (get_transient(self::LOCK_KEY)) {
            return;
        }
        set_transient(self::LOCK_KEY, 1, 55);

        $jobs = self::all();
        $now = time();
        foreach ($jobs as $id => $job) {
            if (empty($job['enabled'])) continue;
            if (($job['next_run'] ?? 0) > $now) continue;

            $run = self::execute_job($id, $job, 'cron');

            // Persist minimal debugging info into the job record.
            $jobs[$id]['last_run']    = $run['finished_at'] ?? $now;
            $jobs[$id]['last_result'] = $run['result'] ?? ['ok'=>false,'error'=>'missing_result'];
            $jobs[$id]['last_status'] = !empty($run['ok']) ? 'ok' : 'error';
            $jobs[$id]['last_error']  = !empty($run['ok']) ? '' : (string)($run['error'] ?? 'unknown');
            $jobs[$id]['next_run']    = $now + (int)($job['interval'] ?? 3600);
        }
        self::save($jobs);

        delete_transient(self::LOCK_KEY);
    }

    /**
     * Execute a job by id and persist run history.
     *
     * @return array Run record (includes result).
     */
    public static function run_job(string $job_id, string $source = 'ui') : array {
        $jobs = self::all();
        if (!isset($jobs[$job_id]) || !is_array($jobs[$job_id])) {
            return ['ok'=>false,'error'=>'job_not_found','job_id'=>$job_id];
        }

        $job = $jobs[$job_id];
        $run = self::execute_job($job_id, $job, $source);

        // Update job timing similarly to a scheduled tick
        $now = time();
        $jobs[$job_id]['last_run']    = $run['finished_at'] ?? $now;
        $jobs[$job_id]['last_result'] = $run['result'] ?? ['ok'=>false,'error'=>'missing_result'];
        $jobs[$job_id]['last_status'] = !empty($run['ok']) ? 'ok' : 'error';
        $jobs[$job_id]['last_error']  = !empty($run['ok']) ? '' : (string)($run['error'] ?? 'unknown');
        $jobs[$job_id]['next_run']    = $now + (int)($job['interval'] ?? 3600);
        self::save($jobs);

        return $run;
    }

    private static function execute_job(string $job_id, array $job, string $source) : array {
        $started_at = time();
        $t0 = microtime(true);

		// Target v1 normalization (keeps backward compatibility)
		$target = isset($job['target']) ? $job['target'] : null;
		if (!$target) {
			// Legacy job formats
			$target = [
				'type' => (string)($job['type'] ?? ''),
				'intent' => (string)($job['intent'] ?? ''),
				'app_id' => (string)($job['app_id'] ?? ''),
			];
		}
		$target = class_exists('IF_Target') ? IF_Target::normalize($target) : (array)$target;
		$input = (array)($job['input'] ?? $job['payload'] ?? []);

		$out = class_exists('IF_Runner')
			? IF_Runner::run_target(
				$target,
				$input,
				['job_id' => $job_id],
				[
					'source'       => 'scheduler.' . $source,
					'ensure_admin' => true,
					'audit'        => true,
					// Optional retry policy per job
					'retries'      => isset($job['retries']) ? (int)$job['retries'] : 0,
					'backoff_ms'   => isset($job['backoff_ms']) ? (int)$job['backoff_ms'] : 250,
					'max_backoff_ms' => isset($job['max_backoff_ms']) ? (int)$job['max_backoff_ms'] : 5000,
				]
			)
			: ['ok'=>false,'error'=>'runner_missing'];

        $finished_at = time();
		$duration_ms = (int) round((microtime(true) - $t0) * 1000);

        $run = [
            'run_id'      => function_exists('wp_generate_uuid4') ? wp_generate_uuid4() : uniqid('if_run_', true),
            'job_id'      => $job_id,
            'source'      => $source,
			'target'      => $target,
            'started_at'  => $started_at,
            'finished_at' => $finished_at,
            'duration_ms' => $duration_ms,
			'ok'          => !empty($out['ok']),
			'error'       => (string)($out['error'] ?? ''),
			'message'     => (string)($out['message'] ?? ''),
            'result'      => $out,
        ];

        if (class_exists('IF_SchedulerRuns')) {
            IF_SchedulerRuns::append($job_id, $run);
        }

        return $run;
    }
}
