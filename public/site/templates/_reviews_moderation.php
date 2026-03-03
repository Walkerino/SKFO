<?php namespace ProcessWire;

if(!function_exists(__NAMESPACE__ . '\\skfoReviewsEnsureTable')) {
	function skfoReviewsSanitizeTableName(string $table): string {
		$table = preg_replace('/[^a-zA-Z0-9_]+/', '', $table) ?? '';
		return trim($table);
	}

	function skfoReviewsEnsureTable($database, string $table = 'tour_reviews'): void {
		$table = skfoReviewsSanitizeTableName($table);
		if($table === '') {
			throw new \RuntimeException('Invalid reviews table name.');
		}

		$database->exec(
			"CREATE TABLE IF NOT EXISTS `$table` (
				`id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
				`page_id` INT UNSIGNED NOT NULL,
				`author` VARCHAR(120) NOT NULL,
				`review_text` TEXT NOT NULL,
				`rating` TINYINT UNSIGNED NOT NULL,
				`avatar_color` VARCHAR(16) NOT NULL DEFAULT 'blue',
				`content_hash` CHAR(64) NOT NULL DEFAULT '',
				`moderation_status` VARCHAR(16) NOT NULL DEFAULT 'approved',
				`moderation_flags` VARCHAR(255) NOT NULL DEFAULT '',
				`created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
				PRIMARY KEY (`id`),
				KEY `page_created` (`page_id`, `created_at`),
				KEY `page_status_created` (`page_id`, `moderation_status`, `created_at`),
				KEY `moderation_status` (`moderation_status`),
				KEY `content_hash` (`content_hash`)
			) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
		);

		$alters = [
			"ALTER TABLE `$table` ADD COLUMN `avatar_color` VARCHAR(16) NOT NULL DEFAULT 'blue'",
			"ALTER TABLE `$table` ADD COLUMN `content_hash` CHAR(64) NOT NULL DEFAULT ''",
			"ALTER TABLE `$table` ADD COLUMN `moderation_status` VARCHAR(16) NOT NULL DEFAULT 'approved'",
			"ALTER TABLE `$table` ADD COLUMN `moderation_flags` VARCHAR(255) NOT NULL DEFAULT ''",
			"ALTER TABLE `$table` ADD KEY `page_status_created` (`page_id`, `moderation_status`, `created_at`)",
			"ALTER TABLE `$table` ADD KEY `moderation_status` (`moderation_status`)",
			"ALTER TABLE `$table` ADD KEY `content_hash` (`content_hash`)",
		];

		foreach($alters as $sql) {
			try {
				$database->exec($sql);
			} catch(\Throwable $e) {
				// Ignore when column/index already exists.
			}
		}
	}

	function skfoReviewsToLower(string $value): string {
		return function_exists('mb_strtolower') ? mb_strtolower($value, 'UTF-8') : strtolower($value);
	}

	function skfoReviewsNormalizeForHash(string $value): string {
		$value = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
		$value = str_replace("\xc2\xa0", ' ', $value);
		$value = trim($value);
		if($value === '') return '';

		$value = skfoReviewsToLower($value);
		$value = str_replace('ё', 'е', $value);
		$value = preg_replace('/[^\p{L}\p{N}\s]+/u', ' ', $value) ?? $value;
		$value = preg_replace('/\s+/u', ' ', $value) ?? $value;
		return trim($value);
	}

	function skfoReviewsComputeContentHash(string $reviewText): string {
		$normalized = skfoReviewsNormalizeForHash($reviewText);
		if($normalized === '') return '';
		return hash('sha256', $normalized);
	}

	function skfoReviewsBanwordRules(): array {
		return [
			'politics' => [
				'label' => 'политика',
				'needles' => ['политик', 'президент', 'выбор', 'депутат', 'госдума', 'партия', 'санкц', 'войн', 'спецоперац'],
			],
			'adult' => [
				'label' => '18+',
				'needles' => ['18+', 'порн', 'эрот', 'секс', 'интим', 'xxx', 'onlyfans', 'онлифанс'],
			],
			'gambling' => [
				'label' => 'азартные игры',
				'needles' => ['казино', 'ставк', 'букмекер', 'тотализатор', 'покер'],
			],
			'drugs' => [
				'label' => 'наркотики',
				'needles' => ['наркот', 'закладк', 'кокаин', 'героин', 'марихуан', 'спайс'],
			],
		];
	}

	function skfoReviewsContainsNeedle(string $haystack, string $needle): bool {
		if($haystack === '' || $needle === '') return false;
		if(function_exists('mb_strpos')) return mb_strpos($haystack, $needle, 0, 'UTF-8') !== false;
		return strpos($haystack, $needle) !== false;
	}

	function skfoReviewsDetectBanwordCategories(string $reviewText): array {
		$normalized = skfoReviewsToLower(trim($reviewText));
		$normalized = str_replace('ё', 'е', $normalized);
		if($normalized === '') return [];

		$found = [];
		foreach(skfoReviewsBanwordRules() as $category => $rule) {
			$needles = isset($rule['needles']) && is_array($rule['needles']) ? $rule['needles'] : [];
			foreach($needles as $needle) {
				$needle = skfoReviewsToLower((string) $needle);
				if($needle === '') continue;
				if(skfoReviewsContainsNeedle($normalized, $needle)) {
					$found[] = (string) $category;
					break;
				}
			}
		}

		return array_values(array_unique($found));
	}

	function skfoReviewsBanwordLabels(array $categories): array {
		$rules = skfoReviewsBanwordRules();
		$labels = [];
		foreach($categories as $category) {
			$category = trim((string) $category);
			if($category === '' || !isset($rules[$category])) continue;
			$labels[] = (string) ($rules[$category]['label'] ?? $category);
		}
		return array_values(array_unique($labels));
	}

	function skfoReviewsEncodeFlags(array $flags): string {
		$out = [];
		foreach($flags as $flag) {
			$flag = trim((string) $flag);
			if($flag !== '') $out[] = $flag;
		}
		$out = array_values(array_unique($out));
		$value = implode(',', $out);
		if(function_exists('mb_substr')) {
			return mb_substr($value, 0, 255, 'UTF-8');
		}
		return substr($value, 0, 255);
	}

	function skfoReviewsDecodeFlags(string $flagsRaw): array {
		$flagsRaw = trim($flagsRaw);
		if($flagsRaw === '') return [];

		$parts = explode(',', $flagsRaw);
		$out = [];
		foreach($parts as $part) {
			$part = trim((string) $part);
			if($part !== '') $out[] = $part;
		}
		return array_values(array_unique($out));
	}

	function skfoReviewsHasDuplicate($database, string $table, int $pageId, string $contentHash, int $excludeId = 0): bool {
		$table = skfoReviewsSanitizeTableName($table);
		if($table === '' || $pageId < 1 || $contentHash === '') return false;

		$sql = "SELECT COUNT(*) FROM `$table` WHERE `page_id`=:page_id AND `content_hash`=:content_hash";
		if($excludeId > 0) {
			$sql .= " AND `id`!=:exclude_id";
		}

		$stmt = $database->prepare($sql);
		$stmt->bindValue(':page_id', $pageId, \PDO::PARAM_INT);
		$stmt->bindValue(':content_hash', $contentHash, \PDO::PARAM_STR);
		if($excludeId > 0) {
			$stmt->bindValue(':exclude_id', $excludeId, \PDO::PARAM_INT);
		}
		$stmt->execute();
		return (int) $stmt->fetchColumn() > 0;
	}

	function skfoReviewsBuildModerationDecision($database, string $table, int $pageId, string $reviewText): array {
		$contentHash = skfoReviewsComputeContentHash($reviewText);
		$banCategories = skfoReviewsDetectBanwordCategories($reviewText);
		$isDuplicate = $contentHash !== '' ? skfoReviewsHasDuplicate($database, $table, $pageId, $contentHash) : false;

		$status = 'approved';
		$flags = [];

		if($isDuplicate) {
			$flags[] = 'duplicate';
		}
		foreach($banCategories as $category) {
			$flags[] = 'ban-' . $category;
		}

		if(count($banCategories)) {
			$status = 'blocked';
		} elseif($isDuplicate) {
			$status = 'duplicate';
		}

		return [
			'status' => $status,
			'content_hash' => $contentHash,
			'is_duplicate' => $isDuplicate,
			'ban_categories' => $banCategories,
			'flags' => array_values(array_unique($flags)),
		];
	}

	function skfoReviewsStatusLabel(string $status): string {
		$labels = [
			'approved' => 'Одобрен',
			'duplicate' => 'Дубликат',
			'blocked' => 'Заблокирован',
			'hidden' => 'Скрыт',
		];
		$status = trim($status);
		return $labels[$status] ?? 'Неизвестно';
	}

	function skfoReviewsBackfillHashes($database, string $table, int $limit = 200): void {
		$table = skfoReviewsSanitizeTableName($table);
		if($table === '') return;

		$limit = max(1, min(1000, (int) $limit));
		$stmt = $database->query("SELECT `id`, `review_text` FROM `$table` WHERE `content_hash`='' OR `content_hash` IS NULL LIMIT " . $limit);
		if(!$stmt) return;

		$rows = $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
		if(!count($rows)) return;

		$update = $database->prepare("UPDATE `$table` SET `content_hash`=:content_hash WHERE `id`=:id");
		foreach($rows as $row) {
			$id = (int) ($row['id'] ?? 0);
			if($id < 1) continue;
			$hash = skfoReviewsComputeContentHash((string) ($row['review_text'] ?? ''));
			$update->bindValue(':content_hash', $hash, \PDO::PARAM_STR);
			$update->bindValue(':id', $id, \PDO::PARAM_INT);
			$update->execute();
		}
	}
}
