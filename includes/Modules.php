<?php
if (!defined('ABSPATH')) { exit; }

/**
 * Module manager (internal integrations).
 *
 * - Modules live under /modules/<slug>/
 * - Each module provides manifest.json and optional module.php
 * - Enabled modules are stored in option: if_enabled_modules (autoload=false)
 * - Loader is fail-safe: a broken module is marked error and skipped (does not crash WP).
 */
final class IF_Modules {
    const OPT_ENABLED   = 'if_enabled_modules';
    const OPT_ERRORS    = 'if_module_errors_v1';
    const OPT_SAFE_MODE = 'if_modules_safe_mode_v1';

    public static function ensure_defaults() : void {
        $all = array_keys(self::discover());

        $enabled = get_option(self::OPT_ENABLED, null);
        if (is_array($enabled)) {
            // Upgrade path: auto-add newly discovered modules so "everything ON" stays true.
            $enabled = array_values(array_unique(array_merge($enabled, $all)));
            update_option(self::OPT_ENABLED, array_values(array_filter(array_map('sanitize_key', $enabled))), false);

            if (get_option(self::OPT_ERRORS, null) === null) add_option(self::OPT_ERRORS, [], '', false);
            if (get_option(self::OPT_SAFE_MODE, null) === null) add_option(self::OPT_SAFE_MODE, false, '', false);
            return;
        }

        // First install: enable ALL modules.
        add_option(self::OPT_ENABLED, $all, '', false);
        add_option(self::OPT_ERRORS, [], '', false);
        add_option(self::OPT_SAFE_MODE, false, '', false);
    }

    public static function enabled() : array {
        $enabled = get_option(self::OPT_ENABLED, []);
        if (!is_array($enabled)) $enabled = [];
        $enabled = array_values(array_unique(array_filter(array_map('sanitize_key', $enabled))));
        return $enabled;
    }

    public static function set_enabled(array $slugs) : void {
        $slugs = array_values(array_unique(array_filter(array_map('sanitize_key', $slugs))));
        update_option(self::OPT_ENABLED, $slugs, false);
    }

    public static function is_safe_mode() : bool {
        return (bool) get_option(self::OPT_SAFE_MODE, false);
    }

    public static function set_safe_mode(bool $on) : void {
        update_option(self::OPT_SAFE_MODE, $on, false);
    }

    public static function errors() : array {
        $e = get_option(self::OPT_ERRORS, []);
        return is_array($e) ? $e : [];
    }

    public static function set_error(string $slug, string $message) : void {
        $slug = sanitize_key($slug);
        if (!$slug) return;
        $e = self::errors();
        $e[$slug] = [
            'ts' => time(),
            'message' => $message,
        ];
        update_option(self::OPT_ERRORS, $e, false);
    }

    public static function clear_error(string $slug) : void {
        $slug = sanitize_key($slug);
        $e = self::errors();
        if (isset($e[$slug])) {
            unset($e[$slug]);
            update_option(self::OPT_ERRORS, $e, false);
        }
    }

    public static function module_dirs() : array {
        $dirs = [];
        $internal = trailingslashit(IF_PLUGIN_DIR) . 'modules/';
        if (is_dir($internal)) $dirs[] = $internal;

        // Future: allow user-installed modules in uploads
        // $upload = wp_upload_dir();
        // $external = trailingslashit($upload['basedir']) . 'mmi-modules/';
        // if (is_dir($external)) $dirs[] = $external;

        return $dirs;
    }

    public static function discover() : array {
        $mods = [];
        foreach (self::module_dirs() as $root) {
            $children = @glob($root . '*', GLOB_ONLYDIR);
            if (!$children) continue;
            foreach ($children as $dir) {
                $slug = sanitize_key(basename($dir));
                if (!$slug) continue;

                $manifest_file = trailingslashit($dir) . 'manifest.json';
                $manifest = [
                    'slug' => $slug,
                    'name' => ucfirst($slug),
                    'description' => '',
                    'version' => '',
                    'requires' => [],
                ];
                if (file_exists($manifest_file)) {
                    $raw = file_get_contents($manifest_file);
                    $json = json_decode($raw, true);
                    if (is_array($json)) {
                        $manifest = array_merge($manifest, $json);
                    }
                }
                $manifest['slug'] = $slug;
                $manifest['dir']  = trailingslashit($dir);
                $manifest['file'] = trailingslashit($dir) . 'module.php';

                $mods[$slug] = $manifest;
            }
        }
        ksort($mods);
        return $mods;
    }

    public static function dep_status(array $manifest) : array {
        $requires = isset($manifest['requires']) && is_array($manifest['requires']) ? $manifest['requires'] : [];
        $missing = [];
        foreach ($requires as $req) {
            $req = (string)$req;
            if ($req === 'woocommerce' && !class_exists('WooCommerce')) $missing[] = 'woocommerce';
            if ($req === 'edd' && !class_exists('Easy_Digital_Downloads')) $missing[] = 'edd';
        }
        return [
            'ok' => empty($missing),
            'missing' => $missing,
        ];
    }

    public static function load_enabled() : void {
        self::ensure_defaults();

        // Avoid duplicate Telegram when external add-on plugin is present
        $external_telegram = defined('IF_TELEGRAM_MODULE_VERSION');

        $all = self::discover();
        $enabled = self::enabled();

        // In safe mode, load only a minimal curated subset.
        if (self::is_safe_mode()) {
            $enabled = array_values(array_intersect($enabled, ['telegram','http','slack_webhook','webhook_in','wordpress','email']));
        }

        foreach ($enabled as $slug) {
            if ($slug === 'telegram' && $external_telegram) continue;
            if (!isset($all[$slug])) continue;

            $m = $all[$slug];
            $dep = self::dep_status($m);
            if (!$dep['ok']) {
                self::set_error($slug, 'missing_dependency: ' . implode(',', $dep['missing']));
                continue;
            }

            $file = $m['file'];
            if (!file_exists($file)) continue;

            try {
                require_once $file;
                self::clear_error($slug);
            } catch (Throwable $e) {
                // Do NOT crash WP. Mark module error and continue.
                self::set_error($slug, $e->getMessage());
            }
        }
    }
}

// -------- Intents for module catalog UI --------
add_action('if_register_intents', function($reg){
    if (!is_object($reg) || !method_exists($reg, 'register')) return;

    $reg->register('server.modules.list', function($payload, $meta, $ctx){
        $all = IF_Modules::discover();
        $enabled = IF_Modules::enabled();
        $errs = IF_Modules::errors();
        $safe = IF_Modules::is_safe_mode();

        $items = [];
        foreach ($all as $slug => $m) {
            $dep = IF_Modules::dep_status($m);
            $items[] = [
                'slug' => $slug,
                'name' => (string)($m['name'] ?? $slug),
                'description' => (string)($m['description'] ?? ''),
                'version' => (string)($m['version'] ?? ''),
                'enabled' => in_array($slug, $enabled, true),
                'dependency_ok' => (bool)$dep['ok'],
                'missing' => $dep['missing'],
                'error' => isset($errs[$slug]) ? ($errs[$slug]['message'] ?? '') : '',
                'safe_mode' => $safe,
            ];
        }

        return [ 'ok' => true, 'items' => $items, 'safe_mode' => $safe ];
    }, ['capability' => 'manage_options']);

    $reg->register('server.modules.toggle', function($payload, $meta, $ctx){
        $slug = isset($payload['slug']) ? sanitize_key((string)$payload['slug']) : '';
        $enabledFlag = array_key_exists('enabled', $payload) ? (bool)$payload['enabled'] : null;
        if (!$slug || $enabledFlag === null) return [ 'ok'=>false, 'error'=>'bad_request', 'message'=>'slug/enabled required' ];

        $all = IF_Modules::discover();
        if (!isset($all[$slug])) return [ 'ok'=>false, 'error'=>'not_found', 'message'=>'module not found' ];

        $enabled = IF_Modules::enabled();
        $isEnabled = in_array($slug, $enabled, true);

        if ($enabledFlag && !$isEnabled) $enabled[] = $slug;
        if (!$enabledFlag && $isEnabled) $enabled = array_values(array_filter($enabled, function($s) use ($slug){ return $s !== $slug; }));

        IF_Modules::set_enabled($enabled);

        // If enabling, clear previous error marker (will be re-set if load fails)
        if ($enabledFlag) IF_Modules::clear_error($slug);

        return [ 'ok'=>true, 'enabled' => IF_Modules::enabled() ];
    }, ['capability' => 'manage_options']);

    $reg->register('server.modules.safe_mode', function($payload, $meta, $ctx){
        $on = array_key_exists('on', $payload) ? (bool)$payload['on'] : null;
        if ($on === null) return [ 'ok'=>false, 'error'=>'bad_request', 'message'=>'on required' ];
        IF_Modules::set_safe_mode($on);
        return [ 'ok'=>true, 'safe_mode' => IF_Modules::is_safe_mode() ];
    }, ['capability' => 'manage_options']);

}, 5);
