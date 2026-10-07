<?php
if (!defined('ABSPATH')) { exit; }

final class IF_PluginSafelist {

	/**
	 * Lista de plugins permitidos para activar/desactivar.
	 * Usa el "file" tal cual sale de server.plugins.list (ej: akismet/akismet.php)
	 */
	public static function allowed() : array {
		return [
			// EJEMPLOS (ajústalo a tu sitio):
			// 'akismet/akismet.php',
			// 'hello.php', // a veces Hello Dolly es hello.php en la raíz, pero en tu site NO existe.
            'duplicator/duplicator.php',
            'wordpress-importer/wordpress-importer.php',
            'neurofocus-wp/neurofocus-wp.php',
            'zoom-semantico-inteligente/zoom-semantico-inteligente.php',
		];
	}

	public static function is_allowed(string $plugin_file) : bool {
		return in_array($plugin_file, self::allowed(), true);
	}
}


