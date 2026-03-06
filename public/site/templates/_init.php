<?php namespace ProcessWire;

// Optional initialization file, called before rendering any template file.
// This is defined by $config->prependTemplateFile in /site/config.php.
// Use this to define shared variables, functions, classes, includes, etc. 

require_once __DIR__ . '/_auth.php';

skfoAuthEnsureTables($database);

if (skfoAuthIsApiRequest($input)) {
	skfoAuthHandleApiRequest($input, $session, $database, $sanitizer, $config, $log);
	exit;
}

$skfoAuthUser = skfoAuthGetCurrentUser($session, $database);

if (!function_exists('skfoNormalizeEscapedQuotes')) {
	function skfoNormalizeEscapedQuotes(string $html): string {
		$normalized = $html;
		do {
			$prev = $normalized;
			$normalized = preg_replace('/&(?:amp;|#0?38;)+(quot;|#0*34;)/i', '&$1', $normalized) ?? $normalized;
		} while ($normalized !== $prev);
		return $normalized;
	}
}

ob_start(static function ($buffer) {
	if (!is_string($buffer) || $buffer === '') return $buffer;
	return skfoNormalizeEscapedQuotes($buffer);
});
