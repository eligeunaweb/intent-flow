<?php
if (!defined('ABSPATH')) { exit; }

final class IF_Templates {

    public static function all() : array {
        return [

            [
                'id'    => 'welcome-email',
                'label' => '👤 Bienvenida al registrarse',
                'description' => 'Envía un email de bienvenida automático cuando un usuario se registra.',
                'category' => 'usuarios',
                'flow'  => [
                    'name'    => 'Email bienvenida nuevo usuario',
                    'trigger' => 'user.registered',
                    'enabled' => true,
                    'steps'   => [[
                        'id'      => 'wp.email.send',
                        'payload' => [
                            'to'      => '{{payload.user_email}}',
                            'subject' => 'Bienvenido/a {{payload.user_login}}',
                            'body'    => '<p>Hola <strong>{{payload.user_login}}</strong>, gracias por registrarte.</p>',
                        ],
                        'meta' => []
                    ]]
                ]
            ],
            [
                'id'    => 'admin-notify-new-user',
                'label' => '🔔 Notificar admin al registrarse usuario',
                'description' => 'Avisa al administrador por email cuando alguien se registra.',
                'category' => 'usuarios',
                'flow'  => [
                    'name'    => 'Notificación admin nuevo usuario',
                    'trigger' => 'user.registered',
                    'enabled' => true,
                    'steps'   => [[
                        'id'      => 'wp.email.send',
                        'payload' => [
                            'to'      => '{{admin_email}}',
                            'subject' => 'Nuevo usuario: {{payload.user_login}}',
                            'body'    => '<p>Nuevo usuario registrado: <strong>{{payload.user_login}}</strong> ({{payload.user_email}})</p>',
                        ],
                        'meta' => []
                    ]]
                ]
            ],
            [
                'id'    => 'notify-login-failed',
                'label' => '🚨 Alerta login fallido',
                'description' => 'Avisa al admin cuando falla un intento de login.',
                'category' => 'seguridad',
                'flow'  => [
                    'name'    => 'Alerta login fallido',
                    'trigger' => 'user.login_failed',
                    'enabled' => true,
                    'steps'   => [[
                        'id'      => 'wp.email.send',
                        'payload' => [
                            'to'      => '{{admin_email}}',
                            'subject' => 'Intento de login fallido',
                            'body'    => '<p>Intento fallido para: <strong>{{payload.user_login}}</strong></p>',
                        ],
                        'meta' => []
                    ]]
                ]
            ],
            [
                'id'    => 'notify-role-changed',
                'label' => '🔑 Notificar cambio de rol',
                'description' => 'Avisa al admin cuando cambia el rol de un usuario.',
                'category' => 'usuarios',
                'flow'  => [
                    'name'    => 'Notificación cambio de rol',
                    'trigger' => 'user.role_changed',
                    'enabled' => true,
                    'steps'   => [[
                        'id'      => 'wp.email.send',
                        'payload' => [
                            'to'      => '{{admin_email}}',
                            'subject' => 'Cambio de rol de usuario',
                            'body'    => '<p>Usuario ID <strong>{{payload.user_id}}</strong> — nuevo rol: <strong>{{payload.new_role}}</strong></p>',
                        ],
                        'meta' => []
                    ]]
                ]
            ],
            [
                'id'    => 'post-published-notify',
                'label' => '📝 Post publicado → Email admin',
                'description' => 'Notifica al admin cuando un post cambia de estado.',
                'category' => 'contenido',
                'flow'  => [
                    'name'    => 'Post publicado notificación',
                    'trigger' => 'post.status_changed',
                    'enabled' => true,
                    'steps'   => [[
                        'id'      => 'wp.email.send',
                        'payload' => [
                            'to'      => '{{admin_email}}',
                            'subject' => 'Post {{payload.post_id}} — estado: {{payload.new_status}}',
                            'body'    => '<p>El post ID <strong>{{payload.post_id}}</strong> ha cambiado de <em>{{payload.old_status}}</em> a <em>{{payload.new_status}}</em>.</p>',
                        ],
                        'meta' => []
                    ]]
                ]
            ],
            [
                'id'    => 'post-created-webhook',
                'label' => '🔗 Post creado → Webhook externo',
                'description' => 'Envía los datos del post a un webhook cuando se crea.',
                'category' => 'contenido',
                'flow'  => [
                    'name'    => 'Post creado a webhook',
                    'trigger' => 'post.created',
                    'enabled' => true,
                    'steps'   => [[
                        'id'      => 'http.request',
                        'payload' => [
                            'url'    => 'https://your-webhook-url.com',
                            'method' => 'POST',
                            'body'   => [ 'post_id' => '{{payload.post_id}}', 'post_type' => '{{payload.post_type}}' ],
                        ],
                        'meta' => []
                    ]]
                ]
            ],
            [
                'id'    => 'comment-notify-admin',
                'label' => '💬 Nuevo comentario → Email admin',
                'description' => 'Notifica al admin cuando se publica un comentario.',
                'category' => 'contenido',
                'flow'  => [
                    'name'    => 'Comentario nuevo notificación',
                    'trigger' => 'comment.posted',
                    'enabled' => true,
                    'steps'   => [[
                        'id'      => 'wp.email.send',
                        'payload' => [
                            'to'      => '{{admin_email}}',
                            'subject' => 'Nuevo comentario en tu sitio',
                            'body'    => '<p>Comentario de <strong>{{payload.author}}</strong> en post ID {{payload.post_id}}.</p>',
                        ],
                        'meta' => []
                    ]]
                ]
            ],
            [
                'id'    => 'webhook-to-http',
                'label' => '🔗 Webhook entrante → HTTP externo',
                'description' => 'Reenvía un webhook entrante a una URL externa.',
                'category' => 'webhooks',
                'flow'  => [
                    'name'    => 'Webhook a HTTP',
                    'trigger' => 'webhook.received',
                    'enabled' => true,
                    'steps'   => [[
                        'id'      => 'http.request',
                        'payload' => [ 'url' => 'https://your-endpoint.com', 'method' => 'POST', 'body' => [] ],
                        'meta' => []
                    ]]
                ]
            ],
            [
                'id'    => 'webhook-to-slack',
                'label' => '📨 Webhook entrante → Slack',
                'description' => 'Notifica en Slack cuando llega un webhook.',
                'category' => 'webhooks',
                'flow'  => [
                    'name'    => 'Webhook a Slack',
                    'trigger' => 'webhook.received',
                    'enabled' => true,
                    'steps'   => [[
                        'id'      => 'slack.send',
                        'payload' => [ 'text' => 'Webhook recibido desde {{meta.source}}' ],
                        'meta' => []
                    ]]
                ]
            ],
            [
                'id'    => 'plugin-updated-notify',
                'label' => '🔄 Plugin actualizado → Email',
                'description' => 'Notifica al admin cuando se actualiza un plugin.',
                'category' => 'sistema',
                'flow'  => [
                    'name'    => 'Notificación actualización plugin',
                    'trigger' => 'plugin.updated',
                    'enabled' => true,
                    'steps'   => [[
                        'id'      => 'wp.email.send',
                        'payload' => [
                            'to'      => '{{admin_email}}',
                            'subject' => 'Plugin actualizado en tu sitio',
                            'body'    => '<p>Plugin actualizado: <strong>{{payload.plugin}}</strong></p>',
                        ],
                        'meta' => []
                    ]]
                ]
            ],
            [
                'id'    => 'post-to-slack',
                'label' => '📢 Post creado → Slack',
                'description' => 'Notifica en Slack cuando se crea un post.',
                'category' => 'contenido',
                'flow'  => [
                    'name'    => 'Post creado a Slack',
                    'trigger' => 'post.created',
                    'enabled' => true,
                    'steps'   => [[
                        'id'      => 'slack.send',
                        'payload' => [ 'text' => 'Nuevo post (ID {{payload.post_id}}) tipo {{payload.post_type}}' ],
                        'meta' => []
                    ]]
                ]
            ],
            [
                'id'    => 'cron-daily-report',
                'label' => '⏰ Informe diario por email',
                'description' => 'Envía un email de informe en cada tick del cron.',
                'category' => 'sistema',
                'flow'  => [
                    'name'    => 'Informe cron diario',
                    'trigger' => 'cron.tick',
                    'enabled' => false,
                    'steps'   => [[
                        'id'      => 'wp.email.send',
                        'payload' => [
                            'to'      => '{{admin_email}}',
                            'subject' => 'Informe automático del sitio',
                            'body'    => '<p>Informe automático programado.</p>',
                        ],
                        'meta' => []
                    ]]
                ]
            ],

            // WooCommerce Pro
            [
                'id'          => 'wc-order-completed-email',
                'label'       => '🛒 Pedido completado → Email cliente',
                'description' => 'Envía un email al cliente cuando su pedido se completa. (Pro)',
                'category'    => 'woocommerce',
                'pro'         => true,
                'flow'        => [
                    'name'    => 'WC Pedido completado → Email',
                    'trigger' => 'wc.order.status_changed',
                    'enabled' => true,
                    'steps'   => [[
                        'id'      => 'wp.email.send',
                        'payload' => [
                            'to'      => '{{payload.email}}',
                            'subject' => 'Tu pedido #{{payload.order_id}} está completado',
                            'body'    => '<p>Hola, tu pedido <strong>#{{payload.order_id}}</strong> ha sido completado. Total: {{payload.total}} {{payload.currency}}</p>',
                        ],
                        'meta' => []
                    ]]
                ]
            ],
            [
                'id'          => 'wc-payment-complete-slack',
                'label'       => '💳 Pago completado → Slack',
                'description' => 'Notifica en Slack cuando se completa un pago. (Pro)',
                'category'    => 'woocommerce',
                'pro'         => true,
                'flow'        => [
                    'name'    => 'WC Pago completado → Slack',
                    'trigger' => 'wc.payment.complete',
                    'enabled' => true,
                    'steps'   => [[
                        'id'      => 'slack.send',
                        'payload' => [
                            'text' => '💳 Pago completado — Pedido #{{payload.order_id}} · {{payload.total}} {{payload.currency}} · {{payload.email}}',
                        ],
                        'meta' => []
                    ]]
                ]
            ],
            [
                'id'          => 'wc-new-customer-coupon',
                'label'       => '🎁 Cliente nuevo → Cupón bienvenida',
                'description' => 'Crea un cupón de bienvenida cuando se registra un nuevo cliente. (Pro)',
                'category'    => 'woocommerce',
                'pro'         => true,
                'flow'        => [
                    'name'    => 'WC Cliente nuevo → Cupón',
                    'trigger' => 'wc.customer.registered',
                    'enabled' => true,
                    'steps'   => [[
                        'id'      => 'wc.coupon.create',
                        'payload' => [
                            'amount'      => 10,
                            'type'        => 'percent',
                            'usage_limit' => 1,
                        ],
                        'meta' => []
                    ]]
                ]
            ],
            [
                'id'          => 'wc-low-stock-email',
                'label'       => '⚠️ Stock bajo → Email admin',
                'description' => 'Avisa al admin cuando un producto tiene stock bajo. (Pro)',
                'category'    => 'woocommerce',
                'pro'         => true,
                'flow'        => [
                    'name'    => 'WC Stock bajo → Email',
                    'trigger' => 'wc.product.low_stock',
                    'enabled' => true,
                    'steps'   => [[
                        'id'      => 'wp.email.send',
                        'payload' => [
                            'to'      => '{{admin_email}}',
                            'subject' => '⚠️ Stock bajo: {{payload.product_name}}',
                            'body'    => '<p>El producto <strong>{{payload.product_name}}</strong> tiene solo <strong>{{payload.stock}}</strong> unidades en stock.</p>',
                        ],
                        'meta' => []
                    ]]
                ]
            ],

            // Google Sheets Pro
            [
                'id'          => 'gsheets-new-user',
                'label'       => '📊 Usuario registrado → Google Sheets',
                'description' => 'Añade una fila en Google Sheets cuando se registra un usuario. (Pro)',
                'category'    => 'google_sheets',
                'pro'         => true,
                'flow'        => [
                    'name'    => 'Usuario registrado → Google Sheets',
                    'trigger' => 'user.registered',
                    'enabled' => true,
                    'steps'   => [[
                        'id'      => 'gsheets.append_row',
                        'payload' => [
                            'spreadsheet_id' => 'YOUR_SPREADSHEET_ID',
                            'sheet_name'     => 'Usuarios',
                            'values'         => ['{{payload.user_email}}', '{{payload.user_login}}', '{{payload.ts}}'],
                        ],
                        'meta' => []
                    ]]
                ]
            ],
            [
                'id'          => 'gsheets-wc-order',
                'label'       => '📊 Pedido WC → Google Sheets',
                'description' => 'Registra cada pedido de WooCommerce en Google Sheets. (Pro)',
                'category'    => 'google_sheets',
                'pro'         => true,
                'flow'        => [
                    'name'    => 'Pedido WC → Google Sheets',
                    'trigger' => 'wc.order.created',
                    'enabled' => true,
                    'steps'   => [[
                        'id'      => 'gsheets.append_row',
                        'payload' => [
                            'spreadsheet_id' => 'YOUR_SPREADSHEET_ID',
                            'sheet_name'     => 'Pedidos',
                            'values'         => ['{{payload.order_id}}', '{{payload.email}}', '{{payload.total}}', '{{payload.currency}}', '{{payload.ts}}'],
                        ],
                        'meta' => []
                    ]]
                ]
            ],

            // OpenAI Pro
            [
                'id'          => 'ai-comment-moderate',
                'label'       => '🤖 Comentario nuevo → Moderación IA',
                'description' => 'Modera automáticamente los comentarios con IA. (Pro)',
                'category'    => 'openai',
                'pro'         => true,
                'flow'        => [
                    'name'    => 'Comentario → Moderación IA',
                    'trigger' => 'comment.posted',
                    'enabled' => true,
                    'steps'   => [[
                        'id'      => 'ai.text.moderate',
                        'payload' => ['text' => '{{payload.comment_content}}'],
                        'meta'    => []
                    ]]
                ]
            ],
            [
                'id'          => 'ai-post-summarize',
                'label'       => '🤖 Post publicado → Resumen IA',
                'description' => 'Genera un resumen del post al publicarlo. (Pro)',
                'category'    => 'openai',
                'pro'         => true,
                'flow'        => [
                    'name'    => 'Post publicado → Resumen IA',
                    'trigger' => 'post.created',
                    'enabled' => true,
                    'steps'   => [[
                        'id'      => 'ai.text.summarize',
                        'payload' => ['text' => '{{payload.post_content}}', 'length' => 'short'],
                        'meta'    => []
                    ]]
                ]
            ],
            [
                'id'          => 'ai-user-welcome',
                'label'       => '🤖 Usuario registrado → Email bienvenida IA',
                'description' => 'Genera un email de bienvenida personalizado con IA. (Pro)',
                'category'    => 'openai',
                'pro'         => true,
                'flow'        => [
                    'name'    => 'Usuario registrado → Email IA',
                    'trigger' => 'user.registered',
                    'enabled' => true,
                    'steps'   => [[
                        'id'      => 'ai.text.generate',
                        'payload' => [
                            'prompt' => 'Write a friendly welcome email for a new user named {{payload.user_login}}.',
                            'system' => 'You are a friendly customer success manager. Write concise, warm emails.',
                        ],
                        'meta' => []
                    ]]
                ]
            ],
        ];
    }
}
