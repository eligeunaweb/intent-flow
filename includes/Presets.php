<?php
if (!defined('ABSPATH')) { exit; }

final class IF_Presets {
    const OPTION_KEY = 'if_presets_v1';
    const MAX_ITEMS = 200;

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

    /** @return array{ok:bool, id?:string, error?:string, preset?:array} */
    public static function save(array $preset) : array {
        $name = isset($preset['name']) ? sanitize_text_field((string)$preset['name']) : '';
        $intentId = isset($preset['intentId']) ? sanitize_text_field((string)$preset['intentId']) : '';
        $payload = (isset($preset['payload']) && is_array($preset['payload'])) ? $preset['payload'] : [];

        if ($name === '') return ['ok'=>false,'error'=>'missing_name'];
        if ($intentId === '') return ['ok'=>false,'error'=>'missing_intentId'];

        $all = self::all();
        if (count($all) >= self::MAX_ITEMS && empty($preset['id'])) {
            return ['ok'=>false,'error'=>'too_many_presets','max'=>self::MAX_ITEMS];
        }

        $id = isset($preset['id']) ? sanitize_key((string)$preset['id']) : '';
        if ($id === '') $id = self::make_id($name);

        $now = time();

        $all[$id] = [
            'id' => $id,
            'name' => $name,
            'intentId' => $intentId,
            'payload' => $payload,
            'created_at' => isset($all[$id]['created_at']) ? (int)$all[$id]['created_at'] : $now,
            'updated_at' => $now,
        ];

        update_option(self::OPTION_KEY, $all, false);

        return ['ok'=>true,'id'=>$id,'preset'=>$all[$id]];
    }

    /** @return array{ok:bool, updated:int} */
    public static function import(array $presets, bool $overwrite = true) : array {
        $all = self::all();
        $updated = 0;

        foreach ($presets as $p) {
            if (!is_array($p)) continue;
            $id = isset($p['id']) ? sanitize_key((string)$p['id']) : '';
            $name = isset($p['name']) ? sanitize_text_field((string)$p['name']) : '';
            $intentId = isset($p['intentId']) ? sanitize_text_field((string)$p['intentId']) : '';
            $payload = (isset($p['payload']) && is_array($p['payload'])) ? $p['payload'] : [];

            if ($name === '' || $intentId === '') continue;
            if ($id === '') $id = self::make_id($name);

            if (!$overwrite && isset($all[$id])) continue;

            $now = time();
            $all[$id] = [
                'id'=>$id,
                'name'=>$name,
                'intentId'=>$intentId,
                'payload'=>$payload,
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
        if ($base === '') $base = 'preset';
        $suffix = substr(wp_hash($base . '|' . microtime(true) . '|' . wp_rand()), 0, 6);
        return $base . '-' . $suffix;
    }
}
