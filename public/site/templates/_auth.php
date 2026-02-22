<?php namespace ProcessWire;

if (!function_exists(__NAMESPACE__ . '\\skfoAuthEnsureTables')) {
	function skfoAuthEnsureTables($database): void {
		$database->exec(
			"CREATE TABLE IF NOT EXISTS `site_auth_accounts` (
				`id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
				`email` VARCHAR(190) NOT NULL,
				`name` VARCHAR(120) NOT NULL DEFAULT '',
				`created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
				`last_login_at` DATETIME NULL,
				`last_ip` VARCHAR(64) NOT NULL DEFAULT '',
				PRIMARY KEY (`id`),
				UNIQUE KEY `uniq_email` (`email`)
			) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
		);

		$database->exec(
			"CREATE TABLE IF NOT EXISTS `site_auth_otps` (
				`id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
				`email` VARCHAR(190) NOT NULL,
				`mode` VARCHAR(16) NOT NULL DEFAULT 'login',
				`requested_name` VARCHAR(120) NOT NULL DEFAULT '',
				`code_hash` VARCHAR(255) NOT NULL,
				`attempts` TINYINT UNSIGNED NOT NULL DEFAULT 0,
				`max_attempts` TINYINT UNSIGNED NOT NULL DEFAULT 5,
				`expires_at` DATETIME NOT NULL,
				`ip` VARCHAR(64) NOT NULL DEFAULT '',
				`created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
				`updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
				`consumed_at` DATETIME NULL,
				PRIMARY KEY (`id`),
				KEY `idx_email_created` (`email`, `created_at`),
				KEY `idx_ip_created` (`ip`, `created_at`),
				KEY `idx_email_active` (`email`, `consumed_at`, `id`)
			) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
		);
	}

	function skfoAuthNormalizeEmail($sanitizer, string $rawEmail): string {
		$email = trim((string) $sanitizer->email($rawEmail));
		$email = function_exists('mb_strtolower') ? mb_strtolower($email, 'UTF-8') : strtolower($email);
		if ($email === '') return '';
		if (!filter_var($email, FILTER_VALIDATE_EMAIL)) return '';
		return $email;
	}

	function skfoAuthNormalizeName(string $rawName): string {
		$name = trim($rawName);
		$name = preg_replace('/\s+/u', ' ', $name) ?? $name;
		return $name;
	}

	function skfoAuthClientIp(): string {
		$ip = trim((string) ($_SERVER['REMOTE_ADDR'] ?? ''));
		if ($ip === '') return '0.0.0.0';
		return substr($ip, 0, 64);
	}

	function skfoAuthJson(bool $ok, string $message, array $data = [], int $status = 200): void {
		http_response_code($status);
		header('Content-Type: application/json; charset=utf-8');
		echo json_encode([
			'ok' => $ok,
			'message' => $message,
			'data' => $data,
		], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
	}

	function skfoAuthClearSession($session): void {
		$session->remove('skfo_auth_account_id');
		$session->remove('skfo_auth_email');
		$session->remove('skfo_auth_name');
		$session->remove('skfo_auth_logged_in_at');
	}

	function skfoAuthSetSession($session, array $account): void {
		$session->set('skfo_auth_account_id', (int) ($account['id'] ?? 0));
		$session->set('skfo_auth_email', (string) ($account['email'] ?? ''));
		$session->set('skfo_auth_name', (string) ($account['name'] ?? ''));
		$session->set('skfo_auth_logged_in_at', date('Y-m-d H:i:s'));
	}

	function skfoAuthFindAccountById($database, int $id): ?array {
		if ($id <= 0) return null;
		$stmt = $database->prepare("SELECT `id`, `email`, `name`, `created_at`, `last_login_at` FROM `site_auth_accounts` WHERE `id`=:id LIMIT 1");
		$stmt->bindValue(':id', $id, \PDO::PARAM_INT);
		$stmt->execute();
		$row = $stmt->fetch(\PDO::FETCH_ASSOC);
		return is_array($row) ? $row : null;
	}

	function skfoAuthFindAccountByEmail($database, string $email): ?array {
		if ($email === '') return null;
		$stmt = $database->prepare("SELECT `id`, `email`, `name`, `created_at`, `last_login_at` FROM `site_auth_accounts` WHERE `email`=:email LIMIT 1");
		$stmt->bindValue(':email', $email, \PDO::PARAM_STR);
		$stmt->execute();
		$row = $stmt->fetch(\PDO::FETCH_ASSOC);
		return is_array($row) ? $row : null;
	}

	function skfoAuthCreateAccount($database, string $email, string $name, string $ip): ?array {
		try {
			$insert = $database->prepare(
				"INSERT INTO `site_auth_accounts` (`email`, `name`, `last_login_at`, `last_ip`) VALUES (:email, :name, :last_login_at, :last_ip)"
			);
			$insert->bindValue(':email', $email, \PDO::PARAM_STR);
			$insert->bindValue(':name', $name, \PDO::PARAM_STR);
			$insert->bindValue(':last_login_at', date('Y-m-d H:i:s'), \PDO::PARAM_STR);
			$insert->bindValue(':last_ip', $ip, \PDO::PARAM_STR);
			$insert->execute();
		} catch (\Throwable $e) {
			// Concurrent registration for the same email can hit unique constraint.
		}
		return skfoAuthFindAccountByEmail($database, $email);
	}

	function skfoAuthTouchLogin($database, int $accountId, string $ip): void {
		$update = $database->prepare("UPDATE `site_auth_accounts` SET `last_login_at`=:last_login_at, `last_ip`=:last_ip WHERE `id`=:id");
		$update->bindValue(':last_login_at', date('Y-m-d H:i:s'), \PDO::PARAM_STR);
		$update->bindValue(':last_ip', $ip, \PDO::PARAM_STR);
		$update->bindValue(':id', $accountId, \PDO::PARAM_INT);
		$update->execute();
	}

	function skfoAuthGetCurrentUser($session, $database): ?array {
		$accountId = (int) $session->get('skfo_auth_account_id');
		if ($accountId <= 0) return null;

		$account = skfoAuthFindAccountById($database, $accountId);
		if (!$account) {
			skfoAuthClearSession($session);
			return null;
		}
		return $account;
	}

	function skfoAuthIsApiRequest($input): bool {
		return $input->requestMethod() === 'POST' && trim((string) $input->post('auth_action')) !== '';
	}

	function skfoAuthEnv(string $name, string $default = ''): string {
		$value = $_ENV[$name] ?? $_SERVER[$name] ?? getenv($name);
		if ($value === false || $value === null) return $default;
		return trim((string) $value);
	}

	function skfoAuthMimeHeader(string $value): string {
		return '=?UTF-8?B?' . base64_encode($value) . '?=';
	}

	function skfoAuthSmtpReadResponse($socket): array {
		$lines = [];
		$code = 0;

		while (!feof($socket)) {
			$line = fgets($socket, 2048);
			if ($line === false) break;
			$lines[] = rtrim($line, "\r\n");
			if (strlen($line) >= 3 && ctype_digit(substr($line, 0, 3))) {
				$code = (int) substr($line, 0, 3);
				if (strlen($line) < 4 || $line[3] !== '-') {
					break;
				}
			}
		}

		return [$code, implode("\n", $lines)];
	}

	function skfoAuthSmtpCommand($socket, string $command, array $expectedCodes, string &$error): bool {
		if ($command !== '') {
			$written = fwrite($socket, $command . "\r\n");
			if ($written === false) {
				$error = 'SMTP write error.';
				return false;
			}
		}

		[$code, $response] = skfoAuthSmtpReadResponse($socket);
		if (!in_array($code, $expectedCodes, true)) {
			$error = "SMTP unexpected response {$code}: {$response}";
			return false;
		}
		return true;
	}

	function skfoAuthSendViaSmtp(string $toEmail, string $subject, string $bodyText, array $smtpConfig, string &$error): bool {
		$host = trim((string) ($smtpConfig['host'] ?? ''));
		$port = (int) ($smtpConfig['port'] ?? 587);
		$user = trim((string) ($smtpConfig['user'] ?? ''));
		$pass = (string) ($smtpConfig['pass'] ?? '');
		$secure = strtolower(trim((string) ($smtpConfig['secure'] ?? 'tls')));
		$fromEmail = trim((string) ($smtpConfig['from_email'] ?? ''));
		$fromName = trim((string) ($smtpConfig['from_name'] ?? 'SKFO.RU'));

		if ($host === '' || $fromEmail === '') {
			$error = 'SMTP config is incomplete.';
			return false;
		}

		$transportHost = $secure === 'ssl' ? "ssl://{$host}" : $host;
		$socket = @stream_socket_client("{$transportHost}:{$port}", $errno, $errStr, 20, STREAM_CLIENT_CONNECT);
		if (!$socket) {
			$error = "SMTP connect error: {$errno} {$errStr}";
			return false;
		}

		stream_set_timeout($socket, 20);
		$error = '';

		if (!skfoAuthSmtpCommand($socket, '', [220], $error)) {
			fclose($socket);
			return false;
		}

		$ehloHost = parse_url((string) ($_SERVER['HTTP_HOST'] ?? ''), PHP_URL_HOST);
		if (!is_string($ehloHost) || $ehloHost === '') $ehloHost = 'localhost';

		if (!skfoAuthSmtpCommand($socket, "EHLO {$ehloHost}", [250], $error)) {
			fclose($socket);
			return false;
		}

		if ($secure === 'tls') {
			if (!skfoAuthSmtpCommand($socket, 'STARTTLS', [220], $error)) {
				fclose($socket);
				return false;
			}
			$cryptoEnabled = @stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
			if ($cryptoEnabled !== true) {
				$error = 'SMTP TLS negotiation failed.';
				fclose($socket);
				return false;
			}
			if (!skfoAuthSmtpCommand($socket, "EHLO {$ehloHost}", [250], $error)) {
				fclose($socket);
				return false;
			}
		}

		if ($user !== '') {
			if (!skfoAuthSmtpCommand($socket, 'AUTH LOGIN', [334], $error)) {
				fclose($socket);
				return false;
			}
			if (!skfoAuthSmtpCommand($socket, base64_encode($user), [334], $error)) {
				fclose($socket);
				return false;
			}
			if (!skfoAuthSmtpCommand($socket, base64_encode($pass), [235], $error)) {
				fclose($socket);
				return false;
			}
		}

		if (!skfoAuthSmtpCommand($socket, "MAIL FROM:<{$fromEmail}>", [250], $error)) {
			fclose($socket);
			return false;
		}
		if (!skfoAuthSmtpCommand($socket, "RCPT TO:<{$toEmail}>", [250, 251], $error)) {
			fclose($socket);
			return false;
		}
		if (!skfoAuthSmtpCommand($socket, 'DATA', [354], $error)) {
			fclose($socket);
			return false;
		}

		$encodedSubject = skfoAuthMimeHeader($subject);
		$encodedFromName = $fromName !== '' ? skfoAuthMimeHeader($fromName) : '';
		$fromHeader = $encodedFromName !== '' ? "{$encodedFromName} <{$fromEmail}>" : $fromEmail;
		$normalizedBody = str_replace(["\r\n", "\r"], "\n", $bodyText);
		$normalizedBody = str_replace("\n", "\r\n", $normalizedBody);

		$headers = [
			"Date: " . date(DATE_RFC2822),
			"From: {$fromHeader}",
			"To: <{$toEmail}>",
			"Subject: {$encodedSubject}",
			"MIME-Version: 1.0",
			"Content-Type: text/plain; charset=UTF-8",
			"Content-Transfer-Encoding: 8bit",
		];
		$data = implode("\r\n", $headers) . "\r\n\r\n" . $normalizedBody;
		$data = preg_replace('/^\./m', '..', $data) ?? $data;

		$written = fwrite($socket, $data . "\r\n.\r\n");
		if ($written === false) {
			$error = 'SMTP body write failed.';
			fclose($socket);
			return false;
		}

		if (!skfoAuthSmtpCommand($socket, '', [250], $error)) {
			fclose($socket);
			return false;
		}

		skfoAuthSmtpCommand($socket, 'QUIT', [221], $error);
		fclose($socket);
		$error = '';
		return true;
	}

	function skfoAuthSendMail(string $toEmail, string $subject, string $message, $sanitizer, $config, string &$error): bool {
		$error = '';
		$smtpProvider = strtolower(skfoAuthEnv('SKFO_SMTP_PROVIDER', ''));
		$smtpPresets = [
			'mailru' => ['host' => 'smtp.mail.ru', 'port' => 465, 'secure' => 'ssl'],
			'yandex' => ['host' => 'smtp.yandex.ru', 'port' => 465, 'secure' => 'ssl'],
			'gmail' => ['host' => 'smtp.gmail.com', 'port' => 465, 'secure' => 'ssl'],
			'sendgrid' => ['host' => 'smtp.sendgrid.net', 'port' => 587, 'secure' => 'tls'],
		];

		$providerPreset = $smtpPresets[$smtpProvider] ?? [];
		$smtpHost = skfoAuthEnv('SKFO_SMTP_HOST', (string) ($providerPreset['host'] ?? ''));
		$smtpPort = (int) skfoAuthEnv('SKFO_SMTP_PORT', (string) ($providerPreset['port'] ?? 587));
		$smtpSecure = skfoAuthEnv('SKFO_SMTP_SECURE', (string) ($providerPreset['secure'] ?? 'tls'));
		$smtpUser = skfoAuthEnv('SKFO_SMTP_USER', '');
		$smtpPass = skfoAuthEnv('SKFO_SMTP_PASS', '');
		$smtpFromEmail = skfoAuthEnv('SKFO_SMTP_FROM_EMAIL', skfoAuthEnv('SKFO_FROM_EMAIL', ''));
		$smtpFromName = skfoAuthEnv('SKFO_SMTP_FROM_NAME', 'SKFO.RU');

		$smtpExplicitlyConfigured = $smtpProvider !== '' || $smtpHost !== '' || $smtpUser !== '' || $smtpPass !== '' || $smtpFromEmail !== '';
		if ($smtpExplicitlyConfigured) {
			if ($smtpHost === '' || $smtpUser === '' || $smtpPass === '' || $smtpFromEmail === '') {
				$error = 'SMTP configured, but required vars are missing: host/user/pass/from_email.';
				return false;
			}

			$smtpConfig = [
				'host' => $smtpHost,
				'port' => $smtpPort,
				'user' => $smtpUser,
				'pass' => $smtpPass,
				'secure' => $smtpSecure,
				'from_email' => $smtpFromEmail,
				'from_name' => $smtpFromName,
			];
			$sent = skfoAuthSendViaSmtp($toEmail, $subject, $message, $smtpConfig, $error);
			if ($sent) return true;
			return false;
		}

		$fromEmail = trim((string) ($config->adminEmail ?? ''));
		if ($fromEmail === '') $fromEmail = 'no-reply@skfo.ru';

		try {
			$mail = wireMail();
			$mail->to($toEmail)
				->from($fromEmail)
				->subject($subject)
				->body($message)
				->bodyHTML(nl2br($sanitizer->entities($message)));
			$sent = (int) $mail->send();
			if ($sent < 1) {
				$error = 'WireMail send returned 0.';
				return false;
			}
			return true;
		} catch (\Throwable $e) {
			$error = $e->getMessage();
			return false;
		}
	}

	function skfoAuthSendCode($input, $session, $database, $sanitizer, $config, $log): void {
		$mode = trim((string) $input->post('mode'));
		if (!in_array($mode, ['login', 'register'], true)) $mode = 'login';

		$email = skfoAuthNormalizeEmail($sanitizer, (string) $input->post('email'));
		$name = skfoAuthNormalizeName((string) $input->post('name'));
		$ip = skfoAuthClientIp();
		$now = time();
		$nowSql = date('Y-m-d H:i:s', $now);

		if ($email === '') {
			skfoAuthJson(false, 'Укажите корректный email.', [], 422);
			return;
		}

		if ($mode === 'register' && $name === '') {
			skfoAuthJson(false, 'Введите имя для регистрации.', [], 422);
			return;
		}

		$account = skfoAuthFindAccountByEmail($database, $email);
		if ($mode === 'login' && !$account) {
			skfoAuthJson(false, 'Профиль с таким email не найден. Перейдите в регистрацию.', [], 404);
			return;
		}
		if ($mode === 'register' && $account) {
			skfoAuthJson(false, 'Профиль с таким email уже существует. Используйте вход.', [], 409);
			return;
		}

		$cooldownSince = date('Y-m-d H:i:s', $now - 60);
		$recentOtp = $database->prepare(
			"SELECT `id`, `created_at` FROM `site_auth_otps`
			WHERE `email`=:email AND `consumed_at` IS NULL AND `created_at`>=:since
			ORDER BY `id` DESC LIMIT 1"
		);
		$recentOtp->bindValue(':email', $email, \PDO::PARAM_STR);
		$recentOtp->bindValue(':since', $cooldownSince, \PDO::PARAM_STR);
		$recentOtp->execute();
		if ($recentOtp->fetch(\PDO::FETCH_ASSOC)) {
			skfoAuthJson(false, 'Код уже отправлен. Повторите через минуту.', ['cooldown' => 60], 429);
			return;
		}

		$windowSince = date('Y-m-d H:i:s', $now - 900);
		$emailCountStmt = $database->prepare(
			"SELECT COUNT(*) FROM `site_auth_otps` WHERE `email`=:email AND `created_at`>=:since"
		);
		$emailCountStmt->bindValue(':email', $email, \PDO::PARAM_STR);
		$emailCountStmt->bindValue(':since', $windowSince, \PDO::PARAM_STR);
		$emailCountStmt->execute();
		$emailCount = (int) $emailCountStmt->fetchColumn();
		if ($emailCount >= 3) {
			skfoAuthJson(false, 'Слишком много запросов кода. Попробуйте позже.', [], 429);
			return;
		}

		$ipCountStmt = $database->prepare(
			"SELECT COUNT(*) FROM `site_auth_otps` WHERE `ip`=:ip AND `created_at`>=:since"
		);
		$ipCountStmt->bindValue(':ip', $ip, \PDO::PARAM_STR);
		$ipCountStmt->bindValue(':since', $windowSince, \PDO::PARAM_STR);
		$ipCountStmt->execute();
		$ipCount = (int) $ipCountStmt->fetchColumn();
		if ($ipCount >= 10) {
			skfoAuthJson(false, 'Слишком много запросов с вашего IP. Попробуйте позже.', [], 429);
			return;
		}

		$code = (string) random_int(100000, 999999);
		$hash = password_hash($code, PASSWORD_DEFAULT);
		$expiresAt = date('Y-m-d H:i:s', $now + 600);
		$otpId = 0;

		try {
			$database->beginTransaction();

			$consumeOld = $database->prepare(
				"UPDATE `site_auth_otps` SET `consumed_at`=:consumed_at
				WHERE `email`=:email AND `consumed_at` IS NULL"
			);
			$consumeOld->bindValue(':consumed_at', $nowSql, \PDO::PARAM_STR);
			$consumeOld->bindValue(':email', $email, \PDO::PARAM_STR);
			$consumeOld->execute();

			$insert = $database->prepare(
				"INSERT INTO `site_auth_otps`
				(`email`, `mode`, `requested_name`, `code_hash`, `attempts`, `max_attempts`, `expires_at`, `ip`)
				VALUES
				(:email, :mode, :requested_name, :code_hash, 0, 5, :expires_at, :ip)"
			);
			$insert->bindValue(':email', $email, \PDO::PARAM_STR);
			$insert->bindValue(':mode', $mode, \PDO::PARAM_STR);
			$insert->bindValue(':requested_name', $name, \PDO::PARAM_STR);
			$insert->bindValue(':code_hash', $hash, \PDO::PARAM_STR);
			$insert->bindValue(':expires_at', $expiresAt, \PDO::PARAM_STR);
			$insert->bindValue(':ip', $ip, \PDO::PARAM_STR);
			$insert->execute();
			$otpId = (int) $database->lastInsertId();

			$database->commit();
		} catch (\Throwable $e) {
			if ($database->inTransaction()) $database->rollBack();
			$log->save('errors', 'auth send_code db error: ' . $e->getMessage());
			skfoAuthJson(false, 'Не удалось создать код. Попробуйте позже.', [], 500);
			return;
		}

		$subject = 'Код входа SKFO.RU';
		$message = "Ваш код подтверждения: {$code}\n\nКод действует 10 минут.\nЕсли это были не вы, просто проигнорируйте письмо.";
		$mailError = '';
		$sent = skfoAuthSendMail($email, $subject, $message, $sanitizer, $config, $mailError);
		if (!$sent) {
			$log->save('errors', 'auth send_code mail error: ' . $mailError);
			$consume = $database->prepare("UPDATE `site_auth_otps` SET `consumed_at`=:consumed_at WHERE `id`=:id");
			$consume->bindValue(':consumed_at', date('Y-m-d H:i:s'), \PDO::PARAM_STR);
			$consume->bindValue(':id', $otpId, \PDO::PARAM_INT);
			$consume->execute();

			skfoAuthJson(false, 'Не удалось отправить письмо. Проверьте настройки почты.', [], 500);
			return;
		}

		$data = ['cooldown' => 60];
		if (!empty($config->debug) && skfoAuthEnv('SKFO_AUTH_SHOW_DEBUG_CODE', '0') === '1') {
			$data['debug_code'] = $code;
		}

		skfoAuthJson(true, 'Код отправлен. Проверьте папку SPAM', $data);
	}

	function skfoAuthVerifyCode($input, $session, $database, $sanitizer): void {
		$mode = trim((string) $input->post('mode'));
		if (!in_array($mode, ['login', 'register'], true)) $mode = 'login';

		$email = skfoAuthNormalizeEmail($sanitizer, (string) $input->post('email'));
		$name = skfoAuthNormalizeName((string) $input->post('name'));
		$code = preg_replace('/\D+/', '', (string) $input->post('code'));
		$ip = skfoAuthClientIp();

		if ($email === '') {
			skfoAuthJson(false, 'Укажите корректный email.', [], 422);
			return;
		}
		if (!is_string($code) || strlen($code) !== 6) {
			skfoAuthJson(false, 'Введите 6-значный код из письма.', [], 422);
			return;
		}

		$stmt = $database->prepare(
			"SELECT `id`, `email`, `mode`, `requested_name`, `code_hash`, `attempts`, `max_attempts`, `expires_at`, `consumed_at`
			FROM `site_auth_otps`
			WHERE `email`=:email AND `consumed_at` IS NULL
			ORDER BY `id` DESC LIMIT 1"
		);
		$stmt->bindValue(':email', $email, \PDO::PARAM_STR);
		$stmt->execute();
		$otp = $stmt->fetch(\PDO::FETCH_ASSOC);
		if (!is_array($otp)) {
			skfoAuthJson(false, 'Сначала запросите код на почту.', [], 404);
			return;
		}

		$otpId = (int) ($otp['id'] ?? 0);
		$otpAttempts = (int) ($otp['attempts'] ?? 0);
		$otpMaxAttempts = max(1, (int) ($otp['max_attempts'] ?? 5));
		$otpExpiresAtTs = strtotime((string) ($otp['expires_at'] ?? '')) ?: 0;
		$nowTs = time();

		if ($otpExpiresAtTs <= $nowTs) {
			$consume = $database->prepare("UPDATE `site_auth_otps` SET `consumed_at`=:consumed_at WHERE `id`=:id");
			$consume->bindValue(':consumed_at', date('Y-m-d H:i:s'), \PDO::PARAM_STR);
			$consume->bindValue(':id', $otpId, \PDO::PARAM_INT);
			$consume->execute();
			skfoAuthJson(false, 'Срок действия кода истёк. Запросите новый.', [], 410);
			return;
		}

		if ($otpAttempts >= $otpMaxAttempts) {
			skfoAuthJson(false, 'Лимит попыток исчерпан. Запросите новый код.', [], 429);
			return;
		}

		$hash = (string) ($otp['code_hash'] ?? '');
		$isValid = $hash !== '' && password_verify($code, $hash);
		if (!$isValid) {
			$newAttempts = $otpAttempts + 1;
			$exhausted = $newAttempts >= $otpMaxAttempts;
			$update = $database->prepare(
				"UPDATE `site_auth_otps`
				SET `attempts`=:attempts, `consumed_at`=:consumed_at
				WHERE `id`=:id"
			);
			$update->bindValue(':attempts', $newAttempts, \PDO::PARAM_INT);
			$update->bindValue(':consumed_at', $exhausted ? date('Y-m-d H:i:s') : null, $exhausted ? \PDO::PARAM_STR : \PDO::PARAM_NULL);
			$update->bindValue(':id', $otpId, \PDO::PARAM_INT);
			$update->execute();

			$left = max(0, $otpMaxAttempts - $newAttempts);
			skfoAuthJson(false, 'Неверный код.', ['attempts_left' => $left], 422);
			return;
		}

		$otpMode = (string) ($otp['mode'] ?? 'login');
		if (!in_array($otpMode, ['login', 'register'], true)) $otpMode = 'login';
		$effectiveMode = $mode === 'register' ? 'register' : $otpMode;

		$account = skfoAuthFindAccountByEmail($database, $email);
		if ($effectiveMode === 'login' && !$account) {
			skfoAuthJson(false, 'Профиль не найден. Пройдите регистрацию.', [], 404);
			return;
		}
		if ($effectiveMode === 'register' && $account) {
			skfoAuthJson(false, 'Профиль уже существует. Используйте вход.', [], 409);
			return;
		}

		if ($effectiveMode === 'register') {
			$requestedName = trim((string) ($otp['requested_name'] ?? ''));
			$finalName = $name !== '' ? $name : $requestedName;
			if ($finalName === '') {
				skfoAuthJson(false, 'Введите имя для регистрации.', [], 422);
				return;
			}
			$account = skfoAuthCreateAccount($database, $email, $finalName, $ip);
			if (!$account) {
				skfoAuthJson(false, 'Не удалось создать профиль. Попробуйте позже.', [], 500);
				return;
			}
		}

		if (!$account) {
			skfoAuthJson(false, 'Профиль не найден.', [], 404);
			return;
		}

		$consume = $database->prepare("UPDATE `site_auth_otps` SET `consumed_at`=:consumed_at WHERE `id`=:id");
		$consume->bindValue(':consumed_at', date('Y-m-d H:i:s'), \PDO::PARAM_STR);
		$consume->bindValue(':id', $otpId, \PDO::PARAM_INT);
		$consume->execute();

		skfoAuthTouchLogin($database, (int) ($account['id'] ?? 0), $ip);
		skfoAuthSetSession($session, $account);

		skfoAuthJson(true, 'Успешный вход в профиль.', ['redirect' => '/profile/']);
	}

	function skfoAuthLogout($session): void {
		skfoAuthClearSession($session);
		skfoAuthJson(true, 'Вы вышли из профиля.', ['redirect' => '/']);
	}

	function skfoAuthHandleApiRequest($input, $session, $database, $sanitizer, $config, $log): void {
		try {
			$csrfValid = $session->CSRF->validate();
		} catch (\Throwable $e) {
			$csrfValid = false;
		}

		if (!$csrfValid) {
			skfoAuthJson(false, 'Ошибка безопасности запроса. Обновите страницу.', [], 403);
			return;
		}

		$action = trim((string) $input->post('auth_action'));
		if ($action === 'send_code') {
			skfoAuthSendCode($input, $session, $database, $sanitizer, $config, $log);
			return;
		}
		if ($action === 'verify_code') {
			skfoAuthVerifyCode($input, $session, $database, $sanitizer);
			return;
		}
		if ($action === 'logout') {
			skfoAuthLogout($session);
			return;
		}

		skfoAuthJson(false, 'Неизвестное действие авторизации.', [], 400);
	}
}
