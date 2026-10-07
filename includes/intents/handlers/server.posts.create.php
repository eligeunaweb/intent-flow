<?php
if (!defined('ABSPATH')) { exit; }

return function(IF_IntentRegistry $reg) : void {

  $reg->register(
    'server.posts.create',
    function(array $payload, array $meta, array $ctx) : array {

      $title   = isset($payload['title']) ? wp_strip_all_tags((string)$payload['title']) : '';
      $content = isset($payload['content']) ? (string)$payload['content'] : '';
      $status  = isset($payload['status']) ? sanitize_key((string)$payload['status']) : 'draft';
      $type    = isset($payload['post_type']) ? sanitize_key((string)$payload['post_type']) : 'post';

      if ($title === '') {
        return ['ok' => false, 'error' => 'missing_title'];
      }

      $allowed_status = ['draft', 'publish', 'pending', 'private'];
      if (!in_array($status, $allowed_status, true)) {
        return ['ok' => false, 'error' => 'invalid_status'];
      }

      $postarr = [
        'post_title'   => $title,
        'post_content' => $content,
        'post_status'  => $status,
        'post_type'    => $type,
      ];

      $post_id = wp_insert_post($postarr, true);
      if (is_wp_error($post_id)) {
        return ['ok'=>false, 'error'=>'wp_insert_post_failed', 'message'=>$post_id->get_error_message()];
      }

      return [
        'created' => true,
        'post_id' => (int)$post_id,
        'edit'    => get_edit_post_link($post_id, 'raw'),
        'view'    => get_permalink($post_id),
      ];
    },
    [
      'capability'    => 'edit_posts',
      'unsafe'        => false,
      'requires_live' => true, // ✅ bloquea si dry_run ON
      'tags'          => ['posts'],
    ]
  );

};
