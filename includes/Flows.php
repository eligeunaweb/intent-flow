<?php
if (!defined('ABSPATH')) { exit; }

final class IF_Flows {
    const OPTION_KEY = 'if_flows_v1';
    const MAX_ITEMS = 100;

    /** @return array<string, array> */
    public static function all() : array {
        $v = get_option(self::OPTION_KEY, []);
        return is_array($v) ? $v : [];
    }

    public static function get(string $id) : ?array {
        $all = self::all();
        return isset($all[$id]) && is_array($all[$id]) ? $all[$id] : null;
    }

    public static function delete(string $id) : bool {
        $all = self::all();
        if (!isset($all[$id])) return false;
        unset($all[$id]);
        return update_option(self::OPTION_KEY, $all, false) ? true : false;
    }

    /** @return array{ok:bool, id?:string, error?:string, flow?:array} */
    public static function save(array $flow) : array {
        $name = isset($flow['name']) ? sanitize_text_field((string)$flow['name']) : '';
        $steps = isset($flow['steps']) && is_array($flow['steps']) ? $flow['steps'] : [];

        if ($name === '') return ['ok'=>false,'error'=>'missing_name'];
        if (count($steps) === 0) return ['ok'=>false,'error'=>'missing_steps'];

        $all = self::all();
        if (count($all) >= self::MAX_ITEMS && empty($flow['id'])) {
            return ['ok'=>false,'error'=>'too_many_flows','max'=>self::MAX_ITEMS];
        }

        $id = isset($flow['id']) ? sanitize_key((string)$flow['id']) : '';
        if ($id === '') $id = self::make_id($name);

        $normSteps = [];
        foreach ($steps as $s) {
            if (!is_array($s)) continue;
            $sid = isset($s['id']) ? sanitize_text_field((string)$s['id']) : '';
            if ($sid === '') continue;
            $pl = (isset($s['payload']) && is_array($s['payload'])) ? $s['payload'] : [];
            $mt = (isset($s['meta']) && is_array($s['meta'])) ? $s['meta'] : [];
            $normSteps[] = ['id'=>$sid,'payload'=>$pl,'meta'=>$mt];
        }

        if (count($normSteps) === 0) return ['ok'=>false,'error'=>'missing_steps'];

        $now = time();
        $all[$id] = [
            'id'=>$id,
            'name'=>$name,
            'steps'=>$normSteps,
            'created_at'=> isset($all[$id]['created_at']) ? (int)$all[$id]['created_at'] : $now,
            'updated_at'=> $now,
        ];
        update_option(self::OPTION_KEY, $all, false);

        return ['ok'=>true,'id'=>$id,'flow'=>$all[$id]];
    }

    /** @return array{ok:bool, updated:int} */
    public static function import(array $flows, bool $overwrite = true) : array {
        $all = self::all();
        $updated = 0;

        foreach ($flows as $f) {
            if (!is_array($f)) continue;
            $id = isset($f['id']) ? sanitize_key((string)$f['id']) : '';
            $name = isset($f['name']) ? sanitize_text_field((string)$f['name']) : '';
            $steps = isset($f['steps']) && is_array($f['steps']) ? $f['steps'] : [];
            if ($name === '' || count($steps)===0) continue;
            if ($id === '') $id = self::make_id($name);
            if (!$overwrite && isset($all[$id])) continue;

            // reuse save-like normalization
            $normSteps = [];
            foreach ($steps as $s) {
                if (!is_array($s)) continue;
                $sid = isset($s['id']) ? sanitize_text_field((string)$s['id']) : '';
                if ($sid === '') continue;
                $pl = (isset($s['payload']) && is_array($s['payload'])) ? $s['payload'] : [];
                $mt = (isset($s['meta']) && is_array($s['meta'])) ? $s['meta'] : [];
                $normSteps[] = ['id'=>$sid,'payload'=>$pl,'meta'=>$mt];
            }
            if (count($normSteps)===0) continue;

            $now = time();
            $all[$id] = [
                'id'=>$id,
                'name'=>$name,
                'steps'=>$normSteps,
                'created_at'=> isset($all[$id]['created_at']) ? (int)$all[$id]['created_at'] : $now,
                'updated_at'=> $now,
            ];
            $updated++;
        }

        update_option(self::OPTION_KEY, $all, false);
        return ['ok'=>true,'updated'=>$updated];
    }

    private static function make_id(string $name) : string {
        $base = sanitize_key($name);
        if ($base === '') $base = 'flow';
        $suffix = substr(wp_hash($base . '|' . microtime(true) . '|' . wp_rand()), 0, 6);
        return $base . '-' . $suffix;
    }
}
