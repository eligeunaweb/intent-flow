<?php
if (!defined('ABSPATH')) { exit; }

/**
 * Metadata (human-friendly) for intents.
 * This is intentionally lightweight: title/description/examples/risk.
 */
final class IF_IntentMeta {
	/**
	 * @return array<string, array{title:string, description:string, risk:string, example_payload:array, tags?:array}>
	 */
	public static function all() : array {
		return [
			// Core registry
			'server.registry.list' => [
				'title' => 'List intents',
				'description' => 'Devuelve la lista de intents disponibles en el servidor.',
				'risk' => 'safe',
				'example_payload' => [],
				'tags' => ['diagnostics'],
			],
			'server.registry.describe' => [
				'title' => 'Describe intents',
				'description' => 'Devuelve metadatos (descripción, riesgo, ejemplo) para uno o todos los intents.',
				'risk' => 'safe',
				'example_payload' => ['id' => 'macro.post.ensure'],
				'tags' => ['diagnostics'],
			],

			// Live sessions
			'server.session.live.status' => [
				'title' => 'Live status',
				'description' => 'Estado actual de la sesión Live (si está activa y cuánto queda).',
				'risk' => 'safe',
				'example_payload' => [],
				'tags' => ['live'],
			],
			'server.session.live.enable' => [
				'title' => 'Enable Live',
				'description' => 'Activa Live por un tiempo limitado. Requiere token de server.settings.challenge.',
				'risk' => 'unsafe',
				'example_payload' => ['token' => '<token>', 'ttl' => 600],
				'tags' => ['live'],
			],
			'server.session.live.disable' => [
				'title' => 'Disable Live',
				'description' => 'Desactiva Live inmediatamente (cierre explícito).',
				'risk' => 'unsafe',
				'example_payload' => ['token' => '<token>'],
				'tags' => ['live'],
			],

			// Settings
			'server.settings.get' => [
				'title' => 'Get settings',
				'description' => 'Lee ajustes del motor (safe_mode, dry_run, enabled).',
				'risk' => 'safe',
				'example_payload' => [],
				'tags' => ['settings'],
			],
			'server.settings.challenge' => [
				'title' => 'Challenge token',
				'description' => 'Genera un token temporal para cambiar ajustes sensibles.',
				'risk' => 'safe',
				'example_payload' => [],
				'tags' => ['settings'],
			],
			'server.settings.set' => [
				'title' => 'Set settings',
				'description' => 'Actualiza ajustes (requiere token). Útil para activar/desactivar safe_mode.',
				'risk' => 'unsafe',
				'example_payload' => ['token' => '<token>', 'safe_mode' => true],
				'tags' => ['settings'],
			],

			// Audit
			'server.audit.latest' => [
				'title' => 'Audit latest',
				'description' => 'Últimos eventos de ejecución (auditoría) guardados en el servidor.',
				'risk' => 'safe',
				'example_payload' => ['limit' => 50],
				'tags' => ['audit'],
			],
			'server.audit.export' => [
				'title' => 'Audit export',
				'description' => 'Exporta la auditoría a JSON.',
				'risk' => 'safe',
				'example_payload' => ['limit' => 200],
				'tags' => ['audit'],
			],
			'server.audit.clear' => [
				'title' => 'Audit clear',
				'description' => 'Borra auditoría del servidor (acción irreversible).',
				'risk' => 'unsafe',
				'example_payload' => [],
				'tags' => ['audit'],
			],

			// Macro
			'macro.post.ensure' => [
				'title' => 'Ensure post',
				'description' => 'Crea o actualiza un post con título/contenido/status. Bloqueado por safe_mode si es unsafe.',
				'risk' => 'unsafe',
				'example_payload' => ['title' => 'Hola', 'content' => 'Contenido...', 'status' => 'draft'],
								'schema' => [
					'required' => ['title','status'],
					'properties' => [
						'title' => ['type'=>'string','min'=>1,'max'=>200],
						'content' => ['type'=>'string'],
						'status' => ['type'=>'string','enum'=>['draft','publish','pending','private']],
					],
				],
				'tags' => ['macro', 'posts'],
			],

			// Presets (server-side templates)
			'server.presets.list' => [
				'title' => 'List presets',
				'description' => 'Lista presets guardados en el servidor.',
				'risk' => 'safe',
				'example_payload' => [],
				'tags' => ['presets','automation'],
			],
			'server.presets.get' => [
				'title' => 'Get preset',
				'description' => 'Obtiene un preset por id.',
				'risk' => 'safe',
				'example_payload' => ['id' => 'mi-preset-abc123'],
				'tags' => ['presets','automation'],
			],
			'server.presets.save' => [
				'title' => 'Save preset',
				'description' => 'Guarda (crea/actualiza) un preset en el servidor.',
				'risk' => 'safe',
				'example_payload' => ['name'=>'Borrador rápido','intentId'=>'macro.post.ensure','payload'=>['title'=>'Hola','status'=>'draft']],
				'tags' => ['presets','automation'],
			],
			'server.presets.delete' => [
				'title' => 'Delete preset',
				'description' => 'Borra un preset por id.',
				'risk' => 'safe',
				'example_payload' => ['id' => 'mi-preset-abc123'],
				'tags' => ['presets','automation'],
			],
			'server.presets.export' => [
				'title' => 'Export presets',
				'description' => 'Exporta presets en JSON.',
				'risk' => 'safe',
				'example_payload' => [],
				'tags' => ['presets','automation'],
			],
			'server.presets.import' => [
				'title' => 'Import presets',
				'description' => 'Importa presets desde JSON.',
				'risk' => 'safe',
				'example_payload' => ['overwrite'=>true,'presets'=>[]],
				'tags' => ['presets','automation'],
			],

			// Flows (step sequences)
			'flow.list' => [
				'title' => 'List flows',
				'description' => 'Lista flows guardados en el servidor.',
				'risk' => 'safe',
				'example_payload' => [],
				'tags' => ['flows','automation'],
			],
			'flow.get' => [
				'title' => 'Get flow',
				'description' => 'Obtiene un flow por id.',
				'risk' => 'safe',
				'example_payload' => ['id' => 'mi-flow-abc123'],
				'tags' => ['flows','automation'],
			],
			'flow.save' => [
				'title' => 'Save flow',
				'description' => 'Guarda (crea/actualiza) un flow.',
				'risk' => 'safe',
				'example_payload' => ['name'=>'Publicar 2 borradores','steps'=>[['id'=>'macro.post.ensure','payload'=>['title'=>'Uno','status'=>'draft']],['id'=>'macro.post.ensure','payload'=>['title'=>'Dos','status'=>'draft']]]],
				'tags' => ['flows','automation'],
			],
			'flow.delete' => [
				'title' => 'Delete flow',
				'description' => 'Borra un flow por id.',
				'risk' => 'safe',
				'example_payload' => ['id' => 'mi-flow-abc123'],
				'tags' => ['flows','automation'],
			],
			'flow.run' => [
				'title' => 'Run flow',
				'description' => 'Ejecuta un flow guardado o uno inline.',
				'risk' => 'unsafe',
				'example_payload' => ['id'=>'mi-flow-abc123'],
				'tags' => ['flows','automation'],
			],
			'flow.export' => [
				'title' => 'Export flows',
				'description' => 'Exporta flows en JSON.',
				'risk' => 'safe',
				'example_payload' => [],
				'tags' => ['flows','automation'],
			],
			'flow.import' => [
				'title' => 'Import flows',
				'description' => 'Importa flows desde JSON.',
				'risk' => 'safe',
				'example_payload' => ['overwrite'=>true,'flows'=>[]],
				'tags' => ['flows','automation'],
			],

		];
	}

	public static function get(string $id) : ?array {
		$all = self::all();
		return isset($all[$id]) ? $all[$id] : null;
	}
}
