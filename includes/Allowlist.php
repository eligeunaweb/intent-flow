<?php
if (!defined('ABSPATH')) { exit; }

final class IF_Allowlist {

	/**
	 * Lista de intents permitidos para batch/plan.
	 * Empieza conservador y vas ampliando.
	 */
	public static function allowed_intents() : array {
		return [
			'server.settings.get',
			'server.settings.challenge',
			'server.diagnostics.whoami',
			'server.audit.latest',
			'server.plan.run',
			'server.plan.apply',
			'server.batch.run',
			'server.diagnostics.health',
            'server.posts.get',
            'server.posts.create',
            'server.posts.update',
            'server.options.set',
			'server.plugins.list',
			'server.plugins.activate',
			'server.plugins.deactivate',


			// cuando metas posts:
			// 'server.posts.get',
			// 'server.posts.create',
			// 'server.posts.update',
		];
	}

	public static function is_allowed(string $intentId) : bool {
		return in_array($intentId, self::allowed_intents(), true);
	}
}
