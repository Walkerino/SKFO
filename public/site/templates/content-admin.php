<?php namespace ProcessWire;

$isLoggedInCmsUser = $user && $user->isLoggedin();
$isAllowedUser = $isLoggedInCmsUser && ($user->isSuperuser() || $user->hasPermission('page-edit'));
if(!$isLoggedInCmsUser) {
	$session->redirect($config->urls->admin . 'login/?continue=' . rawurlencode((string) $page->url));
}
if(!$isAllowedUser) {
	throw new WirePermissionException('You do not have permission to access this page.');
}

$flashPrefix = 'content_admin_';
$pullFlash = static function(Session $session, string $key): string {
	$value = $session->get($key);
	$session->remove($key);
	return is_string($value) ? $value : '';
};
$setFlash = static function(Session $session, string $key, string $value): void {
	if($value === '') {
		$session->remove($key);
		return;
	}
	$session->set($key, $value);
};

$noticeSuccess = $pullFlash($session, $flashPrefix . 'success');
$noticeError = $pullFlash($session, $flashPrefix . 'error');

$tabs = [
	'tours' => 'Туры',
	'articles' => 'Статьи',
	'places' => 'Места',
	'hotels' => 'Отели',
	'placements' => 'Где показывать',
];
$activeTab = trim((string) $input->get('tab'));
if(!isset($tabs[$activeTab])) $activeTab = 'tours';

$contentRoot = $pages->get('/content/');
$contentAdminBaseUrl = rtrim((string) $config->urls->root, '/') . '/content-admin/';
$catalogParents = [
	'tours' => $pages->get('/content/tours/'),
	'articles' => $pages->get('/content/articles/'),
	'places' => $pages->get('/content/places/'),
	'hotels' => $pages->get('/content/hotels/'),
];

$catalogConfigs = [
	'tours' => [
		'template' => 'tour',
		'parent' => $catalogParents['tours'],
		'title' => 'Каталог туров',
		'singular' => 'тур',
		'image_field' => 'tour_cover_image',
	],
	'articles' => [
		'template' => 'article',
		'parent' => $catalogParents['articles'],
		'title' => 'Каталог статей',
		'singular' => 'статья',
		'image_field' => 'article_cover_image',
	],
	'places' => [
		'template' => 'place',
		'parent' => $catalogParents['places'],
		'title' => 'Каталог мест',
		'singular' => 'место',
		'image_field' => 'place_image',
	],
	'hotels' => [
		'template' => 'hotel',
		'parent' => $catalogParents['hotels'],
		'title' => 'Каталог отелей',
		'singular' => 'отель',
		'image_field' => 'hotel_image',
	],
];
$titleField = $fields->get('title');
$ensureTemplateTitleSupport = static function(?Template $template) use ($user, $templates, $titleField): bool {
	if(!$template || !$template->id || !$template->fieldgroup || !$titleField || !$titleField->id) return false;

	$hasTitle = $template->fieldgroup->has($titleField);
	if(!$hasTitle && $user && $user->isSuperuser()) {
		$template->fieldgroup->add($titleField);
		$template->fieldgroup->save();
		$hasTitle = true;
	}

	if((int) $template->get('noGlobal') !== 0 && $user && $user->isSuperuser()) {
		$template->set('noGlobal', 0);
		$templates->save($template);
		$hasTitle = $template->fieldgroup->has($titleField);
	}

	return (bool) $hasTitle;
};
$catalogTemplateTitleSupport = [];
foreach($catalogConfigs as $entityType => $catalogConfig) {
	$template = $templates->get((string) ($catalogConfig['template'] ?? ''));
	$catalogTemplateTitleSupport[$entityType] = $ensureTemplateTitleSupport($template);
}

$regionPages = $pages->find('template=region, include=all, sort=title, limit=200');
$tourRegionOptions = [];
if($regionPages instanceof PageArray && $regionPages->count()) {
	foreach($regionPages as $regionPage) {
		$regionTitle = trim((string) $regionPage->title);
		if($regionTitle !== '') $tourRegionOptions[] = $regionTitle;
	}
}
if(!count($tourRegionOptions)) {
	$tourRegionOptions = [
		'Республика Дагестан',
		'Кабардино-Балкарская Республика',
		'Карачаево-Черкесская Республика',
		'Республика Ингушетия',
		'Республика Северная Осетия',
		'Ставропольский край',
		'Чеченская Республика',
	];
}
$tourRegionOptions = array_values(array_unique($tourRegionOptions));
sort($tourRegionOptions, SORT_NATURAL | SORT_FLAG_CASE);

$tourIncludedLibrary = [
	'accommodation' => 'Проживание в гостевых домах в горах',
	'meals' => 'Трехразовое питание',
	'tickets' => 'Входные билеты на локации',
	'boat' => 'Прогулка на катере',
	'museum' => 'Посещение музея',
	'transfer' => 'Трансфер из/в аэропорт',
	'guide' => 'Сопровождение гида',
	'insurance' => 'Страховка',
];
$hotelAmenitiesLibrary = [
	'wifi' => 'Бесплатный Wifi',
	'parking' => 'Парковка',
	'elevator' => 'Лифт',
	'soundproof_rooms' => 'Звукоизолированные номера',
	'air_conditioning' => 'Кондиционер',
	'kids' => 'Подходит для детей',
	'tv' => 'Телевизор',
	'spa' => 'Spa-центр',
	'minibar' => 'Мини-бар',
	'breakfast' => 'Завтрак',
	'transfer' => 'Трансфер',
	'accessible' => 'Удобства для людей с ограниченными возможностями',
	'gym' => 'Спортивный зал',
];

$tourSeasonMonthMap = [
	1 => 'Январь',
	2 => 'Февраль',
	3 => 'Март',
	4 => 'Апрель',
	5 => 'Май',
	6 => 'Июнь',
	7 => 'Июль',
	8 => 'Август',
	9 => 'Сентябрь',
	10 => 'Октябрь',
	11 => 'Ноябрь',
	12 => 'Декабрь',
];
$tourSeasonMonthHints = [
	1 => ['январ', 'янв'],
	2 => ['феврал', 'фев'],
	3 => ['март', 'мар'],
	4 => ['апрел', 'апр'],
	5 => ['май', 'мая'],
	6 => ['июн'],
	7 => ['июл'],
	8 => ['август', 'авг'],
	9 => ['сентябр', 'сен'],
	10 => ['октябр', 'окт'],
	11 => ['ноябр', 'ноя'],
	12 => ['декабр', 'дек'],
];

$tourDifficultyUiMap = [
	'basic' => 'Базовая',
	'medium' => 'Средняя',
	'extreme' => 'Экстремальная',
];

$tourAgeOptions = ['0+', '6+', '12+', '16+', '18+'];

$toLower = static function(string $value): string {
	return function_exists('mb_strtolower') ? mb_strtolower(trim($value), 'UTF-8') : strtolower(trim($value));
};

$repairBrokenCyrillicX = static function(string $value): string {
	$repaired = preg_replace('/(?<=\p{Cyrillic})\?\R+(?=\p{Cyrillic})/u', 'х', $value);
	return $repaired !== null ? $repaired : $value;
};

$normalizeIncludedItemText = static function(string $value): string {
	$line = str_replace("\xc2\xa0", ' ', $value);
	$line = trim($line);
	$line = preg_replace('/^\s*(?:[\x{2022}\x{2023}\x{25E6}\x{2043}\x{2219}•◦·▪●\-–—*]+|\d+[.)])\s*/u', '', $line) ?? $line;
	$line = preg_replace('/\s+/u', ' ', $line) ?? $line;
	return trim($line);
};

$toIncludedItemKey = static function(string $value) use ($normalizeIncludedItemText, $toLower): string {
	return $toLower($normalizeIncludedItemText($value));
};

$tourIncludedCodeByLabel = [];
foreach($tourIncludedLibrary as $code => $label) {
	$tourIncludedCodeByLabel[$toIncludedItemKey((string) $label)] = $code;
}
$tourIncludedLabelAliases = [
	'проживание в гостевых домах' => 'accommodation',
	'трёхразовое питание' => 'meals',
	'сопровождение гидом' => 'guide',
	'трансфер из аэропорта' => 'transfer',
	'трансфер в аэропорт' => 'transfer',
	'страхование' => 'insurance',
];
foreach($tourIncludedLabelAliases as $aliasLabel => $aliasCode) {
	if(!isset($tourIncludedLibrary[$aliasCode])) continue;
	$tourIncludedCodeByLabel[$toIncludedItemKey((string) $aliasLabel)] = (string) $aliasCode;
}
$hotelAmenityCodeByLabel = [];
foreach($hotelAmenitiesLibrary as $code => $label) {
	$hotelAmenityCodeByLabel[$toIncludedItemKey((string) $label)] = $code;
}
$hotelAmenityLabelAliases = [
	'wi-fi' => 'wifi',
	'wi fi' => 'wifi',
	'wifi' => 'wifi',
	'бесплатный wi-fi' => 'wifi',
	'бесплатный wi fi' => 'wifi',
	'бесплатный wifi' => 'wifi',
	'для детей' => 'kids',
	'spa центр' => 'spa',
	'спа центр' => 'spa',
	'спа-центр' => 'spa',
	'spa-центр' => 'spa',
	'спортивный зал' => 'gym',
	'спортзал' => 'gym',
	'удобства для маломобильных гостей' => 'accessible',
];
foreach($hotelAmenityLabelAliases as $aliasLabel => $aliasCode) {
	if(!isset($hotelAmenitiesLibrary[$aliasCode])) continue;
	$hotelAmenityCodeByLabel[$toIncludedItemKey((string) $aliasLabel)] = (string) $aliasCode;
}

$getImageUrlFromValue = static function($imageValue): string {
	if($imageValue instanceof Pageimage) return $imageValue->url;
	if($imageValue instanceof Pageimages && $imageValue->count()) return $imageValue->first()->url;
	return '';
};

$toIntList = static function($raw): array {
	$ids = [];
	foreach((array) $raw as $id) {
		$id = (int) $id;
		if($id > 0) $ids[] = $id;
	}
	return array_values(array_unique($ids));
};

$createUniqueName = static function(Pages $pages, Page $parent, string $baseName): string {
	$baseName = trim($baseName);
	if($baseName === '') $baseName = 'item';

	$candidate = $baseName;
	$suffix = 1;
	while($pages->get($parent->path . $candidate . '/')->id) {
		$candidate = $baseName . '-' . $suffix;
		$suffix++;
	}
	return $candidate;
};

$saveImageField = static function(Page $entityPage, string $fieldName, string $uploadKey, string $clearKey) use ($input): void {
	if(!$entityPage->hasField($fieldName)) return;

	$clearRequested = (int) $input->post($clearKey) === 1;
	$hasUpload = isset($_FILES[$uploadKey]['tmp_name']) && is_uploaded_file((string) $_FILES[$uploadKey]['tmp_name']);

	$entityPage->of(false);
	$images = $entityPage->getUnformatted($fieldName);
	if($images instanceof Pageimages && ($clearRequested || $hasUpload)) {
		foreach($images as $img) {
			$images->remove($img);
		}
	}
	if($hasUpload && $images instanceof Pageimages) {
		$images->add((string) $_FILES[$uploadKey]['tmp_name']);
	}
	$entityPage->save($fieldName);
};

$normalizeEntityIds = static function(array $ids, string $templateName) use ($pages): array {
	$valid = [];
	foreach($ids as $id) {
		$page = $pages->get((int) $id);
		if(!$page || !$page->id || !$page->template || $page->template->name !== $templateName) continue;
		$valid[] = (int) $page->id;
	}
	return array_values(array_unique($valid));
};

$normalizeLines = static function(string $value) use ($normalizeIncludedItemText, $repairBrokenCyrillicX): array {
	$value = $repairBrokenCyrillicX($value);
	$lines = preg_split('/\R+/u', trim($value)) ?: [];
	$out = [];
	foreach($lines as $line) {
		$line = $normalizeIncludedItemText((string) $line);
		if($line !== '') $out[] = $line;
	}
	return array_values(array_unique($out));
};

$normalizeIncludedItems = static function(array $items) use ($toLower, $normalizeIncludedItemText): array {
	$normalized = [];
	$seen = [];
	foreach($items as $item) {
		$line = $normalizeIncludedItemText((string) $item);
		if($line === '') continue;
		$key = $toLower($line);
		if(isset($seen[$key])) continue;
		$seen[$key] = true;
		$normalized[] = $line;
	}
	return $normalized;
};

$toMoneyString = static function(string $raw): string {
	$digits = preg_replace('/[^\d]+/', '', $raw) ?? '';
	if($digits === '') return '';
	$amount = (int) $digits;
	if($amount <= 0) return '';
	return number_format($amount, 0, '', ' ') . ' ₽';
};

$extractSeasonMonthSelection = static function(string $seasonText) use ($tourSeasonMonthHints, $toLower): array {
	$selection = [];
	$seasonText = $toLower($seasonText);
	if($seasonText === '') return $selection;

	foreach($tourSeasonMonthHints as $monthNum => $hints) {
		foreach($hints as $hint) {
			if($hint !== '' && strpos($seasonText, $hint) !== false) {
				$selection[] = (int) $monthNum;
				break;
			}
		}
	}
	return array_values(array_unique($selection));
};

$getTourDifficultyOptionId = static function(string $uiValue) use ($fields, $toLower): int {
	$field = $fields->get('tour_difficulty_level');
	if(!$field || !$field->id || !($field->type instanceof FieldtypeOptions)) return 0;

	$value = trim($uiValue);
	if($value === '') return 0;

	$expectedTitle = '';
	if($value === 'basic') $expectedTitle = 'базовая';
	if($value === 'medium') $expectedTitle = 'средняя';
	if($value === 'extreme') $expectedTitle = 'экстремальная';

	$options = $field->type->getOptions($field);
	$fallbackHighId = 0;

	foreach($options as $option) {
		$title = $toLower((string) $option->title);
		if($title === 'высокая') $fallbackHighId = (int) $option->id;
		if($expectedTitle !== '' && $title === $expectedTitle) return (int) $option->id;
	}

	if($value === 'extreme') return $fallbackHighId;
	return 0;
};

$collectTourIncludedFromPage = static function(Page $entityPage) use ($normalizeLines, $normalizeIncludedItems): array {
	$includedFromText = [];
	if($entityPage->hasField('tour_included')) {
		$includedFromText = $normalizeLines((string) $entityPage->getUnformatted('tour_included'));
	}
	$includedFromText = $normalizeIncludedItems($includedFromText);
	if(count($includedFromText)) return $includedFromText;

	$includedFromRepeater = [];
	if($entityPage->hasField('tour_included_items')) {
		$repeaterItems = $entityPage->getUnformatted('tour_included_items');
		if($repeaterItems instanceof PageArray && $repeaterItems->count()) {
			foreach($repeaterItems as $itemPage) {
				if(!$itemPage instanceof Page || !$itemPage->hasField('tour_included_item_text')) continue;
				$text = trim((string) $itemPage->tour_included_item_text);
				if($text !== '') $includedFromRepeater[] = $text;
			}
		}
	}

	return $normalizeIncludedItems($includedFromRepeater);
};

$collectUploadedTmpByRow = static function(string $filesKey, int $rowIndex): array {
	if(!isset($_FILES[$filesKey])) return [];
	$tmpNodes = $_FILES[$filesKey]['tmp_name'][$rowIndex] ?? [];
	$errorNodes = $_FILES[$filesKey]['error'][$rowIndex] ?? [];
	if(!is_array($tmpNodes)) $tmpNodes = [$tmpNodes];
	if(!is_array($errorNodes)) $errorNodes = [$errorNodes];

	$out = [];
	foreach($tmpNodes as $fileIndex => $tmpPath) {
		$error = (int) ($errorNodes[$fileIndex] ?? UPLOAD_ERR_NO_FILE);
		if($error !== UPLOAD_ERR_OK) continue;
		$tmpPath = (string) $tmpPath;
		if($tmpPath === '' || !is_uploaded_file($tmpPath)) continue;
		$out[] = $tmpPath;
	}
	return $out;
};

$catalogItems = [
	'tours' => $pages->find('template=tour, include=all, sort=title, limit=500'),
	'articles' => $pages->find('template=article, include=all, sort=-article_publish_date, limit=500'),
	'places' => $pages->find('template=place, include=all, sort=title, limit=500'),
	'hotels' => $pages->find('template=hotel, include=all, sort=title, limit=500'),
];

$homePage = $pages->get('/');
$articlesPage = $pages->get('/articles/');
$hotelsPage = $pages->get('/hotels/');

$currentEntity = null;
$editId = (int) $input->get('id');
if($editId > 0 && isset($catalogConfigs[$activeTab])) {
	$candidate = $pages->get($editId);
	$expectedTemplate = (string) $catalogConfigs[$activeTab]['template'];
	if($candidate && $candidate->id && $candidate->template && $candidate->template->name === $expectedTemplate) {
		$currentEntity = $candidate;
	}
}

if($input->requestMethod() === 'POST') {
	$csrfValid = false;
	try {
		$csrfValid = $session->CSRF->validate();
	} catch(\Throwable $e) {
		$csrfValid = false;
	}

	if(!$csrfValid) {
		$setFlash($session, $flashPrefix . 'error', 'Ошибка CSRF. Обновите страницу и повторите.');
		$session->redirect($contentAdminBaseUrl . '?tab=' . rawurlencode($activeTab));
	}

	$action = trim((string) $input->post('action'));

	if($action === 'save_entity') {
		$entityType = trim((string) $input->post('entity_type'));
			if(!isset($catalogConfigs[$entityType])) {
				$setFlash($session, $flashPrefix . 'error', 'Неизвестный тип контента.');
				$session->redirect($contentAdminBaseUrl . '?tab=' . rawurlencode($activeTab));
			}
		if(empty($catalogTemplateTitleSupport[$entityType])) {
			$setFlash($session, $flashPrefix . 'error', 'Не удаётся сохранить заголовок: у шаблона отсутствует системное поле title. Откройте /processwire/ под superuser и обновите страницу.');
			$session->redirect($contentAdminBaseUrl . '?tab=' . rawurlencode($entityType));
		}

		$config = $catalogConfigs[$entityType];
		$parent = $config['parent'];
			if(!$parent || !$parent->id) {
				$setFlash($session, $flashPrefix . 'error', 'Каталог не найден. Откройте /processwire/ один раз и обновите страницу.');
				$session->redirect($contentAdminBaseUrl . '?tab=' . rawurlencode($activeTab));
			}

		$entityId = (int) $input->post('entity_id');
		$entityPage = null;

		if($entityId > 0) {
			$entityPage = $pages->get($entityId);
			$isValidEntity = $entityPage && $entityPage->id && $entityPage->template && $entityPage->template->name === $config['template'];
				if(!$isValidEntity) {
					$setFlash($session, $flashPrefix . 'error', 'Запись для редактирования не найдена.');
					$session->redirect($contentAdminBaseUrl . '?tab=' . rawurlencode($entityType));
				}
		} else {
			$entityPage = new Page();
			$entityPage->template = $templates->get($config['template']);
			$entityPage->parent = $parent;
		}

		$title = trim((string) $input->post('title'));
		if($title === '') {
			$title = 'Новая запись';
		}

		$tourIncludedItemsToPersist = [];
		$tourDaysToPersist = [];

		$entityPage->of(false);
		$entityPage->title = $title;
		if(method_exists($entityPage, 'setLanguageValue')) {
			$languages = wire('languages');
			if($languages instanceof Languages) {
				foreach($languages as $language) {
					if($language instanceof Language) {
						$entityPage->setLanguageValue($language, 'title', $title);
					}
				}
			}
		}
		if((int) $entityPage->id === 0) {
			$baseName = $sanitizer->pageNameUTF8($title, true);
			$entityPage->name = $createUniqueName($pages, $parent, $baseName);
		}

		if($entityType === 'tours') {
			$tourRegion = trim((string) $input->post('tour_region'));
			$tourDescription = trim((string) $input->post('tour_description'));
			$tourDurationDays = max(0, (int) $input->post('tour_duration_days'));
			$tourGroupSize = max(0, (int) $input->post('tour_group_size'));
			$tourDifficultyInput = trim((string) $input->post('tour_difficulty_level'));
			$tourAge = trim((string) $input->post('tour_age'));
			$tourPrice = $toMoneyString((string) $input->post('tour_price_per_person'));

			$tourSeasonMonthIds = $toIntList($_POST['tour_season_months'] ?? []);
			$tourSeasonLabels = [];
			foreach($tourSeasonMonthIds as $monthId) {
				if(isset($tourSeasonMonthMap[$monthId])) {
					$tourSeasonLabels[] = $tourSeasonMonthMap[$monthId];
				}
			}

			if($entityPage->hasField('tour_region')) $entityPage->tour_region = $tourRegion;
			if($entityPage->hasField('tour_description')) $entityPage->tour_description = $tourDescription;
			if($entityPage->hasField('tour_duration')) $entityPage->tour_duration = $tourDurationDays > 0 ? $tourDurationDays . ' дней' : '';
			if($entityPage->hasField('tour_group')) $entityPage->tour_group = $tourGroupSize > 0 ? $tourGroupSize . ' человек' : '';
			if($entityPage->hasField('tour_season')) $entityPage->tour_season = implode(', ', $tourSeasonLabels);
			if($entityPage->hasField('tour_age')) $entityPage->tour_age = in_array($tourAge, $tourAgeOptions, true) ? $tourAge : '';
			if($entityPage->hasField('tour_price')) $entityPage->tour_price = $tourPrice;
			if($entityPage->hasField('tour_difficulty')) {
				$entityPage->tour_difficulty = $tourDifficultyUiMap[$tourDifficultyInput] ?? '';
			}
			if($entityPage->hasField('tour_difficulty_level')) {
				$optionId = $getTourDifficultyOptionId($tourDifficultyInput);
				$entityPage->set('tour_difficulty_level', $optionId > 0 ? $optionId : null);
			}

			$selectedIncludeCodes = array_values(array_unique(array_map('strval', (array) ($_POST['tour_included_selected'] ?? []))));
			foreach($selectedIncludeCodes as $code) {
				if(isset($tourIncludedLibrary[$code])) {
					$tourIncludedItemsToPersist[] = $tourIncludedLibrary[$code];
				}
			}
			$rawTourIncludedCustom = $_POST['tour_included_custom'] ?? '';
			if(is_array($rawTourIncludedCustom)) {
				$rawTourIncludedCustom = implode("\n", array_map('strval', $rawTourIncludedCustom));
			}
			$tourIncludedItemsToPersist = array_merge($tourIncludedItemsToPersist, $normalizeLines((string) $rawTourIncludedCustom));
			$tourIncludedItemsToPersist = $normalizeIncludedItems($tourIncludedItemsToPersist);
			if($entityPage->hasField('tour_included')) {
				$entityPage->tour_included = implode("\n", $tourIncludedItemsToPersist);
			}

			$postedTourDays = isset($_POST['tour_days']) && is_array($_POST['tour_days']) ? $_POST['tour_days'] : [];
			foreach($postedTourDays as $rowIndex => $dayData) {
				$rowIndex = (int) $rowIndex;
				$dayId = isset($dayData['id']) ? (int) $dayData['id'] : 0;
				$dayTitle = trim((string) ($dayData['title'] ?? ''));
				$dayDescription = trim((string) ($dayData['description'] ?? ''));
				$dayUploads = $collectUploadedTmpByRow('tour_day_images', $rowIndex);

				if($dayTitle === '' && $dayDescription === '' && $dayId < 1 && !count($dayUploads)) continue;

				$tourDaysToPersist[] = [
					'row' => $rowIndex,
					'id' => $dayId,
					'title' => $dayTitle,
					'description' => $dayDescription,
					'uploads' => $dayUploads,
				];
			}
			usort($tourDaysToPersist, static function(array $a, array $b): int {
				return ((int) ($a['row'] ?? 0)) <=> ((int) ($b['row'] ?? 0));
			});
		}

		if($entityType === 'articles') {
			if($entityPage->hasField('article_topic')) $entityPage->article_topic = trim((string) $input->post('article_topic'));
			if($entityPage->hasField('article_publish_date')) {
				$dateRaw = trim((string) $input->post('article_publish_date'));
				$entityPage->article_publish_date = $dateRaw !== '' ? strtotime($dateRaw) : 0;
			}
			if($entityPage->hasField('article_excerpt')) $entityPage->article_excerpt = trim((string) $input->post('article_excerpt'));
			if($entityPage->hasField('article_content')) $entityPage->article_content = trim((string) $input->post('article_content'));
		}

		if($entityType === 'places') {
			if($entityPage->hasField('place_region')) $entityPage->place_region = trim((string) $input->post('place_region'));
			if($entityPage->hasField('place_summary')) $entityPage->place_summary = trim((string) $input->post('place_summary'));
		}

		if($entityType === 'hotels') {
			if($entityPage->hasField('hotel_city')) $entityPage->hotel_city = trim((string) $input->post('hotel_city'));
			if($entityPage->hasField('hotel_region')) $entityPage->hotel_region = trim((string) $input->post('hotel_region'));
			if($entityPage->hasField('hotel_rating')) $entityPage->hotel_rating = (float) $input->post('hotel_rating');
			if($entityPage->hasField('hotel_price')) $entityPage->hotel_price = (int) $input->post('hotel_price');
			if($entityPage->hasField('hotel_max_guests')) $entityPage->hotel_max_guests = max(1, (int) $input->post('hotel_max_guests'));
			$hotelAmenitiesToPersist = [];
			$selectedAmenityCodes = array_values(array_unique(array_map('strval', (array) ($_POST['hotel_amenities_selected'] ?? []))));
			foreach($selectedAmenityCodes as $code) {
				if(isset($hotelAmenitiesLibrary[$code])) $hotelAmenitiesToPersist[] = $code;
			}

			$rawHotelAmenitiesCustom = $_POST['hotel_amenities_custom'] ?? '';
			if(is_array($rawHotelAmenitiesCustom)) {
				$rawHotelAmenitiesCustom = implode("\n", array_map('strval', $rawHotelAmenitiesCustom));
			}
			$hotelAmenitiesCustomLines = $normalizeLines((string) $rawHotelAmenitiesCustom);
			foreach($hotelAmenitiesCustomLines as $line) {
				$line = trim((string) $line);
				if($line === '') continue;

				if(isset($hotelAmenitiesLibrary[$line])) {
					$hotelAmenitiesToPersist[] = $line;
					continue;
				}

				$lineKey = $toIncludedItemKey($line);
				if(isset($hotelAmenityCodeByLabel[$lineKey])) {
					$hotelAmenitiesToPersist[] = $hotelAmenityCodeByLabel[$lineKey];
					continue;
				}

				$hotelAmenitiesToPersist[] = $line;
			}
			$hotelAmenitiesToPersist = $normalizeIncludedItems($hotelAmenitiesToPersist);
			if($entityPage->hasField('hotel_amenities')) $entityPage->hotel_amenities = implode("\n", $hotelAmenitiesToPersist);
		}

		$pages->save($entityPage);
		if($entityPage->hasField('title')) {
			$entityPage->save('title');
		}
		$saveImageField($entityPage, (string) $config['image_field'], 'image_upload', 'clear_image');

		if($entityType === 'tours') {
			$entityPage = $pages->get((int) $entityPage->id);
			if($entityPage && $entityPage->id) {
				$entityPage->of(false);

				if($entityPage->hasField('tour_included_items')) {
					$existingIncludedRepeater = $entityPage->getUnformatted('tour_included_items');
					if($existingIncludedRepeater instanceof PageArray) {
						foreach($existingIncludedRepeater as $itemPage) {
							$entityPage->tour_included_items->remove($itemPage);
						}
					}
					foreach($tourIncludedItemsToPersist as $line) {
						$item = method_exists($entityPage->tour_included_items, 'getNewItem')
							? $entityPage->tour_included_items->getNewItem()
							: $entityPage->tour_included_items->getNew();
						$item->of(false);
						if($item->hasField('tour_included_item_text')) $item->tour_included_item_text = $line;
						$item->save();
					}
					$entityPage->save('tour_included_items');
				}

				if($entityPage->hasField('tour_days')) {
					$currentTourDays = $entityPage->getUnformatted('tour_days');
					$existingDaysById = [];
					if($currentTourDays instanceof PageArray) {
						foreach($currentTourDays as $existingDayPage) {
							$existingDaysById[(int) $existingDayPage->id] = $existingDayPage;
						}
					}

					$rowToDayPage = [];
					foreach($tourDaysToPersist as $dayRow) {
						$targetDay = null;
						$dayId = (int) ($dayRow['id'] ?? 0);

						if($dayId > 0 && isset($existingDaysById[$dayId])) {
							$targetDay = $existingDaysById[$dayId];
						} else {
							$targetDay = method_exists($entityPage->tour_days, 'getNewItem')
								? $entityPage->tour_days->getNewItem()
								: $entityPage->tour_days->getNew();
							$targetDay->of(false);
							$targetDay->save();
						}

						$rowToDayPage[(int) $dayRow['row']] = $targetDay;
					}
					$entityPage->save('tour_days');

					$submittedDayIds = [];
					foreach($tourDaysToPersist as $dayRow) {
						$rowIndex = (int) ($dayRow['row'] ?? 0);
						$targetDay = $rowToDayPage[$rowIndex] ?? null;
						if(!$targetDay instanceof Page || !$targetDay->id) continue;

						$targetDay->of(false);
						if($targetDay->hasField('tour_day_title')) $targetDay->tour_day_title = trim((string) ($dayRow['title'] ?? ''));
						if($targetDay->hasField('tour_day_description')) $targetDay->tour_day_description = trim((string) ($dayRow['description'] ?? ''));
						$targetDay->save();
						$submittedDayIds[(int) $targetDay->id] = true;

						$uploads = isset($dayRow['uploads']) && is_array($dayRow['uploads']) ? $dayRow['uploads'] : [];
						if(count($uploads) && $targetDay->hasField('tour_day_images')) {
							$images = $targetDay->getUnformatted('tour_day_images');
							if($images instanceof Pageimages) {
								foreach($uploads as $tmpUploadPath) {
									$images->add((string) $tmpUploadPath);
								}
								$targetDay->save('tour_day_images');
							}
						}
					}

					$entityPage = $pages->get((int) $entityPage->id);
					$entityPage->of(false);
					$currentTourDays = $entityPage->getUnformatted('tour_days');
					if($currentTourDays instanceof PageArray) {
						foreach($currentTourDays as $dayPage) {
							if(!isset($submittedDayIds[(int) $dayPage->id])) {
								$entityPage->tour_days->remove($dayPage);
							}
						}
					}
					$entityPage->save('tour_days');
				}
			}
		}

		$successMessage = 'Сохранено.';
		if($entityType === 'tours') {
			$successMessage = 'Сохранено. Пунктов «Что включено»: ' . count($tourIncludedItemsToPersist) . '.';
		}
		$setFlash($session, $flashPrefix . 'success', $successMessage);
		$session->redirect($contentAdminBaseUrl . '?tab=' . rawurlencode($entityType) . '&id=' . (int) $entityPage->id);
	}

		if($action === 'delete_entity') {
		$entityType = trim((string) $input->post('entity_type'));
		$entityId = (int) $input->post('entity_id');
			if(!isset($catalogConfigs[$entityType]) || $entityId < 1) {
				$setFlash($session, $flashPrefix . 'error', 'Неверный запрос удаления.');
				$session->redirect($contentAdminBaseUrl . '?tab=' . rawurlencode($activeTab));
			}

		$config = $catalogConfigs[$entityType];
		$entityPage = $pages->get($entityId);
		$isDeletable = $entityPage && $entityPage->id && $entityPage->template && $entityPage->template->name === $config['template'];
			if(!$isDeletable) {
				$setFlash($session, $flashPrefix . 'error', 'Запись не найдена для удаления.');
				$session->redirect($contentAdminBaseUrl . '?tab=' . rawurlencode($entityType));
			}

		$pages->delete($entityPage, true);
		$setFlash($session, $flashPrefix . 'success', 'Запись удалена.');
		$session->redirect($contentAdminBaseUrl . '?tab=' . rawurlencode($entityType));
	}

		if($action === 'save_placements') {
		if($homePage && $homePage->id) {
			$homePage->of(false);
			if($homePage->hasField('home_featured_tours')) {
				$ids = $normalizeEntityIds($toIntList($_POST['home_featured_tours'] ?? []), 'tour');
				$homePage->setAndSave('home_featured_tours', $ids);
			}
			if($homePage->hasField('home_featured_places')) {
				$ids = $normalizeEntityIds($toIntList($_POST['home_featured_places'] ?? []), 'place');
				$homePage->setAndSave('home_featured_places', $ids);
			}
			if($homePage->hasField('home_actual_places')) {
				$ids = $normalizeEntityIds($toIntList($_POST['home_actual_places'] ?? []), 'place');
				$homePage->setAndSave('home_actual_places', $ids);
			}
			if($homePage->hasField('home_featured_articles')) {
				$ids = $normalizeEntityIds($toIntList($_POST['home_featured_articles'] ?? []), 'article');
				$homePage->setAndSave('home_featured_articles', $ids);
			}
		}

			if($articlesPage && $articlesPage->id) {
				$articlesPage->of(false);
			if($articlesPage->hasField('articles_today_refs')) {
				$ids = $normalizeEntityIds($toIntList($_POST['articles_today_refs'] ?? []), 'article');
				$articlesPage->setAndSave('articles_today_refs', $ids);
			}
			if($articlesPage->hasField('articles_first_time_refs')) {
				$ids = $normalizeEntityIds($toIntList($_POST['articles_first_time_refs'] ?? []), 'article');
				$articlesPage->setAndSave('articles_first_time_refs', $ids);
				}
			}

			if($hotelsPage && $hotelsPage->id && $hotelsPage->hasField('hotels_featured_refs')) {
				$hotelsPage->of(false);
				$ids = $normalizeEntityIds($toIntList($_POST['hotels_featured_refs'] ?? []), 'hotel');
				$hotelsPage->setAndSave('hotels_featured_refs', $ids);
			}

		$regionToursMap = isset($_POST['region_featured_tours']) && is_array($_POST['region_featured_tours']) ? $_POST['region_featured_tours'] : [];
		$regionPlacesMap = isset($_POST['region_featured_places']) && is_array($_POST['region_featured_places']) ? $_POST['region_featured_places'] : [];
		$regionArticlesMap = isset($_POST['region_featured_articles']) && is_array($_POST['region_featured_articles']) ? $_POST['region_featured_articles'] : [];

		foreach($regionPages as $regionPage) {
			$regionId = (int) $regionPage->id;
			$regionPage->of(false);

			if($regionPage->hasField('region_featured_tours')) {
				$ids = $normalizeEntityIds($toIntList($regionToursMap[$regionId] ?? []), 'tour');
				$regionPage->setAndSave('region_featured_tours', $ids);
			}
			if($regionPage->hasField('region_featured_places')) {
				$ids = $normalizeEntityIds($toIntList($regionPlacesMap[$regionId] ?? []), 'place');
				$regionPage->setAndSave('region_featured_places', $ids);
			}
			if($regionPage->hasField('region_featured_articles')) {
				$ids = $normalizeEntityIds($toIntList($regionArticlesMap[$regionId] ?? []), 'article');
				$regionPage->setAndSave('region_featured_articles', $ids);
			}
		}

		$setFlash($session, $flashPrefix . 'success', 'Привязки обновлены.');
		$session->redirect($contentAdminBaseUrl . '?tab=placements');
	}
}

$templateCssPath = $config->paths->templates . 'styles/content-admin.css';
$templateCssVersion = is_file($templateCssPath) ? (int) filemtime($templateCssPath) : time();
$renderPlacementChecklist = static function(string $fieldName, PageArray $items, $selectedItems, string $emptyMessage) use ($sanitizer): void {
	$selectedIds = [];
	if($selectedItems instanceof PageArray) {
		foreach($selectedItems as $selectedItem) {
			if($selectedItem instanceof Page && $selectedItem->id) {
				$selectedIds[(int) $selectedItem->id] = true;
			}
		}
	}

	if(!$items->count()) {
		echo '<div class="placement-empty">' . $sanitizer->entities($emptyMessage) . '</div>';
		return;
	}

	echo '<div class="placement-picker">';
	foreach($items as $item) {
		if(!$item instanceof Page || !$item->id) continue;

		$itemId = (int) $item->id;
		$itemTitle = trim((string) $item->title);
		if($itemTitle === '') $itemTitle = 'Без названия';
		$metaParts = [];

		if($item->template && $item->template->name === 'article' && $item->hasField('article_topic')) {
			$topic = trim((string) $item->article_topic);
			if($topic !== '') $metaParts[] = $topic;
		}
		if($item->template && $item->template->name === 'place' && $item->hasField('place_region')) {
			$placeRegion = trim((string) $item->place_region);
			if($placeRegion !== '') $metaParts[] = $placeRegion;
		}
		if($item->template && $item->template->name === 'hotel') {
			if($item->hasField('hotel_city')) {
				$hotelCity = trim((string) $item->hotel_city);
				if($hotelCity !== '') $metaParts[] = $hotelCity;
			}
			if($item->hasField('hotel_region')) {
				$hotelRegion = trim((string) $item->hotel_region);
				if($hotelRegion !== '') $metaParts[] = $hotelRegion;
			}
		}
		if($item->template && $item->template->name === 'tour' && $item->hasField('tour_region')) {
			$tourRegion = trim((string) $item->tour_region);
			if($tourRegion !== '') $metaParts[] = $tourRegion;
		}

		$metaText = implode(' • ', array_values(array_unique($metaParts)));
		$isChecked = isset($selectedIds[$itemId]) ? ' checked' : '';

		echo '<label class="placement-choice">';
		echo '<input type="checkbox" name="' . htmlspecialchars($fieldName, ENT_QUOTES, 'UTF-8') . '" value="' . $itemId . '"' . $isChecked . ' />';
		echo '<span class="placement-choice-copy">';
		echo '<span class="placement-choice-title">' . $sanitizer->entities($itemTitle) . '</span>';
		if($metaText !== '') {
			echo '<span class="placement-choice-meta">' . $sanitizer->entities($metaText) . '</span>';
		}
		echo '</span>';
		echo '</label>';
	}
	echo '</div>';
};

?>

<div id="content" class="content-admin-page">
	<link rel="stylesheet" href="<?php echo $config->urls->templates; ?>styles/content-admin.css?v=<?php echo $templateCssVersion; ?>" />
	<div class="content-admin-bg"></div>
		<section class="content-admin-hero">
			<div class="content-admin-container">
				<div class="content-admin-headline">
					<h1>Контент-центр</h1>
					<p>Создавайте карточки туров, статей, мест и отелей один раз и размещайте их в нужных блоках сайта.</p>
				</div>
			</div>
		</section>

	<section class="content-admin-body">
		<div class="content-admin-container">
			<?php if(!$contentRoot || !$contentRoot->id): ?>
				<div class="content-admin-alert is-error">Раздел `/content/` пока не создан. Откройте `/processwire/` и перезагрузите страницу.</div>
			<?php endif; ?>

			<?php if($noticeSuccess !== ''): ?>
				<div class="content-admin-alert is-success"><?php echo $sanitizer->entities($noticeSuccess); ?></div>
			<?php endif; ?>
			<?php if($noticeError !== ''): ?>
				<div class="content-admin-alert is-error"><?php echo $sanitizer->entities($noticeError); ?></div>
			<?php endif; ?>

				<nav class="content-admin-tabs" aria-label="Разделы контента">
					<?php foreach($tabs as $tabKey => $tabTitle): ?>
						<a class="content-admin-tab<?php echo $activeTab === $tabKey ? ' is-active' : ''; ?>" href="<?php echo $sanitizer->entities($contentAdminBaseUrl . '?tab=' . rawurlencode($tabKey)); ?>">
							<?php echo $sanitizer->entities($tabTitle); ?>
						</a>
					<?php endforeach; ?>
				</nav>

				<?php if($activeTab === 'placements'): ?>
					<div class="content-admin-alert is-success">
						Раздел «Где показывать» управляет тем, какие карточки выводятся на Главной, в Регионах и в Статьях, а также какие отели показывать первыми на странице Отелей.
					</div>
				<?php endif; ?>

			<?php if($activeTab !== 'placements' && isset($catalogConfigs[$activeTab])): ?>
				<?php
					$currentConfig = $catalogConfigs[$activeTab];
					$listItems = $catalogItems[$activeTab] ?? new PageArray();
					$currentImageUrl = '';
					$tourSelectedIncludeCodes = [];
					$tourIncludedCustomText = '';
					$hotelSelectedAmenityCodes = ['wifi', 'parking', 'breakfast'];
					$hotelAmenitiesCustomText = '';
					$tourSelectedSeasonMonths = [];
					$tourSelectedDifficulty = 'basic';
					$tourSelectedAge = '12+';
					$tourDurationDaysValue = '';
					$tourGroupSizeValue = '';
					$tourPricePerPersonValue = '';
					$tourDayRows = [];
					if($currentEntity && $currentEntity->id) {
						$currentImageUrl = $getImageUrlFromValue($currentEntity->getUnformatted((string) $currentConfig['image_field']));

						if($activeTab === 'tours') {
								$currentIncluded = $collectTourIncludedFromPage($currentEntity);
								$customIncluded = [];
								foreach($currentIncluded as $line) {
									$key = $toIncludedItemKey($line);
									if(isset($tourIncludedCodeByLabel[$key])) {
										$tourSelectedIncludeCodes[] = $tourIncludedCodeByLabel[$key];
									} else {
									$customIncluded[] = $line;
								}
							}
							$tourSelectedIncludeCodes = array_values(array_unique($tourSelectedIncludeCodes));
							$tourIncludedCustomText = implode("\n", $customIncluded);

							if($currentEntity->hasField('tour_season')) {
								$tourSelectedSeasonMonths = $extractSeasonMonthSelection((string) $currentEntity->getUnformatted('tour_season'));
							}

							$currentDifficulty = '';
							if($currentEntity->hasField('tour_difficulty_level')) {
								$difficultyValue = $currentEntity->getUnformatted('tour_difficulty_level');
								if($difficultyValue instanceof SelectableOptionArray && $difficultyValue->count()) {
									$currentDifficulty = $toLower((string) $difficultyValue->first()->title);
								} elseif($difficultyValue instanceof SelectableOption) {
									$currentDifficulty = $toLower((string) $difficultyValue->title);
								}
							}
							if($currentDifficulty === '' && $currentEntity->hasField('tour_difficulty')) {
								$currentDifficulty = $toLower((string) $currentEntity->getUnformatted('tour_difficulty'));
							}
							if($currentDifficulty === 'средняя') $tourSelectedDifficulty = 'medium';
							if($currentDifficulty === 'экстремальная' || $currentDifficulty === 'высокая') $tourSelectedDifficulty = 'extreme';

							if($currentEntity->hasField('tour_age')) {
								$currentAge = trim((string) $currentEntity->getUnformatted('tour_age'));
								if(in_array($currentAge, $tourAgeOptions, true)) $tourSelectedAge = $currentAge;
							}

							if($currentEntity->hasField('tour_duration')) {
								$durationDigits = preg_replace('/[^\d]+/', '', (string) $currentEntity->getUnformatted('tour_duration')) ?? '';
								$tourDurationDaysValue = $durationDigits;
							}
							if($currentEntity->hasField('tour_group')) {
								$groupDigits = preg_replace('/[^\d]+/', '', (string) $currentEntity->getUnformatted('tour_group')) ?? '';
								$tourGroupSizeValue = $groupDigits;
							}
							if($currentEntity->hasField('tour_price')) {
								$priceDigits = preg_replace('/[^\d]+/', '', (string) $currentEntity->getUnformatted('tour_price')) ?? '';
								$tourPricePerPersonValue = $priceDigits;
							}

							if($currentEntity->hasField('tour_days')) {
								$tourDaysRepeater = $currentEntity->getUnformatted('tour_days');
								if($tourDaysRepeater instanceof PageArray && $tourDaysRepeater->count()) {
									foreach($tourDaysRepeater as $dayPage) {
										if(!$dayPage instanceof Page) continue;
										$imageCount = 0;
										if($dayPage->hasField('tour_day_images')) {
											$dayImages = $dayPage->getUnformatted('tour_day_images');
											if($dayImages instanceof Pageimages) $imageCount = (int) $dayImages->count();
										}
										$tourDayRows[] = [
											'id' => (int) $dayPage->id,
											'title' => $dayPage->hasField('tour_day_title') ? (string) $dayPage->getUnformatted('tour_day_title') : '',
											'description' => $dayPage->hasField('tour_day_description') ? (string) $dayPage->getUnformatted('tour_day_description') : '',
											'images_count' => $imageCount,
										];
									}
								}
							}
						}

						if($activeTab === 'hotels') {
							$currentHotelAmenities = $normalizeLines((string) ($currentEntity->hasField('hotel_amenities') ? $currentEntity->getUnformatted('hotel_amenities') : ''));
							$customHotelAmenities = [];
							$hotelSelectedAmenityCodes = [];

							foreach($currentHotelAmenities as $line) {
								$line = trim((string) $line);
								if($line === '') continue;

								if(isset($hotelAmenitiesLibrary[$line])) {
									$hotelSelectedAmenityCodes[] = $line;
									continue;
								}

								$lineKey = $toIncludedItemKey($line);
								if(isset($hotelAmenityCodeByLabel[$lineKey])) {
									$hotelSelectedAmenityCodes[] = $hotelAmenityCodeByLabel[$lineKey];
									continue;
								}

								$customHotelAmenities[] = $line;
							}

							$hotelSelectedAmenityCodes = array_values(array_unique($hotelSelectedAmenityCodes));
							$hotelAmenitiesCustomText = implode("\n", $customHotelAmenities);
						}
					}
					if($activeTab === 'tours' && !count($tourDayRows)) {
						$tourDayRows[] = ['id' => 0, 'title' => '', 'description' => '', 'images_count' => 0];
					}
					?>
				<div class="content-admin-grid">
					<div class="content-admin-panel">
						<div class="content-admin-panel-head">
							<h2><?php echo $sanitizer->entities((string) $currentConfig['title']); ?></h2>
							<span><?php echo (int) $listItems->count(); ?> записей</span>
						</div>
						<div class="content-admin-list">
							<?php foreach($listItems as $item): ?>
								<article class="content-item-card<?php echo $currentEntity && $currentEntity->id === $item->id ? ' is-active' : ''; ?>">
									<div class="content-item-main">
										<div class="content-item-title"><?php echo $sanitizer->entities(trim((string) $item->title) !== '' ? (string) $item->title : (string) $item->name); ?></div>
										<div class="content-item-meta"><?php echo $sanitizer->entities((string) $item->name); ?></div>
									</div>
										<div class="content-item-actions">
										<a class="content-item-btn" href="<?php echo $sanitizer->entities($contentAdminBaseUrl . '?tab=' . rawurlencode($activeTab) . '&id=' . (int) $item->id); ?>">Открыть</a>
										<form method="post" onsubmit="return confirm('Удалить запись?');">
											<input type="hidden" name="action" value="delete_entity" />
											<input type="hidden" name="entity_type" value="<?php echo $sanitizer->entities($activeTab); ?>" />
											<input type="hidden" name="entity_id" value="<?php echo (int) $item->id; ?>" />
											<input type="hidden" name="<?php echo $sanitizer->entities($session->CSRF->getTokenName()); ?>" value="<?php echo $sanitizer->entities($session->CSRF->getTokenValue()); ?>" />
											<button class="content-item-btn is-danger" type="submit">Удалить</button>
										</form>
									</div>
								</article>
							<?php endforeach; ?>
							<?php if(!$listItems->count()): ?>
								<div class="content-admin-empty">Пока нет записей в этом каталоге.</div>
							<?php endif; ?>
						</div>
					</div>

					<div class="content-admin-panel">
						<div class="content-admin-panel-head">
							<h2><?php echo $currentEntity && $currentEntity->id ? 'Редактирование' : 'Новая запись'; ?></h2>
							<span><?php echo $sanitizer->entities((string) $currentConfig['singular']); ?></span>
						</div>

						<form class="content-admin-form" method="post" enctype="multipart/form-data">
							<input type="hidden" name="action" value="save_entity" />
							<input type="hidden" name="entity_type" value="<?php echo $sanitizer->entities($activeTab); ?>" />
							<input type="hidden" name="entity_id" value="<?php echo $currentEntity && $currentEntity->id ? (int) $currentEntity->id : 0; ?>" />
							<input type="hidden" name="<?php echo $sanitizer->entities($session->CSRF->getTokenName()); ?>" value="<?php echo $sanitizer->entities($session->CSRF->getTokenValue()); ?>" />

								<label class="content-admin-field">
									<span>Название</span>
									<input type="text" name="title" value="<?php echo $sanitizer->entities($currentEntity ? (string) $currentEntity->title : ''); ?>" required />
								</label>

								<?php if($activeTab === 'tours'): ?>
									<label class="content-admin-field">
										<span>Описание</span>
										<textarea name="tour_description" rows="6"><?php echo $sanitizer->entities($currentEntity && $currentEntity->hasField('tour_description') ? (string) $currentEntity->getUnformatted('tour_description') : ''); ?></textarea>
									</label>

									<label class="content-admin-field">
										<span>Регион</span>
										<select name="tour_region">
											<option value="">Выберите регион</option>
											<?php
											$currentTourRegion = $currentEntity && $currentEntity->hasField('tour_region') ? trim((string) $currentEntity->getUnformatted('tour_region')) : '';
											foreach($tourRegionOptions as $regionOption):
											?>
												<option value="<?php echo $sanitizer->entities($regionOption); ?>"<?php echo $currentTourRegion === $regionOption ? ' selected' : ''; ?>>
													<?php echo $sanitizer->entities($regionOption); ?>
												</option>
											<?php endforeach; ?>
										</select>
									</label>

									<div class="content-admin-subsection">
										<h3>Что включено</h3>
										<div class="content-admin-check-grid">
											<?php foreach($tourIncludedLibrary as $includeCode => $includeLabel): ?>
												<label class="content-admin-check-card">
													<input type="checkbox" name="tour_included_selected[]" value="<?php echo $sanitizer->entities($includeCode); ?>"<?php echo in_array($includeCode, $tourSelectedIncludeCodes, true) ? ' checked' : ''; ?> />
													<span><?php echo $sanitizer->entities($includeLabel); ?></span>
												</label>
											<?php endforeach; ?>
										</div>
										<label class="content-admin-field">
											<span>Дополнительные пункты (по строкам)</span>
											<textarea name="tour_included_custom" rows="4"><?php echo $sanitizer->entities($tourIncludedCustomText); ?></textarea>
										</label>
									</div>

									<div class="content-admin-subsection">
										<h3>Детали тура</h3>
										<div class="content-admin-cols">
											<label class="content-admin-field">
												<span>Длительность (дней)</span>
												<input type="number" min="1" max="365" name="tour_duration_days" value="<?php echo $sanitizer->entities($tourDurationDaysValue); ?>" />
											</label>
											<label class="content-admin-field">
												<span>Группа (человек)</span>
												<input type="number" min="1" max="99" name="tour_group_size" value="<?php echo $sanitizer->entities($tourGroupSizeValue); ?>" />
											</label>
										</div>
										<div class="content-admin-cols">
											<label class="content-admin-field">
												<span>Сложность</span>
												<select name="tour_difficulty_level">
													<?php foreach($tourDifficultyUiMap as $difficultyKey => $difficultyLabel): ?>
														<option value="<?php echo $sanitizer->entities($difficultyKey); ?>"<?php echo $tourSelectedDifficulty === $difficultyKey ? ' selected' : ''; ?>>
															<?php echo $sanitizer->entities($difficultyLabel); ?>
														</option>
													<?php endforeach; ?>
												</select>
											</label>
											<label class="content-admin-field">
												<span>Возрастное ограничение</span>
												<select name="tour_age">
													<?php foreach($tourAgeOptions as $ageOption): ?>
														<option value="<?php echo $sanitizer->entities($ageOption); ?>"<?php echo $tourSelectedAge === $ageOption ? ' selected' : ''; ?>>
															<?php echo $sanitizer->entities($ageOption); ?>
														</option>
													<?php endforeach; ?>
												</select>
											</label>
										</div>
										<label class="content-admin-field">
											<span>Цена за человека (₽)</span>
											<input type="number" min="0" step="100" name="tour_price_per_person" value="<?php echo $sanitizer->entities($tourPricePerPersonValue); ?>" />
										</label>
										<div class="content-admin-field">
											<span>Сезонность (по месяцам)</span>
											<div class="content-admin-month-grid">
												<?php foreach($tourSeasonMonthMap as $monthNumber => $monthLabel): ?>
													<label class="content-admin-month-item">
														<input type="checkbox" name="tour_season_months[]" value="<?php echo (int) $monthNumber; ?>"<?php echo in_array((int) $monthNumber, $tourSelectedSeasonMonths, true) ? ' checked' : ''; ?> />
														<span><?php echo $sanitizer->entities($monthLabel); ?></span>
													</label>
												<?php endforeach; ?>
											</div>
										</div>
									</div>

									<div class="content-admin-subsection">
										<div class="content-admin-subhead">
											<h3>По дням</h3>
											<button class="content-admin-btn" type="button" data-tour-day-add>Добавить день</button>
										</div>
										<div class="tour-day-editor" data-tour-day-list>
											<?php foreach($tourDayRows as $dayIndex => $tourDayRow): ?>
												<div class="tour-day-row" data-tour-day-row>
													<div class="tour-day-row-head">
														<strong class="tour-day-row-label">День <?php echo (int) $dayIndex + 1; ?></strong>
														<button class="content-item-btn is-danger" type="button" data-tour-day-remove>Удалить</button>
													</div>
													<input type="hidden" name="tour_days[<?php echo (int) $dayIndex; ?>][id]" value="<?php echo (int) ($tourDayRow['id'] ?? 0); ?>" data-tour-day-field="id" />
													<label class="content-admin-field">
														<span>Заголовок</span>
														<input type="text" name="tour_days[<?php echo (int) $dayIndex; ?>][title]" value="<?php echo $sanitizer->entities((string) ($tourDayRow['title'] ?? '')); ?>" data-tour-day-field="title" />
													</label>
													<label class="content-admin-field">
														<span>Описание</span>
														<textarea name="tour_days[<?php echo (int) $dayIndex; ?>][description]" rows="4" data-tour-day-field="description"><?php echo $sanitizer->entities((string) ($tourDayRow['description'] ?? '')); ?></textarea>
													</label>
													<label class="content-admin-field">
														<span>Изображения дня</span>
														<input type="file" name="tour_day_images[<?php echo (int) $dayIndex; ?>][]" multiple accept=".jpg,.jpeg,.png,.gif,.webp" data-tour-day-field="images" />
													</label>
													<?php if((int) ($tourDayRow['images_count'] ?? 0) > 0): ?>
														<div class="tour-day-row-note">Сейчас загружено: <?php echo (int) $tourDayRow['images_count']; ?></div>
													<?php endif; ?>
												</div>
											<?php endforeach; ?>
										</div>
									</div>
								<?php endif; ?>

								<?php if($activeTab === 'articles'): ?>
									<div class="content-admin-cols">
										<label class="content-admin-field"><span>Тематика</span><input type="text" name="article_topic" value="<?php echo $sanitizer->entities($currentEntity ? (string) $currentEntity->get('article_topic') : ''); ?>" /></label>
									<label class="content-admin-field">
										<span>Дата публикации</span>
										<?php
										$publishDateValue = '';
										if($currentEntity && $currentEntity->hasField('article_publish_date')) {
											$publishTimestamp = (int) $currentEntity->getUnformatted('article_publish_date');
											if($publishTimestamp > 0) $publishDateValue = date('Y-m-d', $publishTimestamp);
										}
										?>
										<input type="date" name="article_publish_date" value="<?php echo $sanitizer->entities($publishDateValue); ?>" />
									</label>
								</div>
								<label class="content-admin-field"><span>Краткое описание</span><textarea name="article_excerpt" rows="4"><?php echo $sanitizer->entities($currentEntity ? (string) $currentEntity->get('article_excerpt') : ''); ?></textarea></label>
								<label class="content-admin-field"><span>Текст статьи</span><textarea name="article_content" rows="8"><?php echo $sanitizer->entities($currentEntity ? (string) $currentEntity->get('article_content') : ''); ?></textarea></label>
							<?php endif; ?>

							<?php if($activeTab === 'places'): ?>
								<label class="content-admin-field"><span>Регион</span><input type="text" name="place_region" value="<?php echo $sanitizer->entities($currentEntity ? (string) $currentEntity->get('place_region') : ''); ?>" /></label>
								<label class="content-admin-field"><span>Описание места</span><textarea name="place_summary" rows="6"><?php echo $sanitizer->entities($currentEntity ? (string) $currentEntity->get('place_summary') : ''); ?></textarea></label>
							<?php endif; ?>

							<?php if($activeTab === 'hotels'): ?>
								<div class="content-admin-cols">
									<label class="content-admin-field"><span>Город</span><input type="text" name="hotel_city" value="<?php echo $sanitizer->entities($currentEntity ? (string) $currentEntity->get('hotel_city') : ''); ?>" /></label>
									<label class="content-admin-field"><span>Регион</span><input type="text" name="hotel_region" value="<?php echo $sanitizer->entities($currentEntity ? (string) $currentEntity->get('hotel_region') : ''); ?>" /></label>
								</div>
								<div class="content-admin-cols">
									<label class="content-admin-field"><span>Рейтинг</span><input type="number" step="0.1" min="0" max="5" name="hotel_rating" value="<?php echo $sanitizer->entities($currentEntity ? (string) $currentEntity->get('hotel_rating') : ''); ?>" /></label>
									<label class="content-admin-field"><span>Цена (₽)</span><input type="number" min="0" name="hotel_price" value="<?php echo $sanitizer->entities($currentEntity ? (string) $currentEntity->get('hotel_price') : ''); ?>" /></label>
								</div>
								<label class="content-admin-field"><span>Макс. гостей</span><input type="number" min="1" max="50" name="hotel_max_guests" value="<?php echo $sanitizer->entities($currentEntity ? (string) $currentEntity->get('hotel_max_guests') : '2'); ?>" /></label>
								<div class="content-admin-subsection">
									<h3>Сервис и удобства</h3>
									<div class="content-admin-check-grid">
										<?php foreach($hotelAmenitiesLibrary as $amenityCode => $amenityLabel): ?>
											<label class="content-admin-check-card">
												<input type="checkbox" name="hotel_amenities_selected[]" value="<?php echo $sanitizer->entities($amenityCode); ?>"<?php echo in_array($amenityCode, $hotelSelectedAmenityCodes, true) ? ' checked' : ''; ?> />
												<span><?php echo $sanitizer->entities($amenityLabel); ?></span>
											</label>
										<?php endforeach; ?>
									</div>
									<label class="content-admin-field">
										<span>Дополнительные пункты (по строкам)</span>
										<textarea name="hotel_amenities_custom" rows="4"><?php echo $sanitizer->entities($hotelAmenitiesCustomText); ?></textarea>
									</label>
								</div>
								<?php endif; ?>

								<label class="content-admin-field">
									<span><?php echo $activeTab === 'tours' ? 'Главное изображение' : 'Изображение'; ?></span>
									<input type="file" name="image_upload" accept=".jpg,.jpeg,.png,.gif,.webp" />
								</label>
							<?php if($currentImageUrl !== ''): ?>
								<div class="content-admin-image-preview" style="background-image:url('<?php echo htmlspecialchars($currentImageUrl, ENT_QUOTES, 'UTF-8'); ?>');"></div>
								<label class="content-admin-checkbox">
									<input type="checkbox" name="clear_image" value="1" />
									<span>Удалить текущее изображение</span>
								</label>
							<?php endif; ?>

							<div class="content-admin-form-actions">
								<button class="content-admin-btn is-primary" type="submit">Сохранить</button>
								<a class="content-admin-btn" href="<?php echo $sanitizer->entities($contentAdminBaseUrl . '?tab=' . rawurlencode($activeTab)); ?>">Новая запись</a>
							</div>
						</form>
						<?php if($activeTab === 'tours'): ?>
							<template id="tour-day-row-template">
								<div class="tour-day-row" data-tour-day-row>
									<div class="tour-day-row-head">
										<strong class="tour-day-row-label">День 1</strong>
										<button class="content-item-btn is-danger" type="button" data-tour-day-remove>Удалить</button>
									</div>
									<input type="hidden" name="tour_days[__INDEX__][id]" value="0" data-tour-day-field="id" />
									<label class="content-admin-field">
										<span>Заголовок</span>
										<input type="text" name="tour_days[__INDEX__][title]" value="" data-tour-day-field="title" />
									</label>
									<label class="content-admin-field">
										<span>Описание</span>
										<textarea name="tour_days[__INDEX__][description]" rows="4" data-tour-day-field="description"></textarea>
									</label>
									<label class="content-admin-field">
										<span>Изображения дня</span>
										<input type="file" name="tour_day_images[__INDEX__][]" multiple accept=".jpg,.jpeg,.png,.gif,.webp" data-tour-day-field="images" />
									</label>
								</div>
							</template>
						<?php endif; ?>
					</div>
				</div>
			<?php endif; ?>

			<?php if($activeTab === 'tours'): ?>
				<script>
					(function() {
						var dayList = document.querySelector('[data-tour-day-list]');
						var addButton = document.querySelector('[data-tour-day-add]');
						var rowTemplate = document.getElementById('tour-day-row-template');
						if(!dayList || !addButton || !rowTemplate) return;

						var buildName = function(index, field) {
							if(field === 'images') return 'tour_day_images[' + index + '][]';
							return 'tour_days[' + index + '][' + field + ']';
						};

						var syncRows = function() {
							var rows = Array.prototype.slice.call(dayList.querySelectorAll('[data-tour-day-row]'));
							rows.forEach(function(row, index) {
								var label = row.querySelector('.tour-day-row-label');
								if(label) label.textContent = 'День ' + (index + 1);

								var fields = Array.prototype.slice.call(row.querySelectorAll('[data-tour-day-field]'));
								fields.forEach(function(field) {
									var fieldType = field.getAttribute('data-tour-day-field') || '';
									field.name = buildName(index, fieldType);
								});
							});
						};

						addButton.addEventListener('click', function() {
							var fragment = rowTemplate.content.cloneNode(true);
							dayList.appendChild(fragment);
							syncRows();
						});

						dayList.addEventListener('click', function(event) {
							var button = event.target && event.target.closest ? event.target.closest('[data-tour-day-remove]') : null;
							if(!button) return;

							var rows = Array.prototype.slice.call(dayList.querySelectorAll('[data-tour-day-row]'));
							if(rows.length === 1) {
								var onlyRow = rows[0];
								var fields = Array.prototype.slice.call(onlyRow.querySelectorAll('[data-tour-day-field]'));
								fields.forEach(function(field) {
									var fieldType = field.getAttribute('data-tour-day-field') || '';
									if(fieldType === 'id') field.value = '0';
									if(field.tagName === 'INPUT' && field.type !== 'file' && fieldType !== 'id') field.value = '';
									if(field.tagName === 'TEXTAREA') field.value = '';
								});
								var note = onlyRow.querySelector('.tour-day-row-note');
								if(note) note.remove();
								return;
							}

							var row = button.closest('[data-tour-day-row]');
							if(row) row.remove();
							syncRows();
						});

						syncRows();
					})();
				</script>
			<?php endif; ?>

			<?php if($activeTab === 'placements'): ?>
				<form class="content-admin-panel" method="post">
					<input type="hidden" name="action" value="save_placements" />
					<input type="hidden" name="<?php echo $sanitizer->entities($session->CSRF->getTokenName()); ?>" value="<?php echo $sanitizer->entities($session->CSRF->getTokenValue()); ?>" />

					<div class="content-admin-panel-head">
						<h2>Показывать на страницах</h2>
						<span>Привязка карточек к блокам сайта</span>
					</div>

					<?php
					$homeSelectedTours = $homePage && $homePage->hasField('home_featured_tours') ? $homePage->home_featured_tours : new PageArray();
					$homeSelectedPlaces = $homePage && $homePage->hasField('home_featured_places') ? $homePage->home_featured_places : new PageArray();
					$homeSelectedActual = $homePage && $homePage->hasField('home_actual_places') ? $homePage->home_actual_places : new PageArray();
					$homeSelectedArticles = $homePage && $homePage->hasField('home_featured_articles') ? $homePage->home_featured_articles : new PageArray();
					$todaySelected = $articlesPage && $articlesPage->hasField('articles_today_refs') ? $articlesPage->articles_today_refs : new PageArray();
					$firstTimeSelected = $articlesPage && $articlesPage->hasField('articles_first_time_refs') ? $articlesPage->articles_first_time_refs : new PageArray();
					$hotelsSelected = $hotelsPage && $hotelsPage->hasField('hotels_featured_refs') ? $hotelsPage->hotels_featured_refs : new PageArray();
					?>
					<div class="placement-builder" data-placement-builder>
						<section class="placement-step-card">
							<p class="placement-step-index">Шаг 1</p>
							<h3>Выберите страницу</h3>
							<div class="placement-page-nav" role="tablist" aria-label="Страницы для размещения">
								<button class="placement-nav-btn" type="button" data-placement-page-button="home">Главная страница</button>
								<button class="placement-nav-btn" type="button" data-placement-page-button="articles">Страница статей</button>
								<button class="placement-nav-btn" type="button" data-placement-page-button="hotels">Страница отелей</button>
								<button class="placement-nav-btn" type="button" data-placement-page-button="regions">Региональные страницы</button>
							</div>
						</section>

						<section class="placement-page" data-placement-page="home">
							<div class="placement-step-card">
								<p class="placement-step-index">Шаг 2</p>
								<h3>Блок на главной странице</h3>
								<div class="placement-block-nav" role="tablist" aria-label="Блоки главной страницы">
									<button class="placement-nav-btn" type="button" data-placement-block-button="home-tours">Чем заняться этим летом</button>
									<button class="placement-nav-btn" type="button" data-placement-block-button="home-dagestan">Что насчет Дагестана?</button>
									<button class="placement-nav-btn" type="button" data-placement-block-button="home-actual">Актуальные места</button>
									<button class="placement-nav-btn" type="button" data-placement-block-button="home-journal">Статьи СКФО</button>
								</div>
							</div>

							<article class="placement-block" data-placement-block="home-tours">
								<header class="placement-block-head">
									<p class="placement-step-index">Шаг 3</p>
									<h4>Заполните блок «Чем заняться этим летом?»</h4>
									<p>Показывается на странице <code>/</code> в секции туров.</p>
								</header>
								<?php $renderPlacementChecklist('home_featured_tours[]', $catalogItems['tours'], $homeSelectedTours, 'Туров пока нет. Добавьте их в раздел «Туры».'); ?>
							</article>

							<article class="placement-block" data-placement-block="home-dagestan">
								<header class="placement-block-head">
									<p class="placement-step-index">Шаг 3</p>
									<h4>Заполните блок «Что насчет Дагестана?»</h4>
									<p>Показывается на странице <code>/</code> в секции мест.</p>
								</header>
								<?php $renderPlacementChecklist('home_featured_places[]', $catalogItems['places'], $homeSelectedPlaces, 'Мест пока нет. Добавьте их в раздел «Места».'); ?>
							</article>

							<article class="placement-block" data-placement-block="home-actual">
								<header class="placement-block-head">
									<p class="placement-step-index">Шаг 3</p>
									<h4>Заполните блок «Актуальные места»</h4>
									<p>Показывается на странице <code>/</code> в блоке актуальных карточек.</p>
								</header>
								<?php $renderPlacementChecklist('home_actual_places[]', $catalogItems['places'], $homeSelectedActual, 'Мест пока нет. Добавьте их в раздел «Места».'); ?>
							</article>

							<article class="placement-block" data-placement-block="home-journal">
								<header class="placement-block-head">
									<p class="placement-step-index">Шаг 3</p>
									<h4>Заполните блок «Статьи СКФО»</h4>
									<p>Показывается на странице <code>/</code>. На сайте выводится первая выбранная статья.</p>
								</header>
								<?php $renderPlacementChecklist('home_featured_articles[]', $catalogItems['articles'], $homeSelectedArticles, 'Статей пока нет. Добавьте их в раздел «Статьи».'); ?>
							</article>
						</section>

						<section class="placement-page" data-placement-page="articles">
							<div class="placement-step-card">
								<p class="placement-step-index">Шаг 2</p>
								<h3>Блок на странице статей</h3>
								<div class="placement-block-nav" role="tablist" aria-label="Блоки страницы статей">
									<button class="placement-nav-btn" type="button" data-placement-block-button="articles-today">Читают сегодня</button>
									<button class="placement-nav-btn" type="button" data-placement-block-button="articles-first-time">Впервые на Кавказе?</button>
								</div>
							</div>

							<article class="placement-block" data-placement-block="articles-today">
								<header class="placement-block-head">
									<p class="placement-step-index">Шаг 3</p>
									<h4>Заполните блок «Читают сегодня»</h4>
									<p>Показывается на странице <code>/articles/</code> в верхнем блоке рекомендаций.</p>
								</header>
								<?php $renderPlacementChecklist('articles_today_refs[]', $catalogItems['articles'], $todaySelected, 'Статей пока нет. Добавьте их в раздел «Статьи».'); ?>
							</article>

							<article class="placement-block" data-placement-block="articles-first-time">
								<header class="placement-block-head">
									<p class="placement-step-index">Шаг 3</p>
									<h4>Заполните блок «Впервые на Кавказе?»</h4>
									<p>Показывается на странице <code>/articles/</code> в секции для новичков.</p>
								</header>
								<?php $renderPlacementChecklist('articles_first_time_refs[]', $catalogItems['articles'], $firstTimeSelected, 'Статей пока нет. Добавьте их в раздел «Статьи».'); ?>
							</article>
						</section>

						<section class="placement-page" data-placement-page="hotels">
							<div class="placement-step-card">
								<p class="placement-step-index">Шаг 2</p>
								<h3>Блок на странице отелей</h3>
								<div class="placement-block-nav" role="tablist" aria-label="Блоки страницы отелей">
									<button class="placement-nav-btn" type="button" data-placement-block-button="hotels-featured">Показывать первыми</button>
								</div>
							</div>

							<article class="placement-block" data-placement-block="hotels-featured">
								<header class="placement-block-head">
									<p class="placement-step-index">Шаг 3</p>
									<h4>Выберите отели, которые идут первыми</h4>
									<p>Показывается на странице <code>/hotels/</code> в начале списка.</p>
								</header>
								<?php $renderPlacementChecklist('hotels_featured_refs[]', $catalogItems['hotels'], $hotelsSelected, 'Отелей пока нет. Добавьте их в раздел «Отели».'); ?>
							</article>
						</section>

						<section class="placement-page" data-placement-page="regions">
							<div class="placement-step-card">
								<p class="placement-step-index">Шаг 2</p>
								<h3>Выберите региональную страницу</h3>
								<div class="placement-block-nav" role="tablist" aria-label="Региональные страницы">
									<?php foreach($regionPages as $regionPage): ?>
										<button class="placement-nav-btn" type="button" data-placement-block-button="region-<?php echo (int) $regionPage->id; ?>">
											<?php echo $sanitizer->entities((string) $regionPage->title); ?>
										</button>
									<?php endforeach; ?>
								</div>
							</div>

							<?php if(!$regionPages->count()): ?>
								<div class="placement-empty">Региональные страницы не найдены.</div>
							<?php endif; ?>

							<?php foreach($regionPages as $regionPage): ?>
								<?php
								$selectedTours = $regionPage->hasField('region_featured_tours') ? $regionPage->region_featured_tours : new PageArray();
								$selectedPlaces = $regionPage->hasField('region_featured_places') ? $regionPage->region_featured_places : new PageArray();
								$selectedArticles = $regionPage->hasField('region_featured_articles') ? $regionPage->region_featured_articles : new PageArray();
								$regionId = (int) $regionPage->id;
								?>
								<article class="placement-block" data-placement-block="region-<?php echo $regionId; ?>">
									<header class="placement-block-head">
										<p class="placement-step-index">Шаг 3</p>
										<h4><?php echo $sanitizer->entities((string) $regionPage->title); ?></h4>
										<p>Показывается на странице <?php echo $sanitizer->entities((string) $regionPage->url); ?></p>
									</header>
									<div class="placement-region-grid">
										<section class="placement-region-card">
											<h5>Туры региона</h5>
											<?php $renderPlacementChecklist('region_featured_tours[' . $regionId . '][]', $catalogItems['tours'], $selectedTours, 'Туров пока нет. Добавьте их в раздел «Туры».'); ?>
										</section>
										<section class="placement-region-card">
											<h5>Места региона</h5>
											<?php $renderPlacementChecklist('region_featured_places[' . $regionId . '][]', $catalogItems['places'], $selectedPlaces, 'Мест пока нет. Добавьте их в раздел «Места».'); ?>
										</section>
										<section class="placement-region-card">
											<h5>Статьи региона</h5>
											<?php $renderPlacementChecklist('region_featured_articles[' . $regionId . '][]', $catalogItems['articles'], $selectedArticles, 'Статей пока нет. Добавьте их в раздел «Статьи».'); ?>
										</section>
									</div>
								</article>
							<?php endforeach; ?>
						</section>
					</div>

					<div class="content-admin-form-actions">
						<button class="content-admin-btn is-primary" type="submit">Сохранить привязки</button>
					</div>
				</form>
				<script>
					(function() {
						var builder = document.querySelector('[data-placement-builder]');
						if(!builder) return;
						builder.classList.add('is-enhanced');

						var pageButtons = Array.prototype.slice.call(builder.querySelectorAll('[data-placement-page-button]'));
						var pagePanels = Array.prototype.slice.call(builder.querySelectorAll('[data-placement-page]'));

						var setActiveButton = function(buttons, activeValue, attrName) {
							buttons.forEach(function(btn) {
								var isActive = btn.getAttribute(attrName) === activeValue;
								btn.classList.toggle('is-active', isActive);
								btn.setAttribute('aria-pressed', isActive ? 'true' : 'false');
							});
						};

						var activateBlock = function(panel, blockId) {
							if(!panel) return;
							var blockButtons = Array.prototype.slice.call(panel.querySelectorAll('[data-placement-block-button]'));
							var blockPanels = Array.prototype.slice.call(panel.querySelectorAll('[data-placement-block]'));
							if(!blockPanels.length) return;

							var targetBlockId = blockId;
							var blockExists = blockPanels.some(function(blockPanel) {
								return blockPanel.getAttribute('data-placement-block') === targetBlockId;
							});
							if(!blockExists) {
								targetBlockId = blockPanels[0].getAttribute('data-placement-block') || '';
							}

							setActiveButton(blockButtons, targetBlockId, 'data-placement-block-button');
							blockPanels.forEach(function(blockPanel) {
								var isActive = blockPanel.getAttribute('data-placement-block') === targetBlockId;
								blockPanel.classList.toggle('is-active', isActive);
							});
						};

						var activatePage = function(pageId) {
							var targetPage = pageId;
							var pageExists = pagePanels.some(function(panel) {
								return panel.getAttribute('data-placement-page') === targetPage;
							});
							if(!pageExists && pagePanels.length) {
								targetPage = pagePanels[0].getAttribute('data-placement-page') || '';
							}

							setActiveButton(pageButtons, targetPage, 'data-placement-page-button');
							pagePanels.forEach(function(panel) {
								var isActive = panel.getAttribute('data-placement-page') === targetPage;
								panel.classList.toggle('is-active', isActive);
								if(isActive) activateBlock(panel, '');
							});
						};

						pageButtons.forEach(function(button) {
							button.addEventListener('click', function() {
								activatePage(button.getAttribute('data-placement-page-button') || '');
							});
						});

						pagePanels.forEach(function(panel) {
							var blockButtons = Array.prototype.slice.call(panel.querySelectorAll('[data-placement-block-button]'));
							blockButtons.forEach(function(button) {
								button.addEventListener('click', function() {
									activateBlock(panel, button.getAttribute('data-placement-block-button') || '');
								});
							});
						});

						activatePage('home');
					})();
				</script>
				<?php endif; ?>
		</div>
	</section>
</div>
