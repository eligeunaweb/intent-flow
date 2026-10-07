<?php
if (!defined('ABSPATH')) { exit; }

/**
 * Persistent per-job run history for the Scheduler.
 *
 * Storage: WordPress option (autoload=false).
 * Shape:
 *  [job_id => [ run, run, ... ]]
 *
 * A run is:
 *  [
 *    'run_id'      => string,
 *    'job_id'      => string,
 *    'source'      => 'cron'|'ui'|'api'|...,
 *    'intent'      => string,
 *    'started_at'  => int,
 *    'finished_at' => int,
 *    'duration_ms' => int,
 *    'ok'          => bool,
 *    'error'       => string,
 *    'message'     => string,
 *  ]
 */
final class IF_SchedulerRuns {
    const OPTION_KEY = 'if_scheduler_runs_v1';
    const PER_JOB_LIMIT = 50;

    public static function all() : array {
        $v = get_option(self::OPTION_KEY, []);
        return is_array($v) ? $v : [];
    }

    public static function list_for(string $job_id, int $limit = 20) : array {
        $all = self::all();
        $runs = $all[$job_id] ?? [];
        if (!is_array($runs)) { return []; }
        $limit = max(1, min(200, $limit));
        return array_slice($runs, 0, $limit);
    }

    public static function append(string $job_id, array $run) : void {
        $all = self::all();
        if (!isset($all[$job_id]) || !is_array($all[$job_id])) {
            $all[$job_id] = [];
        }

        array_unshift($all[$job_id], $run);
        if (count($all[$job_id]) > self::PER_JOB_LIMIT) {
            $all[$job_id] = array_slice($all[$job_id], 0, self::PER_JOB_LIMIT);
        }

        update_option(self::OPTION_KEY, $all, false);
    }

    public static function clear(string $job_id) : void {
        $all = self::all();
        unset($all[$job_id]);
        update_option(self::OPTION_KEY, $all, false);
    }
}
