<?php
if (!defined('ABSPATH')) { exit; }

return function(IF_IntentRegistry $reg) : void {

  $reg->register(
    'server.diagnostics.health',
    function(array $payload, array $meta, array $ctx) use ($reg) : array {

      $settings = class_exists('IF_Settings') ? IF_Settings::get() : [];
      $scopes   = class_exists('IF_Scopes') ? IF_Scopes::get_map() : [];
      $user_id  = (int)($ctx['user_id'] ?? get_current_user_id());

      $roles = [];
      $u = get_userdata($user_id);
      if ($u && !empty($u->roles)) {
        $roles = array_values($u->roles);
      }

      $audit_ok = class_exists('IF_AuditLog');

      $registry_count = 0;
      if (method_exists($reg, 'getPolicy')) {
        // hack seguro: contamos intents registrados
        $ref = new ReflectionClass($reg);
        if ($ref->hasProperty('map')) {
          $prop = $ref->getProperty('map');
          $prop->setAccessible(true);
          $map = $prop->getValue($reg);
          if (is_array($map)) {
            $registry_count = count($map);
          }
        }
      }

      return [
        'version' => defined('IF_VERSION') ? IF_VERSION : 'unknown',

        'user' => [
          'id'    => $user_id,
          'roles' => $roles,
        ],

        'flags' => [
          'safe_mode' => !empty($settings['safe_mode']),
          'dry_run'   => !empty($settings['dry_run']),
        ],

        'modules' => [
          'registry' => $registry_count,
          'audit'    => $audit_ok,
          'scopes'   => !empty($scopes),
          'safelist' => class_exists('IF_PluginSafelist'),
        ],

        'scopes_by_role' => $scopes,

        'timestamp' => time(),
        'site' => [
          'url' => site_url(),
          'wp_version' => get_bloginfo('version'),
        ],
      ];
    },
    [
      'capability' => 'manage_options',
      'scope'      => 'diagnostics',
      'unsafe'     => false,
      'requires_live' => false,
    ]
  );

};
