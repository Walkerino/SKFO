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
