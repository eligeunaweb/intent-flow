<?php
if (!defined('ABSPATH')) { exit; }

return function(IF_IntentRegistry $reg) : void {

  $reg->register(
    'server.audit.export',
    function(array $payload, array $meta, array $ctx) : array {

      if (!class_exists('IF_AuditLog')) {
        return ['ok'=>false,'error'=>'audit_not_available'];
      }

      $limit = isset($payload['limit']) ? (int)$payload['limit'] : 50;
      if ($limit < 1) $limit = 1;
      if ($limit > 1000) $limit = 1000;

      $events = IF_AuditLog::latest($limit);

      return [
        'count' => count($events),
        'events' => $events,
        'exported_at' => time(),
      ];
    },
    [
      'capability' => 'manage_options',
      'scope'      => 'audit',
      'unsafe'     => false,
      'requires_live' => false,
    ]
  );

};
