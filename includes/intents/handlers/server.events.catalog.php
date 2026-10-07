<?php
if (!defined('ABSPATH')) { exit; }
return function(IF_IntentRegistry $reg) : void {
    $reg->register(
        'server.events.catalog',
        function(array $payload, array $meta, array $ctx) : array {
            $items = [
                // WordPress - Posts
                ['id'=>'post.created',        'label'=>'Post created',        'group'=>'wordpress', 'description'=>'Se emite al crear un post (primer guardado).', 'example_payload'=>['post_id'=>123,'post_type'=>'post','status'=>'draft','update'=>false,'ts'=>time()]],
                ['id'=>'post.updated',        'label'=>'Post updated',        'group'=>'wordpress', 'description'=>'Se emite al actualizar un post existente.',    'example_payload'=>['post_id'=>123,'post_type'=>'post','status'=>'publish','update'=>true,'ts'=>time()]],
                ['id'=>'post.deleted',        'label'=>'Post deleted',        'group'=>'wordpress', 'description'=>'Se emite al eliminar un post permanentemente.', 'example_payload'=>['post_id'=>123,'post_type'=>'post','ts'=>time()]],
                ['id'=>'post.trashed',        'label'=>'Post trashed',        'group'=>'wordpress', 'description'=>'Se emite al mover un post a la papelera.',      'example_payload'=>['post_id'=>123,'post_type'=>'post','ts'=>time()]],
                ['id'=>'post.status_changed', 'label'=>'Post status changed', 'group'=>'wordpress', 'description'=>'Se emite al cambiar el estado de un post.',     'example_payload'=>['post_id'=>123,'post_type'=>'post','old_status'=>'draft','new_status'=>'publish','ts'=>time()]],
                // WordPress - Users
                ['id'=>'user.registered',     'label'=>'User registered',     'group'=>'wordpress', 'description'=>'Se emite al registrar un nuevo usuario.',       'example_payload'=>['user_id'=>1,'user_email'=>'user@example.com','user_login'=>'username','ts'=>time()]],
                ['id'=>'user.login',          'label'=>'User login',          'group'=>'wordpress', 'description'=>'Se emite al iniciar sesión un usuario.',        'example_payload'=>['user_id'=>1,'user_login'=>'username','ts'=>time()]],
                ['id'=>'user.login_failed',   'label'=>'User login failed',   'group'=>'wordpress', 'description'=>'Se emite cuando falla un intento de login.',    'example_payload'=>['user_login'=>'username','ts'=>time()]],
                ['id'=>'user.logout',         'label'=>'User logout',         'group'=>'wordpress', 'description'=>'Se emite al cerrar sesión un usuario.',         'example_payload'=>['user_id'=>1,'ts'=>time()]],
                ['id'=>'user.profile_updated','label'=>'User profile updated','group'=>'wordpress', 'description'=>'Se emite al actualizar el perfil de un usuario.','example_payload'=>['user_id'=>1,'ts'=>time()]],
                ['id'=>'user.password_reset', 'label'=>'User password reset', 'group'=>'wordpress', 'description'=>'Se emite al resetear la contraseña.',           'example_payload'=>['user_id'=>1,'user_login'=>'username','ts'=>time()]],
                ['id'=>'user.deleted',        'label'=>'User deleted',        'group'=>'wordpress', 'description'=>'Se emite al eliminar un usuario.',              'example_payload'=>['user_id'=>1,'ts'=>time()]],
                ['id'=>'user.role_changed',   'label'=>'User role changed',   'group'=>'wordpress', 'description'=>'Se emite al cambiar el rol de un usuario.',     'example_payload'=>['user_id'=>1,'new_role'=>'editor','old_roles'=>['subscriber'],'ts'=>time()]],
                // WordPress - Comments
                ['id'=>'comment.posted',      'label'=>'Comment posted',      'group'=>'wordpress', 'description'=>'Se emite al publicar un comentario.',           'example_payload'=>['comment_id'=>1,'post_id'=>123,'approved'=>1,'author'=>'John','ts'=>time()]],
                // WordPress - Plugins
                ['id'=>'plugin.updated',      'label'=>'Plugin updated',      'group'=>'wordpress', 'description'=>'Se emite tras una actualización de plugin.',    'example_payload'=>['plugin'=>'some-plugin/some-plugin.php','ts'=>time()]],
                // System
                ['id'=>'cron.tick',           'label'=>'Cron tick',           'group'=>'system',    'description'=>'Se emite en cada tick del scheduler.',          'example_payload'=>['ts'=>time()]],
                // WooCommerce (Pro)
                ['id'=>'wc.order.created',        'label'=>'WC Order created',        'group'=>'woocommerce', 'description'=>'Se emite al crear un pedido nuevo.',               'example_payload'=>['order_id'=>123,'status'=>'pending','total'=>99.99,'currency'=>'EUR','email'=>'customer@example.com','ts'=>time()]],
                ['id'=>'wc.order.status_changed',  'label'=>'WC Order status changed', 'group'=>'woocommerce', 'description'=>'Se emite al cambiar el estado de un pedido.',      'example_payload'=>['order_id'=>123,'old_status'=>'pending','new_status'=>'processing','total'=>99.99,'email'=>'customer@example.com','ts'=>time()]],
                ['id'=>'wc.payment.complete',      'label'=>'WC Payment complete',     'group'=>'woocommerce', 'description'=>'Se emite cuando se completa un pago.',             'example_payload'=>['order_id'=>123,'total'=>99.99,'currency'=>'EUR','payment_method'=>'stripe','email'=>'customer@example.com','ts'=>time()]],
                ['id'=>'wc.order.refunded',        'label'=>'WC Order refunded',       'group'=>'woocommerce', 'description'=>'Se emite cuando se reembolsa un pedido.',          'example_payload'=>['order_id'=>123,'refund_id'=>456,'amount'=>99.99,'currency'=>'EUR','email'=>'customer@example.com','ts'=>time()]],
                ['id'=>'wc.customer.registered',   'label'=>'WC Customer registered',  'group'=>'woocommerce', 'description'=>'Se emite cuando un cliente se registra en WC.',   'example_payload'=>['customer_id'=>1,'email'=>'customer@example.com','login'=>'username','ts'=>time()]],
                ['id'=>'wc.coupon.applied',        'label'=>'WC Coupon applied',       'group'=>'woocommerce', 'description'=>'Se emite cuando se aplica un cupón.',              'example_payload'=>['coupon_code'=>'DESCUENTO10','ts'=>time()]],
                ['id'=>'wc.product.low_stock',     'label'=>'WC Product low stock',    'group'=>'woocommerce', 'description'=>'Se emite cuando un producto tiene stock bajo.',    'example_payload'=>['product_id'=>123,'product_name'=>'Producto','stock'=>2,'ts'=>time()]],
                // Stripe (Pro)
                ['id'=>'stripe.payment.completed',    'label'=>'Stripe Payment completed',     'group'=>'stripe', 'description'=>'Se emite cuando se completa un pago en Stripe.',         'example_payload'=>['payment_id'=>'pi_xxx','amount'=>99.99,'currency'=>'EUR','email'=>'customer@example.com','ts'=>time()]],
                ['id'=>'stripe.subscription.created', 'label'=>'Stripe Subscription created',  'group'=>'stripe', 'description'=>'Se emite cuando se crea una suscripción en Stripe.',     'example_payload'=>['subscription_id'=>'sub_xxx','customer'=>'cus_xxx','status'=>'active','amount'=>49.00,'currency'=>'EUR','ts'=>time()]],
                ['id'=>'stripe.subscription.cancelled','label'=>'Stripe Subscription cancelled','group'=>'stripe', 'description'=>'Se emite cuando se cancela una suscripción en Stripe.',  'example_payload'=>['subscription_id'=>'sub_xxx','customer'=>'cus_xxx','status'=>'canceled','ts'=>time()]],
                ['id'=>'stripe.refund.created',       'label'=>'Stripe Refund created',        'group'=>'stripe', 'description'=>'Se emite cuando se crea un reembolso en Stripe.',        'example_payload'=>['charge_id'=>'ch_xxx','amount'=>99.99,'currency'=>'EUR','customer'=>'cus_xxx','ts'=>time()]],
                // Webhooks
                ['id'=>'webhook.received',    'label'=>'Webhook received',    'group'=>'webhooks',  'description'=>'Se emite al recibir un webhook firmado por MMI.','example_payload'=>['webhook_id'=>'wh_demo','body'=>['hello'=>'world'],'query'=>['foo'=>'bar']]],
            ];
            return ['ok'=>true,'items'=>$items];
        },
        ['capability' => 'manage_options']
    );
};
