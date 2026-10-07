<?php
if (!defined('ABSPATH')) { exit; }

return function(IF_IntentRegistry $reg) : void {

  $reg->register(
    'server.posts.get',
    function(array $payload, array $meta, array $ctx) : array {

      $post_id = isset($payload['post_id']) ? (int)$payload['post_id'] : 0;
      if ($post_id <= 0) {
        return ['ok' => false, 'error' => 'missing_post_id'];
      }

      $p = get_post($post_id);
      if (!$p) {
        return ['ok' => false, 'error' => 'not_found'];
      }

      // Permiso por objeto
      if (!current_user_can('edit_post', $post_id)) {
        return ['ok'=>false, 'blocked'=>true, 'reason'=>'capability_denied'];
      }

      return [
        'post' => [
          'ID'     => (int)$p->ID,
          'type'   => (string)$p->post_type,
          'status' => (string)$p->post_status,
          'title'  => (string)get_the_title($p),
          'edit'   => get_edit_post_link($p->ID, 'raw'),
          'view'   => get_permalink($p->ID),
        ]
      ];
    },
    [
      'capability'    => 'edit_posts',
      'unsafe'        => false,
      'requires_live' => false,
      'tags'          => ['posts'],
    ]
  );

};
