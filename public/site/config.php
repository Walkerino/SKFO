<?php namespace ProcessWire;

/**
 * ProcessWire Configuration File
 *
 * Site-specific configuration for ProcessWire
 *
 * Please see the file /wire/config.php which contains all configuration options you may
 * specify here. Simply copy any of the configuration options from that file and paste
 * them into this file in order to modify them.
 * 
 * SECURITY NOTICE
 * In non-dedicated environments, you should lock down the permissions of this file so
 * that it cannot be seen by other users on the system. For more information, please
 * see the config.php section at: https://processwire.com/docs/security/file-permissions/
 * 
 * This file is licensed under the MIT license
 * https://processwire.com/about/license/mit/
 *
 * ProcessWire 3.x, Copyright 2025 by Ryan Cramer
 * https://processwire.com
 *
 */

if(!defined("PROCESSWIRE")) die();

/** @var Config $config */

$skfoEnv = static function(string $key, $default = null) {
	$value = getenv($key);
	if($value === false || $value === null || $value === '') {
		return $default;
	}
	return $value;
};

$skfoEnvBool = static function(string $key, bool $default = false) use ($skfoEnv): bool {
	$value = $skfoEnv($key, null);
	if($value === null) return $default;
	$normalized = strtolower(trim((string) $value));
	return in_array($normalized, array('1', 'true', 'yes', 'on'), true);
};

/*** SITE CONFIG *************************************************************************/

// Let core API vars also be functions? So you can use $page or page(), for example.
$config->useFunctionsAPI = true;

// Use custom Page classes in /site/classes/ ? (i.e. template "home" => HomePage.php)
$config->usePageClasses = true;

// Use Markup Regions? (https://processwire.com/docs/front-end/output/markup-regions/)
$config->useMarkupRegions = true;

// Prepend this file in /site/templates/ to any rendered template files
$config->prependTemplateFile = '_init.php';

// Append this file in /site/templates/ to any rendered template files
$config->appendTemplateFile = '_main.php';

// Allow template files to be compiled for backwards compatibility?
$config->templateCompile = false;

/*** INSTALLER CONFIG ********************************************************************/
/**
 * Installer: Database Configuration
 * 
 */
$config->dbHost = (string) $skfoEnv('SKFO_DB_HOST', 'db');
$config->dbName = (string) $skfoEnv('SKFO_DB_NAME', 'db');
$config->dbUser = (string) $skfoEnv('SKFO_DB_USER', 'db');
$config->dbPass = (string) $skfoEnv('SKFO_DB_PASS', 'db');
$config->dbPort = (string) $skfoEnv('SKFO_DB_PORT', '3306');
$config->dbCharset = (string) $skfoEnv('SKFO_DB_CHARSET', 'utf8mb4');
$config->dbEngine = (string) $skfoEnv('SKFO_DB_ENGINE', 'InnoDB');

/**
 * Installer: User Authentication Salt 
 * 
 * This value was randomly generated for your system on 2026/02/09.
 * This should be kept as private as a password and never stored in the database.
 * Must be retained if you migrate your site from one server to another.
 * Do not change this value, or user passwords will no longer work.
 * 
 */
$config->userAuthSalt = (string) $skfoEnv('SKFO_USER_AUTH_SALT', '145c809d86dce001554219a444f9c1bbecbe7c42'); 

/**
 * Installer: Table Salt (General Purpose) 
 * 
 * Use this rather than userAuthSalt when a hashing salt is needed for non user 
 * authentication purposes. Like with userAuthSalt, you should never change 
 * this value or it may break internal system comparisons that use it. 
 * 
 */
$config->tableSalt = (string) $skfoEnv('SKFO_TABLE_SALT', '489c01bd73f8296d4c2d5eb61fbb9aaa1cf50c43'); 

/**
 * Installer: File Permission Configuration
 * 
 */
$config->chmodDir = '0755'; // permission for directories created by ProcessWire
$config->chmodFile = '0644'; // permission for files created by ProcessWire 

/**
 * Installer: Time zone setting
 * 
 */
$config->timezone = (string) $skfoEnv('SKFO_TIMEZONE', 'Europe/Moscow');

/**
 * Installer: Admin theme
 * 
 */
$config->defaultAdminTheme = 'AdminThemeUikit';

/**
 * Installer: Name of Uikit theme to use in admin
 * 
 */
$config->AdminThemeUikit('themeName', 'default');

/**
 * Installer: Unix timestamp of date/time installed
 * 
 * This is used to detect which when certain behaviors must be backwards compatible.
 * Please leave this value as-is.
 * 
 */
$config->installed = 1770632743;


/**
 * Installer: Session name 
 * 
 * Default session name as used in session cookie. 
 * Note that changing this will automatically logout any current sessions. 
 * 
 */
$config->sessionName = (string) $skfoEnv('SKFO_SESSION_NAME', 'pw353');


/**
 * Installer: HTTP Hosts Whitelist
 * 
 */
$httpHostsRaw = (string) $skfoEnv('SKFO_HTTP_HOSTS', 'skfo.ddev.site');
$httpHosts = array_values(array_filter(array_map('trim', explode(',', $httpHostsRaw))));
$config->httpHosts = count($httpHosts) ? $httpHosts : array('skfo.ddev.site');


/**
 * Installer: Debug mode?
 * 
 * When debug mode is true, errors and exceptions are visible. 
 * When false, they are not visible except to superuser and in logs. 
 * Should be true for development sites and false for live/production sites. 
 * 
 */
$defaultDebug = $skfoEnv('IS_DDEV_PROJECT', false) !== false;
$config->debug = $skfoEnvBool('SKFO_DEBUG', $defaultDebug);
