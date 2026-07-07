<?php
/**
 * Undocumented
 */
function __($string, array $parameters = [], $locale = null)
{
	return Evo\Lang::get($string, $parameters, $locale);
}


/**
 * Undocumented
 */
function __plural($string, $count, array $parameters = [], $locale = null)
{
	return Evo\Lang::choice($string, $count, $parameters, $locale);
}


/**
 * Undocumented
 */
function random_hash($length = 16)
{
	return substr(str_replace(['=', '+', '/'], '', base64_encode(random_bytes($length))), 0, $length);
}


/**
 * Undocumented
 */
function html_encode($string)
{
	if (is_array($string)) {
		return array_map('html_encode', $string);
	}
	return htmlspecialchars((string)$string, ENT_COMPAT, 'utf-8');
}


function fa_icon_aliases(): array
{
	static $aliases = null;

	if ($aliases === null) {
		$file = __DIR__ . '/lib-data/font-awesome-aliases.json';
		$aliases = is_file($file)
			? (json_decode(file_get_contents($file), true) ?: [])
			: [];
	}

	return $aliases;
}


/**
 * Normalise une référence d'icône Font Awesome vers la syntaxe v7.
 *
 * @param string $icon Nom (`fa-home`), classes (`fas fa-home`) ou classes complètes.
 * @param string $style Style par défaut: solid, regular ou brands.
 * @param array<int, string> $extra Classes utilitaires optionnelles (fa-fw, fa-lg, etc.).
 */
function fa_icon_classes(string $icon, string $style = 'solid', array $extra = []): string
{
	static $styles = [
		'solid' => 'fa-solid',
		'regular' => 'fa-regular',
		'brands' => 'fa-brands',
		'fas' => 'fa-solid',
		'far' => 'fa-regular',
		'fab' => 'fa-brands',
		'fa' => 'fa-solid',
		'fa-solid' => 'fa-solid',
		'fa-regular' => 'fa-regular',
		'fa-brands' => 'fa-brands',
	];

	static $utilities = [
		'fa-fw' => true, 'fa-lg' => true, 'fa-sm' => true, 'fa-xs' => true,
		'fa-2x' => true, 'fa-3x' => true, 'fa-4x' => true, 'fa-5x' => true,
		'fa-6x' => true, 'fa-7x' => true, 'fa-8x' => true, 'fa-9x' => true,
		'fa-10x' => true, 'fa-spin' => true, 'fa-pulse' => true, 'fa-inverse' => true,
		'fa-stack' => true, 'fa-stack-1x' => true, 'fa-stack-2x' => true,
		'fa-pull-left' => true, 'fa-pull-right' => true, 'fa-border' => true,
		'fa-li' => true, 'fa-classic' => true,
	];

	$aliases = fa_icon_aliases();
	$tokens = preg_split('/\s+/', trim($icon)) ?: [];
	$resolvedStyle = $styles[$style] ?? 'fa-solid';
	$iconName = null;
	$utilitiesFound = [];

	foreach ($tokens as $token) {
		if ($token === '') {
			continue;
		}

		if (isset($styles[$token])) {
			$resolvedStyle = $styles[$token];
			continue;
		}

		if (isset($utilities[$token])) {
			$utilitiesFound[] = $token;
			continue;
		}

		if (preg_match('/^fa-(.+)$/', $token, $matches)) {
			$name = $aliases[$matches[1]] ?? $matches[1];
			$iconName = 'fa-' . $name;
		} elseif (preg_match('/^[a-z0-9-]+$/', $token)) {
			$name = $aliases[$token] ?? $token;
			$iconName = 'fa-' . $name;
		}
	}

	if ($iconName === null) {
		return trim($icon . ' ' . implode(' ', $extra));
	}

	return trim(implode(' ', array_unique(array_merge([$resolvedStyle, $iconName], $extra, $utilitiesFound))));
}


/**
 * Rend une balise <i> Font Awesome normalisée (syntaxe v7).
 *
 * @param array<string, string> $attrs Attributs HTML additionnels (aria-hidden, title, etc.).
 */
function fa_icon_html(string $icon, string $style = 'solid', array $extra = [], array $attrs = [], string $class = ''): string
{
	$classAttr = trim(fa_icon_classes($icon, $style, $extra) . ' ' . $class);
	$parts = ['class="' . html_encode($classAttr) . '"'];

	foreach ($attrs as $name => $value) {
		$parts[] = html_encode($name) . '="' . html_encode((string) $value) . '"';
	}

	return '<i ' . implode(' ', $parts) . '></i>';
}


/**
 * Extrait le nom d'icône sans préfixe de style (pour affichage dans les sélecteurs).
 */
function fa_icon_label(string $icon): string
{
	$tokens = preg_split('/\s+/', trim(fa_icon_classes($icon))) ?: [];
	$ignored = [
		'fa-solid' => true,
		'fa-regular' => true,
		'fa-brands' => true,
		'fa' => true,
		'fas' => true,
		'far' => true,
		'fab' => true,
		'fa-fw' => true,
		'fa-lg' => true,
		'fa-sm' => true,
		'fa-xs' => true,
		'fa-spin' => true,
		'fa-pulse' => true,
		'fa-inverse' => true,
		'fa-stack' => true,
		'fa-stack-1x' => true,
		'fa-stack-2x' => true,
		'fa-pull-left' => true,
		'fa-pull-right' => true,
		'fa-border' => true,
		'fa-li' => true,
		'fa-classic' => true,
	];

	foreach ($tokens as $token) {
		if (isset($ignored[$token]) || preg_match('/^fa-\d+x$/', $token)) {
			continue;
		}

		if (preg_match('/^fa-([a-z0-9-]+)$/', $token, $matches)) {
			return $matches[1];
		}
	}

	return $icon;
}


/**
 * Fetch remote URL content (HTTPS/HTTP) with curl or stream wrappers.
 */
function fetch_remote_url(string $url, int $timeout = 15): ?string
{
	if (function_exists('curl_init')) {
		$curl = curl_init($url);
		if ($curl === false) {
			return null;
		}

		curl_setopt_array($curl, [
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_FOLLOWLOCATION => true,
			CURLOPT_CONNECTTIMEOUT => $timeout,
			CURLOPT_TIMEOUT => $timeout,
			CURLOPT_USERAGENT => 'Evo-CMS/' . EVO_VERSION,
			CURLOPT_SSL_VERIFYPEER => false,
			CURLOPT_SSL_VERIFYHOST => 0,
		]);

		$content = curl_exec($curl);
		$status = curl_getinfo($curl, CURLINFO_HTTP_CODE);
		curl_close($curl);

		if ($content !== false && $status >= 200 && $status < 300) {
			return $content;
		}
	}

	$scheme = parse_url($url, PHP_URL_SCHEME);
	if ($scheme && !in_array(strtolower($scheme), stream_get_wrappers(), true)) {
		return null;
	}

	$context = stream_context_create([
		'http' => [
			'method' => 'GET',
			'timeout' => $timeout,
			'header' => "User-Agent: Evo-CMS/" . EVO_VERSION . "\r\n",
		],
		'ssl' => [
			'verify_peer' => false,
			'allow_self_signed' => true,
		],
	]);

	$content = @file_get_contents($url, false, $context);

	return $content !== false ? $content : null;
}


/**
 * Accessible close button for Bootstrap dismissible alerts.
 */
function alert_close_button(string $label = 'Fermer'): string
{
	return '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="' . html_encode($label) . '"></button>';
}


/**
 * Render a country flag icon with graceful fallback when the PNG is missing.
 */
function flag_icon_html(string $code, string $title = '', int $height = 15): string
{
	$code = strtolower(trim($code));
	$aliases = ['en' => 'gb'];
	$fileCode = $aliases[$code] ?? $code;
	$title = $title ?: (@COUNTRIES[strtoupper($code)] ?? strtoupper($code));

	if ($url = App::getAsset("img/flags/{$fileCode}.png")) {
		return '<img src="' . html_encode($url) . '" style="height:' . (int)$height . 'px;" alt="' . html_encode($title) . '" title="' . html_encode($title) . '">';
	}

	return '<span class="flag-code text-muted" title="' . html_encode($title) . '">' . html_encode(strtoupper($code)) . '</span>';
}


/**
 * Indique si la page admin courante utilise le panneau carte standard.
 */
function admin_uses_panel_layout(): bool
{
	return App::GET('page') !== 'file_editor';
}

/**
 * Affiche le pied de page admin en dehors de la zone de contenu.
 */
function admin_render_footer(array $variables = []): void
{
	echo '<div class="admin-footer">';
	App::renderTemplate('footer.php', $variables);
	echo '</div>';
}

function admin_card_header(string $content = ''): string
{
	return '<div class="card-header">' . $content . '</div>';
}

function admin_card_body_open(string $class = 'card-body'): string
{
	return '<div class="' . $class . '">';
}

function admin_card_body_close(): string
{
	return '</div>';
}

/**
 * Affiche une grille de statistiques réutilisable dans l'admin.
 *
 * @param array<int, array{icon?: string, value?: string, label?: string, variant?: string, url?: string}> $items
 * @param array{class?: string, variant?: 'default'|'kpi'} $options
 */
function admin_stat_grid(array $items, array $options = []): string
{
	if (!$items) {
		return '';
	}

	$class = $options['class'] ?? 'mb-4';
	$variant = $options['variant'] ?? 'default';

	if ($variant === 'kpi') {
		$variants = ['primary', 'success', 'info', 'warning', 'danger', 'secondary'];
		$html = '<div class="row g-2 admin-stat-grid admin-stat-grid--kpi ' . html_encode($class) . '">';

		foreach ($items as $item) {
			$icon = html_encode($item['icon'] ?? 'fa-solid fa-chart-bar');
			$value = $item['value'] ?? '&mdash;';
			$label = html_encode($item['label'] ?? '');
			$url = $item['url'] ?? '';
			$tag = $url !== '' ? 'a' : 'div';
			$card_class = 'admin-kpi-card' . ($tag === 'a' ? ' admin-kpi-card--linked' : '');
			$attrs = $tag === 'a' ? ' href="' . html_encode($url) . '"' : '';
			$icon_class = 'admin-kpi-card__icon';

			if (!empty($item['variant']) && in_array($item['variant'], $variants, true)) {
				$icon_class .= ' admin-kpi-card__icon--' . $item['variant'];
			}

			$html .= '<div class="col-6 col-lg-3">';
			$html .= '<' . $tag . ' class="' . $card_class . '"' . $attrs . '>';
			$html .= '<span class="' . $icon_class . '"><i class="' . $icon . '" aria-hidden="true"></i></span>';
			$html .= '<span class="admin-kpi-card__body">';
			$html .= '<span class="admin-kpi-card__value">' . $value . '</span>';
			$html .= '<span class="admin-kpi-card__label">' . $label . '</span>';
			$html .= '</span>';
			$html .= '</' . $tag . '></div>';
		}

		return $html . '</div>';
	}

	$variants = ['primary', 'success', 'info', 'warning', 'danger', 'secondary'];
	$html = '<div class="row g-3 admin-stat-grid ' . html_encode($class) . '">';

	foreach ($items as $item) {
		$icon = html_encode($item['icon'] ?? 'fa-solid fa-chart-bar');
		$value = $item['value'] ?? '&mdash;';
		$label = html_encode($item['label'] ?? '');
		$icon_class = 'admin-stat-card__icon';
		$url = $item['url'] ?? '';
		$tag = $url !== '' ? 'a' : 'div';
		$card_class = 'admin-stat-card' . ($tag === 'a' ? ' admin-stat-card--linked' : '');
		$attrs = $tag === 'a' ? ' href="' . html_encode($url) . '"' : '';

		if (!empty($item['variant']) && in_array($item['variant'], $variants, true)) {
			$icon_class .= ' admin-stat-card__icon--' . $item['variant'];
		}

		$html .= '<div class="col-6 col-xl-3">';
		$html .= '<' . $tag . ' class="' . $card_class . '"' . $attrs . '>';
		$html .= '<div class="' . $icon_class . '"><i class="' . $icon . '" aria-hidden="true"></i></div>';
		$html .= '<div class="admin-stat-card__content">';
		$html .= '<span class="admin-stat-card__value">' . $value . '</span>';
		$html .= '<span class="admin-stat-card__label">' . $label . '</span>';
		$html .= '</div></' . $tag . '></div>';
	}

	return $html . '</div>';
}

/**
 * Affiche un titre de section réutilisable dans l'admin.
 */
function admin_section_title(string $title, string $class = 'mb-3'): string
{
	return '<h6 class="admin-section-title ' . html_encode($class) . '">' . html_encode($title) . '</h6>';
}

/**
 * Extrait un sous-ensemble de champs de configuration.
 *
 * @param array<string, array<string, mixed>> $settings
 * @param array<int, string> $keys
 */
function admin_settings_pick(array $settings, array $keys): array
{
	$picked = [];

	foreach ($keys as $key) {
		if (isset($settings[$key])) {
			$picked[$key] = $settings[$key];
		}
	}

	return $picked;
}

function admin_settings_prepare(array $settings): array
{
	foreach ($settings as $name => &$description) {
		$description['default'] = $description['default'] ?? App::getDefaultConfig($name);
		$description['value'] = App::getConfig($name);
	}
	unset($description);

	return $settings;
}

/**
 * Regroupe des champs consécutifs sur une même ligne responsive.
 *
 * @param array<string, array<string, mixed>> $settings
 */
function admin_settings_group_fields_row(array $settings, int $columns = 2, ?callable $matcher = null): array
{
	$matcher ??= static fn(array $field): bool => ($field['type'] ?? '') === 'image';
	$result = [];
	$pending = [];

	$flush = static function () use (&$result, &$pending, $columns): void {
		if (!$pending) {
			return;
		}

		if (count($pending) === 1) {
			$result += $pending;
		} else {
			$row_id = 'row-' . md5(implode(',', array_keys($pending)));

			foreach ($pending as $key => $field) {
				$field['row'] = $row_id;
				$result[$key] = $field;
			}
		}

		$pending = [];
	};

	foreach ($settings as $key => $field) {
		if ($matcher($field)) {
			$pending[$key] = $field;

			if (count($pending) >= $columns) {
				$flush();
			}
		} else {
			$flush();
			$result[$key] = $field;
		}
	}

	$flush();

	return $result;
}

/**
 * Affiche plusieurs sous-sections dans une seule carte avec un bouton Enregistrer.
 *
 * @param array<int, array{title?: string, icon?: string, description?: string, settings?: array, content?: string, class?: string, body_class?: string, empty?: string, extra?: string}> $groups
 */
function admin_settings_grouped_form(string $tab_id, array $groups, array $options = []): string
{
	$submit_label = $options['submit'] ?? __('form.save');
	$show_submit = ($options['submit'] ?? null) !== false;
	$class = $options['class'] ?? '';
	$form_id = 'admin-settings-form-' . random_hash(4);
	$has_settings = false;

	foreach ($groups as $group) {
		if (!empty($group['settings'])) {
			$has_settings = true;
			break;
		}
	}

	$html = '<form method="post" role="form" class="form-horizontal admin-settings-grouped-form ' . html_encode($class) . '" enctype="multipart/form-data" id="' . html_encode($form_id) . '">';
	$html .= '<input type="hidden" name="admin_settings_tab" value="' . html_encode($tab_id) . '">';
	$html .= '<section class="admin-settings-section admin-settings-section--grouped">';
	$html .= '<div class="admin-settings-section__body admin-settings-section__body--grouped">';

	foreach ($groups as $index => $group) {
		if ($index > 0) {
			$html .= '<hr class="admin-settings-subsection__divider">';
		}

		$settings = $group['settings'] ?? [];
		$content = '';

		if (!empty($group['content'])) {
			$content = $group['content'];
		} elseif (!$settings && !empty($group['empty'])) {
			$content = admin_settings_empty($group['empty'], $group['icon'] ?? 'fa-inbox');
		} else {
			$content = Widgets::formBuilder(null, admin_settings_prepare($settings), false, null);
		}

		if (!empty($group['extra'])) {
			$content .= $group['extra'];
		}

		$html .= admin_settings_subsection($group['title'] ?? '', $content, [
			'icon' => $group['icon'] ?? '',
			'description' => $group['description'] ?? '',
			'class' => $group['class'] ?? '',
			'body_class' => $group['body_class'] ?? '',
		]);
	}

	$html .= '</div>';

	if ($show_submit && $has_settings) {
		$html .= '<footer class="admin-settings-section__footer">';
		$html .= '<div class="text-center"><input class="btn btn-primary" type="submit" value="' . html_encode($submit_label) . '"></div>';
		$html .= '</footer>';
	}

	$html .= '</section>';

	if ($has_settings) {
		$html .= Widgets::formBuilderScript();
	}

	return $html . '</form>';
}

/**
 * Affiche une sous-section réutilisable dans les formulaires groupés admin.
 */
function admin_settings_subsection(string $title, string $content, array $options = []): string
{
	$icon = $options['icon'] ?? '';
	$description = $options['description'] ?? '';
	$wrapper_class = trim($options['class'] ?? '');
	$body_class = trim($options['body_class'] ?? '');
	$html = '<div class="admin-settings-subsection' . ($wrapper_class !== '' ? ' ' . html_encode($wrapper_class) : '') . '">';

	if ($title !== '') {
		$html .= '<header class="admin-settings-subsection__header">';

		if ($icon !== '') {
			$html .= '<span class="admin-settings-subsection__icon"><i class="fa-solid ' . html_encode($icon) . '" aria-hidden="true"></i></span>';
		}

		$html .= '<div class="admin-settings-subsection__heading">';
		$html .= '<h3 class="admin-settings-subsection__title">' . html_encode($title) . '</h3>';

		if ($description !== '') {
			$html .= '<p class="admin-settings-subsection__desc">' . $description . '</p>';
		}

		$html .= '</div></header>';
	}

	return $html . '<div class="admin-settings-subsection__body' . ($body_class !== '' ? ' ' . html_encode($body_class) : '') . '">' . $content . '</div></div>';
}

/**
 * Affiche une ligne de champ horizontal réutilisable dans l'admin.
 */
function admin_form_field_row(string $label, string $input, array $options = []): string
{
	$for = $options['for'] ?? '';
	$hint = $options['hint'] ?? '';
	$html = '<div class="mb-3 row">';
	$html .= '<label class="col-sm-3 col-form-label text-end"';

	if ($for !== '') {
		$html .= ' for="' . html_encode($for) . '"';
	}

	$html .= '>' . html_encode($label);

	if ($hint !== '') {
		$html .= ' <i class="fa-solid fa-circle-question" title="' . html_encode($hint) . '" aria-hidden="true"></i>';
	}

	return $html . '</label><div class="col-sm-8">' . $input . '</div></div>';
}

/**
 * Affiche un champ empilé (libellé au-dessus) réutilisable dans l'admin.
 */
function admin_form_field_stack(string $label, string $input, array $options = []): string
{
	$for = $options['for'] ?? '';
	$hint = $options['hint'] ?? '';
	$html = '<div class="admin-form-field-stack">';
	$html .= '<label class="admin-form-field-stack__label"';

	if ($for !== '') {
		$html .= ' for="' . html_encode($for) . '"';
	}

	$html .= '>' . html_encode($label);

	if ($hint !== '') {
		$html .= ' <i class="fa-solid fa-circle-question" title="' . html_encode($hint) . '" aria-hidden="true"></i>';
	}

	return $html . '</label><div class="admin-form-field-stack__control">' . $input . '</div></div>';
}

/**
 * Champ CSRF réutilisable pour les formulaires admin.
 */
function admin_csrf_field(): string
{
	if (empty($_SESSION['csrf'])) {
		return '';
	}

	return '<input type="hidden" name="csrf" value="' . html_encode((string) $_SESSION['csrf']) . '">';
}

/**
 * Indique si la requête POST contient un jeton CSRF valide.
 */
function admin_csrf_valid(): bool
{
	if (!IS_POST) {
		return true;
	}

	if (!(App::$protections & App::CSRF)) {
		return true;
	}

	return (string) ($_POST['csrf'] ?? '') === (string) ($_SESSION['csrf'] ?? '');
}

/**
 * Affiche une zone de dépôt de fichiers réutilisable (drag & drop).
 *
 * @param array{
 *   name?: string,
 *   multiple?: bool,
 *   accept?: string,
 *   id?: string,
 *   title?: string,
 *   hint?: string,
 *   browse?: string,
 *   summary?: string,
 *   auto_submit?: bool,
 *   preview?: bool,
 *   compact?: bool,
 *   detail?: bool,
 *   detail_status?: string,
 *   detail_size_label?: string,
 *   detail_type_label?: string,
 *   detail_mime_label?: string,
 *   detail_remove_label?: string,
 *   autofill?: array<string, mixed>,
 *   icon?: string,
 *   class?: string
 * } $options
 */
function admin_file_dropzone(array $options = []): string
{
	$name = $options['name'] ?? 'upload[]';
	$multiple = array_key_exists('multiple', $options) ? (bool) $options['multiple'] : true;
	$accept = $options['accept'] ?? '';
	$id = $options['id'] ?? 'dropzone-' . random_hash(8);
	$title = $options['title'] ?? __('admin/general.file_drop_title');
	$hint = $options['hint'] ?? __('admin/general.file_drop_hint');
	$browse = $options['browse'] ?? __('admin/general.file_drop_browse');
	$summary = $options['summary'] ?? __('admin/general.file_drop_summary');
	$icon = $options['icon'] ?? 'fa-cloud-upload-alt';
	$has_detail = !empty($options['detail']);
	$detail_remove_in = $options['detail_remove_in'] ?? 'detail';
	$class = trim('admin-file-dropzone'
		. (!empty($options['compact']) ? ' admin-file-dropzone--compact' : '')
		. (!empty($options['auto_submit']) ? ' admin-file-dropzone--auto-submit' : '')
		. ($has_detail ? ' admin-file-dropzone--detail' : '')
		. ($has_detail && $detail_remove_in === 'header' ? ' admin-file-dropzone--header-actions' : '')
		. ' ' . ($options['class'] ?? ''));

	$html = '<div class="' . html_encode($class) . '"';

	if (!empty($options['auto_submit'])) {
		$html .= ' data-auto-submit="1"';
	}

	if (!empty($options['preview'])) {
		$html .= ' data-preview="1"';
	}

	$html .= ' data-file-type-map="' . html_encode(json_encode(admin_file_type_map()), ENT_QUOTES) . '"';
	$html .= ' data-file-extension-map="' . html_encode(json_encode(admin_file_extension_icon_map()), ENT_QUOTES) . '"';

	if (!empty($options['autofill'])) {
		$html .= ' data-autofill="' . html_encode(json_encode($options['autofill']), ENT_QUOTES) . '"';
	}

	$html .= '>';
	$html .= '<div class="admin-file-dropzone__zone" tabindex="0" role="button" aria-label="' . html_encode($title) . '">';
	$html .= '<span class="admin-file-dropzone__icon"><i class="fas ' . html_encode($icon) . '" aria-hidden="true"></i></span>';
	$html .= '<span class="admin-file-dropzone__title">' . html_encode($title) . '</span>';
	$html .= '<span class="admin-file-dropzone__hint">' . html_encode($hint) . '</span>';
	$html .= '<button type="button" class="admin-file-dropzone__browse btn btn-outline-secondary btn-sm" data-dropzone-trigger>';
	$html .= html_encode($browse);
	$html .= '</button>';
	$html .= '<input type="file" class="admin-file-dropzone__input" id="' . html_encode($id) . '" name="' . html_encode($name) . '"';

	if ($multiple) {
		$html .= ' multiple';
	}

	if ($accept !== '') {
		$html .= ' accept="' . html_encode($accept) . '"';
	}

	$html .= ' hidden>';
	$html .= '</div>';

	if (!empty($options['preview'])) {
		$html .= '<div class="admin-file-dropzone__preview" data-dropzone-preview hidden></div>';
		$html .= '<p class="admin-file-dropzone__summary" data-dropzone-summary data-template="' . html_encode($summary) . '" hidden></p>';
	}

	if ($has_detail) {
		$detail_status = $options['detail_status'] ?? $summary;
		$detail_labels = [
			'size' => $options['detail_size_label'] ?? __('admin/general.file_drop_detail_size'),
			'type' => $options['detail_type_label'] ?? __('admin/general.file_drop_detail_type'),
			'mime' => $options['detail_mime_label'] ?? __('admin/general.file_drop_detail_mime'),
		];
		$detail_remove = $options['detail_remove_label'] ?? __('admin/general.file_drop_detail_remove');

		$html .= '<div class="admin-file-dropzone__detail" data-dropzone-detail hidden';
		$html .= ' data-detail-status="' . html_encode($detail_status, ENT_QUOTES) . '"';

		foreach ($detail_labels as $key => $label) {
			$html .= ' data-label-' . html_encode($key) . '="' . html_encode($label, ENT_QUOTES) . '"';
		}

		$html .= '>';
		$html .= '<span class="icon-background icon-background--rotate-45 admin-file-dropzone__detail-bg-icon fas fa-file" data-dropzone-detail-bg-icon aria-hidden="true"></span>';
		$html .= '<div class="admin-file-dropzone__detail-media" data-dropzone-detail-media aria-hidden="true"></div>';
		$html .= '<div class="admin-file-dropzone__detail-body">';
		$html .= '<div class="admin-file-dropzone__detail-content">';
		$html .= '<p class="admin-file-dropzone__detail-status"><i class="fas fa-check-circle" aria-hidden="true"></i> <span data-dropzone-detail-status-text></span></p>';
		$html .= '<p class="admin-file-dropzone__detail-name" data-dropzone-detail-name></p>';
		$html .= '<dl class="admin-file-dropzone__detail-meta" data-dropzone-detail-meta></dl>';
		$html .= '</div>';

		if ($detail_remove_in === 'detail') {
			$html .= '<div class="admin-file-dropzone__detail-actions">';
			$html .= '<button type="button" class="btn btn-outline-danger btn-sm" data-dropzone-detail-remove>';
			$html .= '<i class="fas fa-times me-1" aria-hidden="true"></i>' . html_encode($detail_remove);
			$html .= '</button></div>';
		}

		$html .= '</div></div>';
	}

	return $html . '</div>';
}

/**
 * Affiche des filtres à puces réutilisables (signalements, pages, téléchargements, etc.).
 *
 * @param array<string, array{label: string, icon?: string}> $items
 * @param array<int, string> $selected
 */
function admin_filter_chips(array $items, array $selected, string $name, string $label): string
{
	if (!$items) {
		return '';
	}

	$html = '<div class="admin-filter-chips">';
	$html .= '<span class="admin-filter-chips__label">' . html_encode($label) . '</span>';
	$html .= '<div class="admin-filter-chips__list">';

	foreach ($items as $value => $item) {
		$checked = in_array($value, $selected, true);
		$icon = $item['icon'] ?? 'fa-circle';
		$item_label = $item['label'] ?? $value;

		$html .= '<label class="admin-filter-chip' . ($checked ? ' admin-filter-chip--active' : '') . '">';
		$html .= '<input type="checkbox" name="' . html_encode($name) . '[]" value="' . html_encode($value) . '"' . ($checked ? ' checked' : '') . ' class="admin-filter-chip__input">';
		$html .= '<span class="admin-filter-chip__content">';
		$html .= '<i class="fas ' . html_encode($icon) . '" aria-hidden="true"></i>';
		$html .= html_encode($item_label);
		$html .= '</span></label>';
	}

	return $html . '</div></div>';
}

/**
 * Navigation par onglets réutilisable pour l'administration.
 *
 * @param array<string, array{label: string, icon?: string, badge?: string, disabled?: bool, href?: string}> $tabs
 * @param array{
 *   active?: string,
 *   type?: 'bootstrap'|'link',
 *   page?: string,
 *   class?: string,
 *   id?: string,
 *   aria_label?: string
 * } $options
 */
function admin_tabs(array $tabs, array $options = []): string
{
	$active = $options['active'] ?? '';
	$type = $options['type'] ?? 'bootstrap';
	$page = $options['page'] ?? '';
	$extra_class = trim($options['class'] ?? '');
	$id = $options['id'] ?? '';
	$aria_label = $options['aria_label'] ?? '';

	$classes = trim('nav nav-tabs admin-tabs ' . $extra_class);
	$html = '<ul class="' . html_encode($classes) . '" role="tablist"';

	if ($id !== '') {
		$html .= ' id="' . html_encode($id) . '"';
	}

	if ($aria_label !== '') {
		$html .= ' aria-label="' . html_encode($aria_label) . '"';
	}

	$html .= '>';

	foreach ($tabs as $tab_id => $tab) {
		$is_active = $active === $tab_id;
		$link_class = 'nav-link' . ($is_active ? ' active' : '');

		if (!empty($tab['disabled'])) {
			$link_class .= ' disabled';
		}

		$ms_auto = !empty($tab['ms_auto']) ? ' ms-auto' : '';
		$html .= '<li class="nav-item' . $ms_auto . '" role="presentation">';
		$html .= '<a class="' . $link_class . '" role="tab" aria-selected="' . ($is_active ? 'true' : 'false') . '"';

		if ($type === 'link') {
			$href = $tab['href'] ?? ('?page=' . rawurlencode($page) . '&tab=' . rawurlencode((string) $tab_id));
			$html .= ' href="' . html_encode($href) . '"';
		} elseif (!empty($tab['external'])) {
			$href = $tab['href'] ?? '#';
			$html .= ' href="' . html_encode($href) . '" target="_blank" rel="noopener noreferrer"';
		} elseif (!empty($tab['disabled'])) {
			$html .= ' id="' . html_encode($tab_id) . '-tab" tabindex="-1" aria-disabled="true"';
		} else {
			$href = $tab['href'] ?? ('#' . $tab_id);
			$html .= ' id="' . html_encode($tab_id) . '-tab" data-bs-toggle="tab" href="' . html_encode($href) . '" aria-controls="' . html_encode($tab_id) . '"';
		}

		$html .= '>';

		if (!empty($tab['icon'])) {
			$html .= '<i class="fa-solid ' . html_encode($tab['icon']) . ' me-2" aria-hidden="true"></i>';
		}

		$html .= html_encode($tab['label']);

		if (!empty($tab['badge'])) {
			$html .= ' ' . $tab['badge'];
		}

		$html .= '</a></li>';
	}

	return $html . '</ul>';
}

/**
 * Affiche les onglets de la page paramètres admin.
 *
 * @param array<string, array{label: string, icon?: string}> $tabs
 */
function admin_settings_tabs(array $tabs, string $active): string
{
	return admin_tabs($tabs, ['active' => $active, 'type' => 'bootstrap']);
}

/**
 * Ouvre un panneau d'onglet Bootstrap réutilisable pour l'administration.
 */
function admin_tab_pane_open(string $id, bool $active, string $pane_class = ''): string
{
	$classes = 'tab-pane';

	if ($pane_class !== '') {
		$classes .= ' ' . trim($pane_class);
	}

	if ($active) {
		$classes .= ' active show';
	}

	return '<div class="' . html_encode($classes) . '" id="' . html_encode($id) . '" role="tabpanel" aria-labelledby="' . html_encode($id) . '-tab" tabindex="0">';
}

function admin_settings_tab_open(string $id, bool $active): string
{
	return admin_tab_pane_open($id, $active, 'admin-settings__pane');
}

function admin_settings_tab_close(): string
{
	return '</div>';
}

/**
 * Affiche une section de paramètres réutilisable.
 */
function admin_settings_section(string $title, string $content, array $options = []): string
{
	$icon = $options['icon'] ?? '';
	$class = $options['class'] ?? '';
	$description = $options['description'] ?? '';
	$html = '<section class="admin-settings-section ' . html_encode($class) . '">';

	if ($title !== '') {
		$html .= '<header class="admin-settings-section__header">';

		if ($icon !== '') {
			$html .= '<span class="admin-settings-section__icon"><i class="fa-solid ' . html_encode($icon) . '" aria-hidden="true"></i></span>';
		}

		$html .= '<div class="admin-settings-section__heading">';
		$html .= '<h3 class="admin-settings-section__title">' . html_encode($title) . '</h3>';

		if ($description !== '') {
			$html .= '<p class="admin-settings-section__desc">' . $description . '</p>';
		}

		$html .= '</div></header>';
	}

	return $html . '<div class="admin-settings-section__body">' . $content . '</div></section>';
}

function admin_settings_empty(string $message, string $icon = 'fa-inbox'): string
{
	return '<div class="admin-settings-empty">'
		. '<i class="fa-solid ' . html_encode($icon) . ' admin-settings-empty__icon" aria-hidden="true"></i>'
		. '<p class="admin-settings-empty__text">' . html_encode($message) . '</p>'
		. '</div>';
}

/**
 * État vide compact à l'intérieur d'un panneau admin (section, catégorie, etc.).
 *
 * @param array{class?: string, icon_style?: 'far'|'fas'} $options
 */
function admin_panel_empty(string $message, string $icon = 'fa-inbox', array $options = []): string
{
	$class = trim('admin-panel-empty ' . ($options['class'] ?? ''));
	$icon_style = ($options['icon_style'] ?? 'far') === 'fas' ? 'fas' : 'far';

	return '<div class="' . html_encode($class) . '">'
		. '<span class="admin-panel-empty__icon"><i class="' . html_encode($icon_style) . ' ' . html_encode($icon) . '" aria-hidden="true"></i></span>'
		. '<p class="admin-panel-empty__text">' . html_encode($message) . '</p>'
		. '</div>';
}

/**
 * Génère un identifiant DOM sûr pour une section repliable.
 */
function admin_collapsible_slug(string $prefix, string $key): string
{
	$slug = strtolower(preg_replace('/[^a-zA-Z0-9_-]+/', '-', $key));
	$slug = trim($slug, '-');

	return $prefix . ($slug !== '' ? $slug : random_hash(8));
}

/**
 * Bouton d'ouverture/fermeture pour une section repliable (Bootstrap collapse).
 *
 * @param array{class?: string, label?: string} $options
 */
function admin_collapsible_toggle(string $target_id, string $content, bool $expanded = true, array $options = []): string
{
	$class = trim('admin-collapsible__toggle' . ($expanded ? '' : ' collapsed') . ' ' . ($options['class'] ?? ''));
	$label = $options['label'] ?? __('admin/general.btn_toggle_section');

	$html = '<button type="button" class="' . html_encode($class) . '" data-bs-toggle="collapse" data-bs-target="#' . html_encode($target_id) . '" aria-expanded="' . ($expanded ? 'true' : 'false') . '" aria-controls="' . html_encode($target_id) . '">';
	$html .= '<span class="admin-collapsible__chevron" aria-hidden="true"><i class="fas fa-chevron-down"></i></span>';
	$html .= $content;
	$html .= '<span class="visually-hidden">' . html_encode($label) . '</span>';
	$html .= '</button>';

	return $html;
}

function admin_collapsible_body_open(string $id, bool $expanded = true, string $class = ''): string
{
	return '<div class="collapse' . ($expanded ? ' show' : '') . ' admin-collapsible__body ' . html_encode(trim($class)) . '" id="' . html_encode($id) . '">';
}

function admin_collapsible_body_close(): string
{
	return '</div>';
}

/**
 * Affiche la grille de sélection de thèmes.
 *
 * @param array<string, array{0: object, 1: string}> $themes
 */
function admin_settings_theme_grid(array $themes, string $activeDir): string
{
	$html = '<div class="row g-4 admin-settings-themes">';

	foreach ($themes as $dir => [$theme, $preview]) {
		$is_active = ($dir === $activeDir);
		$html .= '<div class="col-md-6 col-xl-4">';
		$html .= '<article class="admin-settings-theme' . ($is_active ? ' admin-settings-theme--active' : '') . '">';
		$html .= '<div class="admin-settings-theme__preview">';
		$html .= '<img src="' . html_encode(App::getLocalURL($preview)) . '" alt="' . html_encode($theme->name) . '">';
		$html .= '</div>';
		$html .= '<div class="admin-settings-theme__body">';
		$html .= '<h4 class="admin-settings-theme__name">' . html_encode($theme->name) . '</h4>';
		$html .= '<p class="admin-settings-theme__version">v' . html_encode($theme->version) . '</p>';
		$html .= '<p class="admin-settings-theme__desc">' . html_encode($theme->description) . '</p>';

		if ($is_active) {
			$html .= '<span class="badge admin-settings-theme__badge"><i class="fa-solid fa-check me-1" aria-hidden="true"></i>' . html_encode(__('admin/general.theme_enabled')) . '</span>';
		} else {
			$html .= '<button type="submit" class="btn btn-sm btn-primary" name="theme" value="' . html_encode($dir) . '">' . html_encode(__('admin/general.theme_active_btn')) . '</button>';
		}

		$html .= '</div></article></div>';
	}

	return $html . '</div>';
}

/**
 * Contenu de sélection de thème pour un formulaire groupé admin.
 *
 * @param array<string, array{0: object, 1: string}> $themes
 */
function admin_settings_theme_selection(array $themes, string $activeDir): string
{
	return '<div class="admin-settings-theme-form">'
		. admin_settings_theme_grid($themes, $activeDir)
		. '<p class="admin-settings-theme-form__hint">' . __('admin/general.theme_tips') . '</p>'
		. '</div>';
}

/**
 * Affiche l'onglet thème complet : sélection + préférences du thème actif.
 *
 * @param array<string, array{0: object, 1: string}> $themes
 * @param array<string, array<string, mixed>>|null $themeSettings
 */
function admin_settings_theme_tab(array $themes, string $activeDir, ?array $themeSettings = null): string
{
	$groups = [
		[
			'title' => __('admin/general.tab_theme'),
			'icon' => 'fa-palette',
			'content' => admin_settings_theme_selection($themes, $activeDir),
			'class' => 'container',
		],
	];

	if ($themeSettings) {
		$groups[] = [
			'title' => __('admin/general.theme_title1'),
			'icon' => 'fa-paintbrush',
			'settings' => admin_settings_group_fields_row(
				$themeSettings,
				2,
				static fn(array $field): bool => in_array($field['type'] ?? '', ['image', 'color'], true)
			),
			'class' => 'container',
		];
	}

	return admin_settings_grouped_form('theme', $groups, [
		'submit' => $themeSettings ? null : false,
	]);
}

function admin_settings_test_mail(?string $email = null): string
{
	$email = $email ?: App::getCurrentUser()->email;

	return '<form method="post" class="admin-settings-test-mail">'
		. '<label class="form-label admin-settings-test-mail__label">' . html_encode(__('admin/general.email_test')) . '</label>'
		. '<div class="input-group">'
		. '<input type="email" class="form-control" name="mail||send-test-mail" value="' . html_encode($email) . '" placeholder="email@example.com" required>'
		. '<button type="submit" class="btn btn-outline-secondary">'
		. '<i class="fa-solid fa-paper-plane me-1" aria-hidden="true"></i>' . html_encode(__('admin/general.email_test'))
		. '</button></div></form>';
}

/**
 * Affiche un tableau de statistiques classique réutilisable dans l'admin.
 *
 * @param array<int, array{icon?: string, value?: string, label?: string, url?: string}> $items
 */
function admin_stat_table(array $items, array $options = []): string
{
	if (!$items) {
		return '';
	}

	$class = $options['class'] ?? 'mb-4';
	$html = '<div class="table-responsive ' . html_encode($class) . '">';
	$html .= '<table class="table table-bordered table-sm admin-stat-table mb-0">';
	$html .= '<thead><tr>';
	$html .= '<th scope="col">' . html_encode(__('admin/dashboard.table_metric')) . '</th>';
	$html .= '<th scope="col" class="text-end">' . html_encode(__('admin/dashboard.table_value')) . '</th>';
	$html .= '</tr></thead><tbody>';

	foreach ($items as $item) {
		$icon = html_encode($item['icon'] ?? 'fa-solid fa-chart-bar');
		$value = $item['value'] ?? '&mdash;';
		$label = html_encode($item['label'] ?? '');
		$url = $item['url'] ?? '';

		$html .= '<tr>';

		if ($url !== '') {
			$html .= '<td><a href="' . html_encode($url) . '" class="admin-stat-table__link">';
			$html .= '<i class="' . $icon . ' text-muted me-2" aria-hidden="true"></i>' . $label;
			$html .= '</a></td>';
			$html .= '<td class="text-end"><a href="' . html_encode($url) . '" class="admin-stat-table__link admin-stat-table__value">' . $value . '</a></td>';
		} else {
			$html .= '<td><i class="' . $icon . ' text-muted me-2" aria-hidden="true"></i>' . $label . '</td>';
			$html .= '<td class="text-end admin-stat-table__value">' . $value . '</td>';
		}

		$html .= '</tr>';
	}

	return $html . '</tbody></table></div>';
}

/**
 * Affiche un panneau d'informations clé/valeur réutilisable dans l'admin.
 *
 * @param array<int, array{title?: string, icon?: string, items: array<int, array{label: string, value?: string}>}> $panels
 * @param array{class?: string, variant?: 'panel'|'table'|'modern', columns?: int} $options
 */
function admin_info_grid(array $panels, array $options = []): string
{
	if (!$panels) {
		return '';
	}

	$class = $options['class'] ?? 'mb-4';
	$variant = $options['variant'] ?? 'panel';
	$columns = (int) ($options['columns'] ?? (count($panels) === 1 ? 1 : 2));
	$columns = max(1, min(3, $columns));

	switch ($columns) {
		case 1:
			$column_class = 'col-12';
			break;
		case 3:
			$column_class = 'col-md-6 col-lg-4';
			break;
		default:
			$column_class = 'col-md-6';
	}
	$accents = ['primary', 'success', 'info', 'warning', 'danger', 'secondary', 'amber'];
	$panel_class = $variant === 'modern' ? ' admin-info-panel--modern' : '';

	if ($variant === 'table') {
		$html = '<div class="row g-1 admin-info-grid ' . html_encode($class) . '">';

		foreach ($panels as $panel) {
			$html .= '<div class="' . $column_class . '">';
			$html .= '<div class="table-responsive">';
			$html .= '<table class="table table-bordered table-sm admin-info-table mb-0">';

			if (!empty($panel['title'])) {
				$html .= '<caption>' . html_encode($panel['title']) . '</caption>';
			}

			$html .= '<tbody>';

			foreach ($panel['items'] as $item) {
				$html .= '<tr>';
				$html .= '<th scope="row">' . html_encode($item['label']) . '</th>';
				$html .= '<td>' . ($item['value'] ?? '&mdash;') . '</td>';
				$html .= '</tr>';
			}

			$html .= '</tbody></table></div></div>';
		}

		return $html . '</div>';
	}

	$html = '<div class="row g-1 admin-info-grid ' . html_encode($class) . '">';

	foreach ($panels as $panel) {
		$html .= '<div class="' . $column_class . '">';
		$panel_accent = $panel['accent'] ?? '';

		if ($variant === 'modern' && $panel_accent !== '' && in_array($panel_accent, $accents, true)) {
			$panel_class_with_accent = $panel_class . ' admin-info-panel--accent-' . $panel_accent;
		} else {
			$panel_class_with_accent = $panel_class;
		}

		$html .= '<div class="admin-info-panel' . $panel_class_with_accent . '">';

		if (!empty($panel['title'])) {
			$html .= '<div class="admin-info-panel__header">';

			if ($variant === 'modern') {
				if (!empty($panel['icon'])) {
					$html .= '<span class="admin-info-panel__badge"><i class="' . html_encode($panel['icon']) . '" aria-hidden="true"></i></span>';
				}

				$html .= '<span class="admin-info-panel__title">' . html_encode($panel['title']) . '</span>';
			} else {
				if (!empty($panel['icon'])) {
					$html .= '<i class="' . html_encode($panel['icon']) . ' me-2" aria-hidden="true"></i>';
				}

				$html .= html_encode($panel['title']);
			}

			$html .= '</div>';
		}

		$html .= '<dl class="admin-info-panel__list mb-0">';

		foreach ($panel['items'] as $item) {
			$html .= '<div class="admin-info-panel__row">';
			$html .= '<dt>' . html_encode($item['label']) . '</dt>';
			$html .= '<dd>' . ($item['value'] ?? '&mdash;') . '</dd>';
			$html .= '</div>';
		}

		$html .= '</dl></div></div>';
	}

	return $html . '</div>';
}

/**
 * Affiche une grille de tuiles métriques modernes réutilisable dans l'admin.
 *
 * @param array<int, array{icon?: string, value?: string, label?: string, variant?: string, url?: string}> $items
 */
function admin_metric_tiles(array $items, array $options = []): string
{
	if (!$items) {
		return '';
	}

	$variants = ['primary', 'success', 'info', 'warning', 'danger', 'secondary', 'amber'];
	$class = $options['class'] ?? 'mb-0';
	$html = '<div class="admin-metric-tiles ' . html_encode($class) . '">';

	foreach ($items as $item) {
		$icon = html_encode($item['icon'] ?? 'fa-solid fa-chart-bar');
		$value = $item['value'] ?? '&mdash;';
		$label = html_encode($item['label'] ?? '');
		$url = $item['url'] ?? '';
		$tag = $url !== '' ? 'a' : 'div';
		$item_class = 'admin-metric-tile';

		if (!empty($item['variant']) && in_array($item['variant'], $variants, true)) {
			$item_class .= ' admin-metric-tile--' . $item['variant'];
		}

		$attrs = $tag === 'a' ? ' href="' . html_encode($url) . '"' : '';

		$html .= '<' . $tag . ' class="' . $item_class . '"' . $attrs . '>';
		$html .= '<span class="admin-metric-tile__orb"><i class="' . $icon . '" aria-hidden="true"></i></span>';
		$html .= '<span class="admin-metric-tile__body">';
		$html .= '<span class="admin-metric-tile__value">' . $value . '</span>';
		$html .= '<span class="admin-metric-tile__label">' . $label . '</span>';
		$html .= '</span></' . $tag . '>';
	}

	return $html . '</div>';
}

/**
 * Affiche des cartes de détails modernes réutilisables dans l'admin.
 *
 * @param array<int, array{title?: string, icon?: string, accent?: string, items: array<int, array{label: string, value?: string}>}> $sections
 */
function admin_detail_cards(array $sections, array $options = []): string
{
	if (!$sections) {
		return '';
	}

	$accents = ['primary', 'success', 'info', 'warning', 'danger', 'secondary', 'amber'];
	$class = $options['class'] ?? '';
	$html = '<div class="admin-detail-cards ' . html_encode($class) . '">';

	foreach ($sections as $section) {
		$card_class = 'admin-detail-card';
		$accent = $section['accent'] ?? '';

		if ($accent !== '' && in_array($accent, $accents, true)) {
			$card_class .= ' admin-detail-card--' . $accent;
		}

		$html .= '<article class="' . $card_class . '">';

		if (!empty($section['title'])) {
			$html .= '<header class="admin-detail-card__head">';

			if (!empty($section['icon'])) {
				$html .= '<span class="admin-detail-card__orb"><i class="' . html_encode($section['icon']) . '" aria-hidden="true"></i></span>';
			}

			$html .= '<h6 class="admin-detail-card__title">' . html_encode($section['title']) . '</h6>';
			$html .= '</header>';
		}

		$html .= '<dl class="admin-detail-card__list mb-0">';

		foreach ($section['items'] as $item) {
			$html .= '<div class="admin-detail-card__row">';
			$html .= '<dt>' . html_encode($item['label']) . '</dt>';
			$html .= '<dd>' . ($item['value'] ?? '&mdash;') . '</dd>';
			$html .= '</div>';
		}

		$html .= '</dl></article>';
	}

	return $html . '</div>';
}

/**
 * Affiche une barre de métriques unifiée réutilisable dans l'admin.
 *
 * @param array<int, array{icon?: string, value?: string, label?: string, variant?: string, url?: string}> $items
 */
function admin_metrics_strip(array $items, array $options = []): string
{
	if (!$items) {
		return '';
	}

	$variants = ['primary', 'success', 'info', 'warning', 'danger', 'secondary', 'amber'];
	$class = $options['class'] ?? 'mb-3';
	$html = '<div class="admin-metrics-strip ' . html_encode($class) . '">';

	foreach ($items as $item) {
		$icon = html_encode($item['icon'] ?? 'fa-solid fa-chart-bar');
		$value = $item['value'] ?? '&mdash;';
		$label = html_encode($item['label'] ?? '');
		$url = $item['url'] ?? '';
		$tag = $url !== '' ? 'a' : 'div';
		$item_class = 'admin-metrics-strip__item';

		if (!empty($item['variant']) && in_array($item['variant'], $variants, true)) {
			$item_class .= ' admin-metrics-strip__item--' . $item['variant'];
		}

		$attrs = $tag === 'a' ? ' href="' . html_encode($url) . '"' : '';

		$html .= '<' . $tag . ' class="' . $item_class . '"' . $attrs . '>';
		$html .= '<span class="admin-metrics-strip__icon"><i class="' . $icon . '" aria-hidden="true"></i></span>';
		$html .= '<span class="admin-metrics-strip__value">' . $value . '</span>';
		$html .= '<span class="admin-metrics-strip__label">' . $label . '</span>';
		$html .= '</' . $tag . '>';
	}

	return $html . '</div>';
}

/**
 * Affiche un panneau de détails multi-sections réutilisable dans l'admin.
 *
 * @param array<int, array{title?: string, icon?: string, accent?: string, items: array<int, array{label: string, value?: string}>}> $sections
 */
function admin_detail_board(array $sections, array $options = []): string
{
	if (!$sections) {
		return '';
	}

	$accents = ['primary', 'success', 'info', 'warning', 'danger', 'secondary', 'amber'];
	$class = $options['class'] ?? '';
	$html = '<div class="admin-detail-board ' . html_encode($class) . '">';

	foreach ($sections as $section) {
		$section_class = 'admin-detail-board__section';
		$accent = $section['accent'] ?? '';

		if ($accent !== '' && in_array($accent, $accents, true)) {
			$section_class .= ' admin-detail-board__section--' . $accent;
		}

		$html .= '<section class="' . $section_class . '">';

		if (!empty($section['title'])) {
			$html .= '<div class="admin-detail-board__head">';

			if (!empty($section['icon'])) {
				$html .= '<i class="' . html_encode($section['icon']) . '" aria-hidden="true"></i>';
			}

			$html .= '<span>' . html_encode($section['title']) . '</span></div>';
		}

		$html .= '<dl class="admin-detail-board__list mb-0">';

		foreach ($section['items'] as $item) {
			$html .= '<div class="admin-detail-board__row">';
			$html .= '<dt>' . html_encode($item['label']) . '</dt>';
			$html .= '<dd>' . ($item['value'] ?? '&mdash;') . '</dd>';
			$html .= '</div>';
		}

		$html .= '</dl></section>';
	}

	return $html . '</div>';
}

/**
 * Affiche une bannière d'état réutilisable dans l'admin.
 *
 * @param array{action: string, label: string, icon?: string, variant?: string}|null $action
 */
function admin_status_bar(string $variant, string $title, string $details = '', ?array $action = null): string
{
	$allowed = ['success', 'info', 'warning', 'danger'];
	$variant = in_array($variant, $allowed, true) ? $variant : 'info';

	$html = '<div class="admin-status-bar alert alert-' . $variant . ' mb-0">';
	$html .= '<div class="d-flex flex-wrap justify-content-between align-items-center gap-2">';
	$html .= '<div><strong>' . $title . '</strong>';

	if ($details !== '') {
		$html .= '<div class="small mt-1 text-muted">' . $details . '</div>';
	}

	$html .= '</div>';

	if ($action) {
		$btn_variant = html_encode($action['variant'] ?? 'secondary');
		$html .= '<form method="post" class="m-0">';
		$html .= '<input type="hidden" name="action" value="' . html_encode($action['action']) . '">';
		$html .= '<button type="submit" class="btn btn-outline-' . $btn_variant . ' btn-sm">';

		if (!empty($action['icon'])) {
			$html .= '<i class="' . html_encode($action['icon']) . ' me-1"></i>';
		}

		$html .= html_encode($action['label']) . '</button></form>';
	}

	return $html . '</div></div>';
}

/**
 * Affiche la navigation de la page modules admin (style dashboard).
 *
 * @param array<string, array{label: string, icon?: string}> $tabs
 */
function admin_modules_nav(array $tabs, string $active): string
{
	return admin_tabs($tabs, [
		'active' => $active,
		'type' => 'bootstrap',
		'aria_label' => __('admin/modules.main_title'),
	]);
}

function admin_modules_tab_open(string $id, bool $active): string
{
	return admin_tab_pane_open($id, $active, 'admin-modules-board__pane');
}

function admin_modules_tab_close(): string
{
	return '</div>';
}

function admin_modules_catalog_notice(bool $unavailable): string
{
	if (!$unavailable) {
		return '';
	}

	return '<div class="admin-modules-board__notice">'
		. admin_status_bar(
			'warning',
			'<i class="fa-solid fa-triangle-exclamation me-1" aria-hidden="true"></i> ' . __('admin/modules.alert_catalog_error')
		)
		. '</div>';
}

/**
 * Affiche la navigation de la page sauvegardes admin (style dashboard).
 *
 * @param array<string, array{label: string, icon?: string, badge?: string}> $tabs
 */
function admin_backup_nav(array $tabs, string $active): string
{
	return admin_tabs($tabs, [
		'active' => $active,
		'type' => 'link',
		'page' => 'backup',
		'aria_label' => __('admin/menu.sub_backup'),
	]);
}

function admin_modules_empty(string $message, string $icon = 'fa-box-open'): string
{
	return admin_settings_empty($message, $icon);
}

/**
 * Boutons réutilisables pour ajuster la taille du texte d'un tableau admin.
 *
 * @param array{aria_label?: string, smaller_label?: string, larger_label?: string} $options
 */
function admin_modules_table_text_size_toolbar(array $options = []): string
{
	$aria_label = $options['aria_label'] ?? __('admin/users.table_text_size');
	$smaller_label = $options['smaller_label'] ?? __('admin/users.table_text_smaller');
	$larger_label = $options['larger_label'] ?? __('admin/users.table_text_larger');

	return '<div class="admin-modules-table__text-size" role="group" aria-label="' . html_encode($aria_label) . '">'
		. '<button type="button" class="btn btn-sm btn-outline-secondary admin-modules-table__text-size-btn" data-admin-table-text-size-down title="' . html_encode($smaller_label) . '" aria-label="' . html_encode($smaller_label) . '">'
		. '<i class="fa-solid fa-minus" aria-hidden="true"></i></button>'
		. '<button type="button" class="btn btn-sm btn-outline-secondary admin-modules-table__text-size-btn" data-admin-table-text-size-up title="' . html_encode($larger_label) . '" aria-label="' . html_encode($larger_label) . '">'
		. '<i class="fa-solid fa-plus" aria-hidden="true"></i></button>'
		. '</div>';
}

/**
 * Affiche un tableau réutilisable pour la page modules admin.
 *
 * @param array<int, string> $columns
 * @param array<int, array<int, string>> $rows
 * @param array{caption?: string, icon?: string, class?: string, empty?: string, empty_icon?: string, text_size?: array<string, mixed>|true} $options
 */
function admin_modules_table(array $columns, array $rows, array $options = []): string
{
	if (!$rows) {
		if (!empty($options['empty'])) {
			return admin_modules_empty($options['empty'], $options['empty_icon'] ?? 'fa-inbox');
		}

		return '';
	}

	$class = $options['class'] ?? '';
	$variant = $options['variant'] ?? 'data';
	$layout = $options['layout'] ?? ($variant === 'info' ? 'info' : 'catalog');
	$wrap_attrs = '';

	if (!empty($options['text_size'])) {
		$text_size = is_array($options['text_size']) ? $options['text_size'] : [];
		$wrap_attrs = ' data-admin-table-text-size="1"'
			. ' data-admin-table-text-size-key="' . html_encode((string) ($text_size['storage_key'] ?? 'admin-table-text-size')) . '"'
			. ' data-admin-table-text-size-min="' . (int) ($text_size['min'] ?? 0) . '"'
			. ' data-admin-table-text-size-max="' . (int) ($text_size['max'] ?? 4) . '"'
			. ' data-admin-table-text-size-default="' . (int) ($text_size['default'] ?? 1) . '"';
	}

	$html = '<div class="admin-modules-table-wrap ' . html_encode($class) . '"' . $wrap_attrs . '">';

	if (!empty($options['caption'])) {
		$html .= '<div class="admin-modules-table__toolbar">';
		$html .= '<div class="admin-modules-table__caption">';

		if (!empty($options['icon'])) {
			$accent = $options['accent'] ?? 'primary';
			$html .= '<span class="admin-modules-table__caption-icon admin-modules-table__caption-icon--' . html_encode($accent) . '">';
			$html .= '<i class="' . html_encode($options['icon']) . '" aria-hidden="true"></i></span>';
		}

		$html .= '<span class="admin-modules-table__caption-text">' . html_encode($options['caption']) . '</span>';
		$html .= '</div>';

		if (!empty($options['toolbar_actions'])) {
			$html .= '<div class="admin-modules-table__toolbar-actions">' . $options['toolbar_actions'] . '</div>';
		}

		if (!empty($options['text_size'])) {
			$html .= admin_modules_table_text_size_toolbar(is_array($options['text_size']) ? $options['text_size'] : []);
		}

		$html .= '<span class="admin-modules-table__count">' . count($rows) . '</span>';
		$html .= '</div>';
	}

	$html .= '<div class="table-responsive admin-modules-table-scroll">';
	$html .= '<table class="table admin-modules-table mb-0';

	if ($layout === 'reports') {
		$html .= ' admin-reports-table';
	}

	if ($layout === 'servers') {
		$html .= ' admin-servers-table';
	}

	if ($layout === 'security') {
		$html .= ' admin-security-table';
	}

	if ($layout === 'downloads') {
		$html .= ' admin-downloads-table';
	}

	if ($layout === 'pages') {
		$html .= ' admin-pages-table';
	}

	if ($layout === 'gallery') {
		$html .= ' admin-gallery-table';
	}

	if ($layout === 'page_edit') {
		$html .= ' admin-page-edit-history-table';
	}

	if ($layout === 'comments') {
		$html .= ' admin-comments-table';
	}

	if ($layout === 'users') {
		$html .= ' admin-users-table';
	}

	if ($layout === 'user_view_messages') {
		$html .= ' admin-user-view-messages-table';
	}

	if ($layout === 'user_view_history') {
		$html .= ' admin-user-view-history-table';
	}

	if ($layout === 'groups') {
		$html .= ' admin-groups-table';
	}

	if ($variant === 'info') {
		$html .= ' admin-modules-table--info';
	}

	$html .= '">';
	$html .= admin_modules_table_colgroup($layout);

	if ($variant !== 'info' && $columns) {
		$html .= '<thead><tr>';

		foreach ($columns as $column) {
			$html .= '<th scope="col">' . $column . '</th>';
		}

		$html .= '</tr></thead>';
	}

	$html .= '<tbody>';

	foreach ($rows as $row_index => $row) {
		$row_class = '';
		$row_attrs = '';

		if (isset($row['_search'])) {
			$row_attrs .= ' data-search-text="' . html_encode(strtolower((string) $row['_search'])) . '"';
			unset($row['_search']);
		}

		if (isset($row['_class'])) {
			$row_class = ' class="' . html_encode((string) $row['_class']) . '"';
			unset($row['_class']);
		} elseif (!empty($options['row_class']) && is_callable($options['row_class'])) {
			$class = (string) $options['row_class']($row, $row_index);

			if ($class !== '') {
				$row_class = ' class="' . html_encode($class) . '"';
			}
		}

		$html .= '<tr' . $row_class . $row_attrs . '>';

		if ($variant === 'info') {
			$html .= '<th scope="row">' . ($row[0] ?? '') . '</th>';
			$html .= '<td>' . ($row[1] ?? '&mdash;') . '</td>';
		} else {
			foreach ($row as $index => $cell) {
				$label = $columns[$index] ?? '';
				$attrs = $label !== '' ? ' data-label="' . html_encode(strip_tags($label)) . '"' : '';
				$html .= '<td' . $attrs . '>' . $cell . '</td>';
			}
		}

		$html .= '</tr>';
	}

	return $html . '</tbody></table></div></div>';
}

/**
 * Définit les largeurs de colonnes pour les tableaux modules.
 */
function admin_modules_table_colgroup(string $layout): string
{
	if ($layout === 'info') {
		return '<colgroup><col class="admin-modules-col-label"><col class="admin-modules-col-value"></colgroup>';
	}

	if ($layout === 'reports') {
		return '<colgroup>'
			. '<col class="admin-reports-col-content">'
			. '<col class="admin-reports-col-reporter">'
			. '<col class="admin-reports-col-reason">'
			. '<col class="admin-reports-col-actions">'
			. '</colgroup>';
	}

	if ($layout === 'servers') {
		return '<colgroup>'
			. '<col class="admin-servers-col-item">'
			. '<col class="admin-servers-col-type">'
			. '<col class="admin-servers-col-polling">'
			. '<col class="admin-servers-col-actions">'
			. '</colgroup>';
	}

	if ($layout === 'security') {
		return '<colgroup>'
			. '<col class="admin-security-col-rule">'
			. '<col class="admin-security-col-reason">'
			. '<col class="admin-security-col-expires">'
			. '<col class="admin-security-col-actions">'
			. '</colgroup>';
	}

	if ($layout === 'downloads') {
		return '<colgroup>'
			. '<col class="admin-downloads-col-file">'
			. '<col class="admin-downloads-col-date">'
			. '<col class="admin-downloads-col-size">'
			. '<col class="admin-downloads-col-hits">'
			. '<col class="admin-downloads-col-actions">'
			. '</colgroup>';
	}

	if ($layout === 'pages') {
		return '<colgroup>'
			. '<col class="admin-pages-col-item">'
			. '<col class="admin-pages-col-status">'
			. '<col class="admin-pages-col-comments">'
			. '<col class="admin-pages-col-views">'
			. '<col class="admin-pages-col-actions">'
			. '</colgroup>';
	}

	if ($layout === 'gallery') {
		return '<colgroup>'
			. '<col class="admin-gallery-col-preview">'
			. '<col class="admin-gallery-col-details">'
			. '<col class="admin-gallery-col-user">'
			. '<col class="admin-gallery-col-date">'
			. '<col class="admin-gallery-col-origin">'
			. '<col class="admin-gallery-col-views">'
			. '<col class="admin-gallery-col-actions">'
			. '</colgroup>';
	}

	if ($layout === 'page_edit') {
		return '<colgroup>'
			. '<col class="admin-page-edit-col-compare">'
			. '<col class="admin-page-edit-col-revision">'
			. '<col class="admin-page-edit-col-date">'
			. '<col class="admin-page-edit-col-status">'
			. '<col class="admin-page-edit-col-author">'
			. '<col class="admin-page-edit-col-size">'
			. '<col class="admin-page-edit-col-attach">'
			. '<col class="admin-page-edit-col-actions">'
			. '</colgroup>';
	}

	if ($layout === 'comments') {
		return '<colgroup>'
			. '<col class="admin-comments-col-message">'
			. '<col class="admin-comments-col-user">'
			. '<col class="admin-comments-col-state">'
			. '<col class="admin-comments-col-actions">'
			. '</colgroup>';
	}

	if ($layout === 'users') {
		return '<colgroup>'
			. '<col class="admin-users-col-user">'
			. '<col class="admin-users-col-email">'
			. '<col class="admin-users-col-group">'
			. '<col class="admin-users-col-register">'
			. '<col class="admin-users-col-country">'
			. '<col class="admin-users-col-ip">'
			. '<col class="admin-users-col-actions">'
			. '</colgroup>';
	}

	if ($layout === 'user_view_messages') {
		return '<colgroup>'
			. '<col class="admin-user-view-col-date">'
			. '<col class="admin-user-view-col-user">'
			. '<col class="admin-user-view-col-user">'
			. '<col class="admin-user-view-col-subject">'
			. '<col class="admin-user-view-col-content">'
			. '</colgroup>';
	}

	if ($layout === 'user_view_history') {
		return '<colgroup>'
			. '<col class="admin-user-view-col-date">'
			. '<col class="admin-user-view-col-user">'
			. '<col class="admin-user-view-col-user">'
			. '<col class="admin-user-view-col-ip">'
			. '<col class="admin-user-view-col-content">'
			. '</colgroup>';
	}

	if ($layout === 'groups') {
		return '<colgroup>'
			. '<col class="admin-groups-col-name">'
			. '<col class="admin-groups-col-users">'
			. '</colgroup>';
	}

	return '<colgroup>'
		. '<col class="admin-modules-col-item">'
		. '<col class="admin-modules-col-meta">'
		. '<col class="admin-modules-col-version">'
		. '<col class="admin-modules-col-status">'
		. '<col class="admin-modules-col-actions">'
		. '</colgroup>';
}

/**
 * Colonnes standard pour les tableaux modules (catalogue / installé).
 *
 * @return array<int, string>
 */
function admin_modules_table_columns(): array
{
	return [
		html_encode(__('admin/modules.table_name')),
		html_encode(__('admin/modules.table_author')),
		html_encode(__('admin/modules.table_plugin_version')),
		html_encode(__('admin/modules.table_cms_version')),
		html_encode(__('admin/modules.table_action')),
	];
}

/**
 * Colonnes pour les composants installés.
 *
 * @return array<int, string>
 */
function admin_modules_installed_columns(): array
{
	return [
		html_encode(__('admin/modules.table_name')),
		html_encode(__('admin/modules.table_author')),
		html_encode(__('admin/modules.table_version_installed')),
		html_encode(__('admin/modules.table_type')),
		html_encode(__('admin/modules.table_action')),
	];
}

/**
 * Colonnes pour les packs de langue.
 *
 * @return array<int, string>
 */
function admin_modules_lang_columns(): array
{
	return [
		html_encode(__('admin/modules.table_name')),
		html_encode(__('admin/modules.table_author')),
		html_encode(__('admin/modules.table_progress')),
		html_encode(__('admin/modules.table_cms_version')),
		html_encode(__('admin/modules.table_action')),
	];
}

/**
 * Construit la cellule visuelle principale d'une ligne modules.
 *
 * @param array{description?: string, url?: string, icon?: string, accent?: string, avatar_html?: string} $options
 */
function admin_modules_item_cell(string $name, array $options = []): string
{
	$description = $options['description'] ?? '';
	$url = $options['url'] ?? '';
	$icon = $options['icon'] ?? 'fa-puzzle-piece';
	$accent = $options['accent'] ?? 'primary';
	$avatar_html = $options['avatar_html'] ?? '';

	$html = '<div class="admin-modules-item">';

	if ($avatar_html !== '') {
		$html .= '<span class="admin-modules-item__avatar admin-modules-item__avatar--custom">' . $avatar_html . '</span>';
	} else {
		$html .= '<span class="admin-modules-item__avatar admin-modules-item__avatar--' . html_encode($accent) . '">';
		$html .= '<i class="fa-solid ' . html_encode($icon) . '" aria-hidden="true"></i></span>';
	}

	$html .= '<span class="admin-modules-item__content">';

	if ($url !== '') {
		$html .= '<a href="' . html_encode($url) . '" target="_blank" rel="noopener noreferrer" class="admin-modules-item__link">' . html_encode($name) . '</a>';
	} else {
		$html .= '<span class="admin-modules-item__title">' . html_encode($name) . '</span>';
	}

	if ($description !== '') {
		$html .= '<span class="admin-modules-item__desc">' . html_encode($description) . '</span>';
	}

	return $html . '</span></div>';
}

/**
 * Affiche une valeur meta ou un tiret discret.
 */
function admin_modules_meta_cell(string $value): string
{
	$value = trim($value);

	if ($value === '' || $value === '—') {
		return '<span class="admin-modules-muted">&mdash;</span>';
	}

	return '<span class="admin-modules-meta">' . html_encode($value) . '</span>';
}

/**
 * Affiche une puce de version.
 */
function admin_modules_version_cell(?string $version, string $extra_html = '', bool $prefix_v = true): string
{
	if ($version === null || $version === '') {
		return '<span class="admin-modules-muted">&mdash;</span>';
	}

	$label = ($prefix_v ? 'v' : '') . html_encode($version);
	$html = '<span class="admin-modules-chip">' . $label . '</span>';

	if ($extra_html !== '') {
		$html .= '<div class="admin-modules-table__update">' . $extra_html . '</div>';
	}

	return $html;
}

/**
 * Affiche un badge de statut installé.
 */
function admin_modules_status_cell(bool $active): string
{
	$class = $active ? 'is-active' : 'is-inactive';
	$label = $active ? __('admin/modules.badge_active') : __('admin/modules.badge_inactive');

	return '<span class="admin-modules-status admin-modules-status--' . $class . '">'
		. '<span class="admin-modules-status__dot" aria-hidden="true"></span>'
		. html_encode($label)
		. '</span>';
}

/**
 * Regroupe des boutons d'action compacts.
 */
function admin_modules_actions_group(string $content): string
{
	if ($content === '') {
		return '<span class="admin-modules-muted">&mdash;</span>';
	}

	return '<div class="admin-modules-actions" role="group">' . $content . '</div>';
}

function admin_modules_action_link(string $href, string $icon, string $label, string $class = 'btn-outline-secondary'): string
{
	$external = $href !== '#' && preg_match('#^https?://#i', $href);
	$attrs = $external ? ' target="_blank" rel="noopener noreferrer"' : '';

	return '<a href="' . html_encode($href) . '" class="btn ' . html_encode($class) . '" title="' . html_encode($label) . '" aria-label="' . html_encode($label) . '"' . $attrs . '>'
		. '<i class="' . html_encode($icon) . '" aria-hidden="true"></i></a>';
}

function admin_modules_action_button(string $name, string $value, string $icon, string $label, string $class = 'btn-outline-secondary', string $extra_attrs = ''): string
{
	return '<button type="submit" name="' . html_encode($name) . '" value="' . html_encode($value) . '" class="btn ' . html_encode($class) . '" title="' . html_encode($label) . '" aria-label="' . html_encode($label) . '" ' . $extra_attrs . '>'
		. '<i class="' . html_encode($icon) . '" aria-hidden="true"></i></button>';
}

/**
 * Construit la cellule actions pour un tableau modules.
 */
function admin_modules_table_actions_cell(string $actions): string
{
	return admin_modules_actions_group($actions);
}

/**
 * Construit le HTML des actions pour un composant installé.
 */
function admin_modules_installed_actions(array $item, string $delete_confirm): string
{
	$html = '';

	if (!empty($item['active'])) {
		if (!empty($item['has_settings'])) {
			$html .= admin_modules_action_link(
				'?page=modules&plugin=' . rawurlencode($item['id']),
				'fa-solid fa-gear',
				__('admin/modules.btn_settings'),
				'btn-outline-primary'
			);
		}

		$html .= admin_modules_action_button(
			'deactivate_plugin',
			$item['id'],
			'fa-solid fa-power-off',
			__('admin/modules.btn_disabling'),
			'btn-outline-warning'
		);
	} else {
		$html .= admin_modules_action_button(
			'activate_plugin',
			$item['id'],
			'fa-solid fa-check',
			__('admin/modules.btn_enabling'),
			'btn-success'
		);
		$html .= admin_modules_action_button(
			'delete_plugin',
			$item['id'],
			'fa-solid fa-trash-can',
			__('admin/modules.btn_delete_'),
			'btn-outline-danger',
			'onclick="return confirm(\'' . $delete_confirm . '\');"'
		);
	}

	return $html;
}

/**
 * Construit une ligne de tableau pour un composant installé.
 *
 * @return array<int, string>
 */
function admin_modules_installed_table_row(array $item, string $delete_confirm): array
{
	$is_theme = ($item['type'] ?? '') === 'theme';

	return [
		admin_modules_item_cell($item['name'], [
			'description' => $item['description'] ?? '',
			'url' => $item['homepage'] ?? '',
			'icon' => $is_theme ? 'fa-palette' : 'fa-puzzle-piece',
			'accent' => $is_theme ? 'info' : 'primary',
		]),
		admin_modules_meta_cell($item['author'] ?? ''),
		admin_modules_version_cell($item['version'] ?? null, $item['update'] ?? '', true),
		admin_modules_status_cell(!empty($item['active'])),
		admin_modules_table_actions_cell(admin_modules_installed_actions($item, $delete_confirm)),
	];
}

/**
 * Construit une ligne de tableau pour un élément du catalogue.
 *
 * @return array<int, string>
 */
function admin_modules_catalog_table_row(object $item, bool $extended = true, string $accent = 'primary', string $icon = 'fa-puzzle-piece'): array
{
	return [
		admin_modules_item_cell($item->name ?? '', [
			'description' => $item->description ?? '',
			'icon' => $icon,
			'accent' => $accent,
		]),
		admin_modules_meta_cell($item->author ?? ''),
		admin_modules_version_cell($item->plugin_version ?? null),
		admin_modules_version_cell($item->cms_version ?? null, '', false),
		admin_modules_table_actions_cell(admin_modules_catalog_actions($item, $extended)),
	];
}

/**
 * Construit une ligne de tableau pour un pack de langue.
 *
 * @return array<int, string>
 */
function admin_modules_lang_table_row(object $item): array
{
	$progress = (int) ($item->progress ?? 0);
	$progress_class = $progress >= 100 ? 'is-complete' : 'is-progress';

	$progress_cell = '<div class="admin-modules-progress admin-modules-progress--' . $progress_class . '">';
	$progress_cell .= '<div class="admin-modules-progress__track"><span style="width:' . $progress . '%"></span></div>';
	$progress_cell .= '<span class="admin-modules-progress__value">' . $progress . '%</span></div>';

	return [
		admin_modules_item_cell($item->name ?? '', [
			'avatar_html' => flag_icon_html($item->flag ?? '', @COUNTRIES[$item->flag ?? ''] ?? ($item->name ?? '')),
			'accent' => 'success',
		]),
		admin_modules_meta_cell($item->author ?? ''),
		$progress_cell,
		admin_modules_version_cell($item->cms_version ?? null, '', false),
		admin_modules_table_actions_cell(admin_modules_catalog_actions($item, false)),
	];
}

/**
 * Affiche les composants installés dans des tableaux.
 */
function admin_modules_installed_board(array $themes, array $plugins, string $delete_confirm): string
{
	if (!$themes && !$plugins) {
		return admin_modules_empty(__('admin/modules.empty_list'), 'fa-inbox');
	}

	$html = '<form method="post" class="admin-modules-installed-form">';
	$columns = admin_modules_installed_columns();

	if ($themes) {
		$rows = [];

		foreach ($themes as $item) {
			$rows[] = admin_modules_installed_table_row($item, $delete_confirm);
		}

		$html .= admin_modules_table($columns, $rows, [
			'caption' => __('admin/modules.section_themes'),
			'icon' => 'fa-solid fa-palette',
			'accent' => 'info',
			'layout' => 'installed',
		]);
	}

	if ($plugins) {
		$rows = [];

		foreach ($plugins as $item) {
			$rows[] = admin_modules_installed_table_row($item, $delete_confirm);
		}

		$html .= admin_modules_table($columns, $rows, [
			'caption' => __('admin/modules.section_modules'),
			'icon' => 'fa-solid fa-puzzle-piece',
			'accent' => 'primary',
			'layout' => 'installed',
			'class' => $themes ? 'admin-modules-table-wrap--spaced' : '',
		]);
	}

	return $html . '</form>';
}

/**
 * Construit les actions pour un élément du catalogue.
 */
function admin_modules_catalog_actions(object $item, bool $extended = true): string
{
	$html = '';

	if (!empty($item->download)) {
		$html .= admin_modules_action_link(
			$item->download,
			'fa-solid fa-download',
			__('admin/modules.btn_download'),
			'btn-primary'
		);
		$html .= admin_modules_action_link('#', 'fa-solid fa-microchip', __('admin/modules.btn_install'));
	}

	if ($extended && !empty($item->preview)) {
		$html .= admin_modules_action_link(
			$item->preview,
			'fa-regular fa-images',
			__('admin/modules.btn_preview')
		);
	}

	if ($extended && !empty($item->website)) {
		$html .= admin_modules_action_link(
			$item->website,
			'fa-solid fa-earth-americas',
			__('admin/modules.btn_website')
		);
	}

	return $html;
}

/**
 * Extrait l'icône Font Awesome sans le préfixe de style.
 */
function admin_modules_panel_icon(string $icon): string
{
	$icon = trim($icon);
	$icon = preg_replace('/\b(fa-solid|fa-regular|fa-brands|fa|fas|far|fab)\b/', '', $icon);

	return trim(preg_replace('/\s+/', ' ', $icon)) ?: 'fa-puzzle-piece';
}

/**
 * Affiche un catalogue dans un tableau.
 *
 * @param array<int, object> $items
 */
function admin_modules_catalog_board(array $items, array $panel): string
{
	$rows = [];
	$accent = $panel['accent'] ?? 'primary';
	$icon = admin_modules_panel_icon($panel['icon'] ?? 'fa-solid fa-puzzle-piece');

	foreach ($items as $item) {
		$rows[] = admin_modules_catalog_table_row($item, $panel['extended'] ?? true, $accent, $icon);
	}

	return admin_modules_table(admin_modules_table_columns(), $rows, [
		'empty' => $panel['empty'] ?? __('admin/modules.empty_catalog'),
		'empty_icon' => 'fa-store',
		'layout' => 'catalog',
	]);
}

/**
 * Affiche les packs de langue dans un tableau.
 *
 * @param array<int, object> $items
 */
function admin_modules_lang_board(array $items): string
{
	$rows = [];

	foreach ($items as $item) {
		$rows[] = admin_modules_lang_table_row($item);
	}

	return admin_modules_table(admin_modules_lang_columns(), $rows, [
		'empty' => __('admin/modules.empty_languages'),
		'empty_icon' => 'fa-language',
		'layout' => 'lang',
	]);
}

/**
 * Affiche le panneau d'import de module ZIP.
 */
function admin_modules_import_board(): string
{
	if (!class_exists('ZipArchive')) {
		return admin_status_bar(
			'danger',
			'<i class="fa-solid fa-circle-exclamation me-1" aria-hidden="true"></i> ' . __('admin/modules.import_zip_missing')
		);
	}

	$html = '<div class="admin-modules-upload">';
	$html .= '<div class="admin-modules-upload__visual">';
	$html .= '<span class="admin-modules-upload__icon"><i class="fa-solid fa-cloud-arrow-up" aria-hidden="true"></i></span>';
	$html .= '<h3 class="admin-modules-upload__title">' . html_encode(__('admin/modules.header_form')) . '</h3>';
	$html .= '<p class="admin-modules-upload__hint">' . __('admin/modules.import_desc') . '</p>';
	$html .= '<p class="admin-modules-upload__meta">' . __('admin/modules.import_hint') . '</p>';
	$html .= '</div>';
	$html .= '<form method="post" enctype="multipart/form-data" class="admin-modules-upload__form">';
	$html .= '<label class="admin-modules-upload__field">';
	$html .= '<span class="admin-modules-upload__field-label">' . html_encode(__('admin/modules.header_form_btn_upload')) . '</span>';
	$html .= '<input type="file" name="plugin_file" class="form-control" accept=".zip,application/zip" required>';
	$html .= '</label>';
	$html .= '<button type="submit" class="btn btn-primary">';
	$html .= '<i class="fa-solid fa-upload me-1" aria-hidden="true"></i>' . html_encode(__('admin/modules.header_form_btn_upload'));
	$html .= '</button></form></div>';

	return $html;
}

/**
 * Affiche la vue de configuration d'un module.
 */
function admin_modules_config_board(object $plugin): string
{
	$info = $plugin->infos;

	$html = '<div class="admin-modules-config__toolbar">';
	$html .= '<a href="?page=modules" class="btn btn-outline-secondary btn-sm">';
	$html .= '<i class="fa-solid fa-arrow-left me-1" aria-hidden="true"></i>' . html_encode(__('admin/modules.back_to_list'));
	$html .= '</a></div>';

	$html .= admin_modules_item_cell($info->name, [
		'description' => $info->description ?? '',
		'icon' => 'fa-gears',
		'accent' => 'primary',
	]);

	$meta_rows = [];

	if ($info->getAuthors()) {
		$meta_rows[] = [
			html_encode(__('admin/modules.table_author')),
			admin_modules_meta_cell(implode(', ', array_map('strval', $info->getAuthors()))),
		];
	}

	$meta_rows[] = [
		html_encode(__('admin/modules.table_version_installed')),
		admin_modules_version_cell($info->version),
	];

	$html .= admin_modules_table([], $meta_rows, [
		'variant' => 'info',
		'caption' => __('admin/modules.config_title') . ' ' . $info->name,
		'icon' => 'fa-solid fa-gears',
		'accent' => 'primary',
		'class' => 'admin-modules-table-wrap--info',
		'layout' => 'info',
	]);

	if ($plugin->settings) {
		$html .= '<div class="admin-modules-config-form">' . settings_form($plugin->settings) . '</div>';
	}

	return $html;
}

/**
 * Prépare les données d'un composant installé.
 */
function admin_modules_installed_item(string $plugin_id, object $module, array $updates): array
{
	return [
		'id' => $plugin_id,
		'name' => $module->name,
		'description' => $module->description,
		'author' => implode(', ', array_map('strval', $module->getAuthors())),
		'version' => $module->version,
		'update' => $updates[$plugin_id]['content'] ?? '',
		'active' => (bool) App::getModule($plugin_id),
		'has_settings' => (bool) $module->settings,
		'homepage' => $module->homepage ?? '',
		'type' => ($module->exports[0] ?? 'plugin') === 'theme' ? 'theme' : 'plugin',
	];
}

/**
 * Navigation de la page serveurs admin.
 *
 * @param array<string, array{label: string, icon?: string}> $tabs
 */
function admin_servers_nav(array $tabs, string $active): string
{
	return admin_tabs($tabs, [
		'active' => $active,
		'type' => 'bootstrap',
		'aria_label' => __('admin/servers.main_title'),
	]);
}

function admin_servers_tab_open(string $id, bool $active): string
{
	return admin_tab_pane_open($id, $active, 'admin-servers-board__pane');
}

function admin_servers_tab_close(): string
{
	return '</div>';
}

function admin_servers_empty(string $message, string $icon = 'fa-server'): string
{
	return admin_settings_empty($message, $icon);
}

/**
 * @param array<int, array<string, mixed>> $servers
 * @param array<string, string> $server_types
 */
function admin_servers_build_stats(array $servers, array $server_types): array
{
	$polling_active = 0;
	$types_used = [];

	foreach ($servers as $server) {
		if (!empty($server['poll_interval'])) {
			$polling_active++;
		}

		$type = (string) ($server['type'] ?? '');

		if ($type !== '' && isset($server_types[$type])) {
			$types_used[$type] = true;
		}
	}

	return [
		['icon' => 'fa-solid fa-server', 'value' => (string) count($servers), 'label' => __('admin/servers.stats_total'), 'variant' => 'primary'],
		['icon' => 'fa-solid fa-arrows-rotate', 'value' => (string) $polling_active, 'label' => __('admin/servers.stats_polling'), 'variant' => 'success'],
		['icon' => 'fa-solid fa-layer-group', 'value' => (string) count($types_used), 'label' => __('admin/servers.stats_types'), 'variant' => 'info'],
		['icon' => 'fa-solid fa-power-off', 'value' => (string) (count($servers) - $polling_active), 'label' => __('admin/servers.stats_offline'), 'variant' => 'warning'],
	];
}

/**
 * @return array<int, string>
 */
function admin_servers_columns(): array
{
	return [
		html_encode(__('admin/general.server_name')),
		html_encode(__('admin/general.server_type')),
		html_encode(__('admin/servers.table_polling')),
		html_encode(__('admin/modules.table_action')),
	];
}

function admin_servers_polling_cell($poll_interval): string
{
	$poll_interval = (int) $poll_interval;

	if ($poll_interval <= 0) {
		return '<span class="admin-modules-status admin-modules-status--is-inactive">'
			. '<span class="admin-modules-status__dot" aria-hidden="true"></span>'
			. html_encode(__('admin/servers.polling_off'))
			. '</span>';
	}

	return '<span class="admin-modules-chip">'
		. html_encode(__('admin/servers.polling_seconds', ['%n%' => (string) $poll_interval]))
		. '</span>';
}

/**
 * @param array<string, mixed> $server
 * @param array<string, string> $server_types
 */
function admin_servers_item_cell(array $server, array $server_types): string
{
	$type = (string) ($server['type'] ?? '');
	$icon_src = App::getAsset('/img/servers/' . rawurlencode($type) . '.png');
	$avatar = '<img src="' . html_encode($icon_src) . '" width="28" height="28" alt="" loading="lazy">';
	$name = (string) ($server['name'] ?? '');
	$address = (string) ($server['address'] ?? '');
	$url = App::getURL('server', (int) ($server['id'] ?? 0));

	$html = '<div class="admin-modules-item">';
	$html .= '<span class="admin-modules-item__avatar admin-modules-item__avatar--custom">' . $avatar . '</span>';
	$html .= '<span class="admin-modules-item__content">';
	$html .= '<a href="' . html_encode($url) . '" class="admin-modules-item__link">' . html_encode($name) . '</a>';

	if ($address !== '') {
		$html .= '<span class="admin-modules-item__desc">' . html_encode($address) . '</span>';
	}

	$html .= '</span></div>';

	return $html;
}

function admin_servers_type_cell(array $server, array $server_types): string
{
	$type = (string) ($server['type'] ?? '');
	$label = $server_types[$type] ?? $type;

	if ($label === '' || $label === '--------') {
		return '<span class="admin-modules-muted">&mdash;</span>';
	}

	return '<span class="admin-modules-chip admin-servers-type-chip">' . html_encode($label) . '</span>';
}

/**
 * @param array<string, mixed> $server
 */
function admin_servers_actions(array $server, string $delete_confirm): string
{
	return admin_modules_actions_group(
		admin_modules_action_button(
			'edit_serv',
			(string) ($server['id'] ?? ''),
			'fa-solid fa-pencil',
			__('admin/general.server_btn_title_edit'),
			'btn-outline-primary'
		)
		. admin_modules_action_button(
			'del_serv',
			(string) ($server['id'] ?? ''),
			'fa-regular fa-trash-can',
			__('admin/general.server_btn_title_delete'),
			'btn-outline-danger',
			'onclick="return confirm(\'' . $delete_confirm . '\');"'
		)
	);
}

/**
 * @param array<string, mixed> $server
 * @param array<string, string> $server_types
 * @return array<int, string>
 */
function admin_servers_table_row(array $server, array $server_types, string $delete_confirm): array
{
	return [
		admin_servers_item_cell($server, $server_types),
		admin_servers_type_cell($server, $server_types),
		admin_servers_polling_cell($server['poll_interval'] ?? 0),
		admin_modules_table_actions_cell(admin_servers_actions($server, $delete_confirm)),
	];
}

/**
 * @param array<int, array<string, mixed>> $servers
 * @param array<string, string> $server_types
 */
function admin_servers_list_board(array $servers, array $server_types, string $delete_confirm): string
{
	if (!$servers) {
		return admin_servers_empty(__('admin/general.server_none'));
	}

	$rows = [];

	foreach ($servers as $server) {
		$rows[] = admin_servers_table_row($server, $server_types, $delete_confirm);
	}

	$html = '<form method="post" class="admin-servers-list-form">';
	$html .= admin_modules_table(admin_servers_columns(), $rows, [
		'caption' => __('admin/general.server_list_title'),
		'icon' => 'fa-solid fa-server',
		'accent' => 'primary',
		'layout' => 'servers',
		'class' => 'admin-servers-table-wrap',
	]);

	return $html . '</form>';
}

/**
 * @param array<string, mixed> $server
 * @param array<string, string> $server_types
 */
function admin_servers_form_connection_fields(array $server, array $server_types): string
{
	$html = admin_form_field_row(
		__('admin/general.server_name'),
		'<input class="form-control" id="server-name" name="name" type="text" value="' . html_encode((string) ($server['name'] ?? '')) . '" required>',
		['for' => 'server-name']
	);

	$html .= admin_form_field_row(
		__('admin/general.server_type'),
		Widgets::select('type', $server_types, (string) ($server['type'] ?? ''), true, 'class="form-control" id="server-type"'),
		['for' => 'server-type']
	);

	$html .= admin_form_field_row(
		__('admin/general.server_ip'),
		'<input class="form-control" id="server-address" name="address" type="text" value="' . html_encode((string) ($server['address'] ?? '')) . '" required>',
		['for' => 'server-address', 'hint' => __('admin/general.server_title_ph')]
	);

	$html .= admin_form_field_row(
		__('admin/general.server_password'),
		'<input class="form-control" id="server-password" name="password" type="text" value="' . html_encode((string) ($server['password'] ?? '')) . '" autocomplete="off">',
		['for' => 'server-password', 'hint' => __('admin/servers.password_hint')]
	);

	return $html;
}

/**
 * @param array<string, mixed> $server
 */
function admin_servers_form_polling_fields(array $server): string
{
	return admin_form_field_row(
		__('admin/servers.table_polling'),
		'<input class="form-control" id="server-poll-interval" name="poll_interval" type="number" min="0" step="1" value="' . html_encode((string) (int) ($server['poll_interval'] ?? 0)) . '">',
		['for' => 'server-poll-interval']
	);
}

/**
 * @param array<string, mixed> $server
 * @param array<string, string> $server_types
 */
function admin_servers_form_board(array $server, array $server_types): string
{
	$is_edit = !empty($server['id']);
	$submit_label = $is_edit ? __('admin/general.server_btn_edit_save') : __('admin/general.server_btn_save');

	$html = '<div class="admin-servers-form admin-servers-form--config">';

	if ($is_edit) {
		$html .= admin_servers_item_cell($server, $server_types);
	}

	$html .= '<form method="post" role="form" class="form-horizontal admin-settings-grouped-form admin-servers-form__form" id="admin-servers-form">';
	$html .= '<section class="admin-settings-section admin-settings-section--grouped">';
	$html .= '<div class="admin-settings-section__body admin-settings-section__body--grouped">';

	$html .= '<div class="admin-settings-subsection">';
	$html .= '<header class="admin-settings-subsection__header">';
	$html .= '<span class="admin-settings-subsection__icon"><i class="fa-solid fa-plug" aria-hidden="true"></i></span>';
	$html .= '<div class="admin-settings-subsection__heading">';
	$html .= '<h3 class="admin-settings-subsection__title">' . html_encode(__('admin/servers.form_connection')) . '</h3>';

	if (!$is_edit) {
		$html .= '<p class="admin-settings-subsection__desc">' . html_encode(__('admin/servers.form_intro')) . '</p>';
	}

	$html .= '</div></header>';
	$html .= '<div class="admin-settings-subsection__body">';
	$html .= admin_servers_form_connection_fields($server, $server_types);
	$html .= '</div></div>';

	$html .= '<hr class="admin-settings-subsection__divider">';

	$html .= '<div class="admin-settings-subsection">';
	$html .= '<header class="admin-settings-subsection__header">';
	$html .= '<span class="admin-settings-subsection__icon"><i class="fa-solid fa-arrows-rotate" aria-hidden="true"></i></span>';
	$html .= '<div class="admin-settings-subsection__heading">';
	$html .= '<h3 class="admin-settings-subsection__title">' . html_encode(__('admin/servers.form_polling')) . '</h3>';
	$html .= '<p class="admin-settings-subsection__desc">' . html_encode(__('admin/servers.form_polling_hint')) . '</p>';
	$html .= '</div></header>';
	$html .= '<div class="admin-settings-subsection__body">';
	$html .= admin_servers_form_polling_fields($server);
	$html .= '</div></div>';

	$html .= '</div>';
	$html .= '<footer class="admin-settings-section__footer">';
	$html .= '<div class="text-center">';
	$html .= '<input type="hidden" name="id" value="' . html_encode((string) (int) ($server['id'] ?? 0)) . '">';
	$html .= '<button class="btn btn-primary" name="save" value="1" type="submit">';
	$html .= '<i class="fa-solid fa-floppy-disk me-1" aria-hidden="true"></i>' . html_encode($submit_label);
	$html .= '</button> ';
	$html .= '<a class="btn btn-outline-secondary" href="?page=servers&amp;tab=list">' . html_encode(__('admin/menu.btn_cancel')) . '</a>';
	$html .= '</div></footer>';
	$html .= '</section></form></div>';

	return $html;
}

function admin_security_nav(array $tabs, string $active): string
{
	return admin_tabs($tabs, [
		'active' => $active,
		'type' => 'bootstrap',
		'aria_label' => __('admin/menu.sub_security'),
	]);
}

function admin_security_tab_open(string $id, bool $active): string
{
	return admin_tab_pane_open($id, $active, 'admin-security-board__pane');
}

function admin_security_tab_close(): string
{
	return '</div>';
}

function admin_security_empty(string $message, string $icon = 'fa-shield-alt'): string
{
	return admin_settings_empty($message, $icon);
}

/**
 * @param array<int, array<string, mixed>> $banlist
 * @return array<int, array{icon: string, value: string, label: string, variant: string}>
 */
function admin_security_build_stats(array $banlist): array
{
	$counts = [
		'username' => 0,
		'email' => 0,
		'ip' => 0,
		'country' => 0,
	];

	foreach ($banlist as $ban) {
		$type = (string) ($ban['type'] ?? '');

		if (isset($counts[$type])) {
			$counts[$type]++;
		}
	}

	return [
		['icon' => 'fa fa-shield-alt', 'value' => (string) count($banlist), 'label' => __('admin/security.stats_total'), 'variant' => 'primary'],
		['icon' => 'fa fa-user', 'value' => (string) $counts['username'], 'label' => __('admin/security.stats_username'), 'variant' => 'danger'],
		['icon' => 'fa fa-network-wired', 'value' => (string) $counts['ip'], 'label' => __('admin/security.stats_ip'), 'variant' => 'warning'],
		['icon' => 'fa fa-envelope', 'value' => (string) ($counts['email'] + $counts['country']), 'label' => __('admin/security.stats_other'), 'variant' => 'info'],
	];
}

/**
 * @return array{label: string, icon: string, accent: string}
 */
function admin_security_type_meta(string $type): array
{
	static $map = [
		'username' => ['label' => 'admin/security.select_username', 'icon' => 'fa-user', 'accent' => 'danger'],
		'email' => ['label' => 'admin/security.select_email', 'icon' => 'fa-envelope', 'accent' => 'info'],
		'ip' => ['label' => 'admin/security.select_ip', 'icon' => 'fa-network-wired', 'accent' => 'warning'],
		'country' => ['label' => 'admin/security.table_country', 'icon' => 'fa-globe', 'accent' => 'primary'],
	];

	return $map[$type] ?? ['label' => '', 'icon' => 'fa-ban', 'accent' => 'secondary'];
}

function admin_security_type_label(string $type): string
{
	$meta = admin_security_type_meta($type);

	return $meta['label'] !== '' ? __($meta['label']) : ucfirst($type);
}

/**
 * @return array<int, string>
 */
function admin_security_columns(): array
{
	return [
		html_encode(__('admin/security.table_rule')),
		html_encode(__('admin/security.table_reason')),
		html_encode(__('admin/security.table_expiration')),
		html_encode(__('admin/modules.table_action')),
	];
}

/**
 * @param array<string, mixed> $ban
 * @param array<string, string> $types
 */
function admin_security_rule_cell(array $ban, array $types): string
{
	$type = (string) ($ban['type'] ?? '');
	$meta = admin_security_type_meta($type);
	$rule = html_encode(str_replace(['%', '\_'], ['*', '_'], (string) ($ban['rule'] ?? '')));
	$type_label = $types[$type] ?? admin_security_type_label($type);

	return admin_modules_item_cell($rule, [
		'description' => $type_label,
		'icon' => $meta['icon'],
		'accent' => $meta['accent'],
	]);
}

/**
 * @param array<string, mixed> $ban
 */
function admin_security_reason_cell(array $ban): string
{
	$reason = trim((string) ($ban['reason'] ?? ''));

	if ($reason === '') {
		return '<span class="admin-modules-muted">&mdash;</span>';
	}

	return html_encode($reason);
}

/**
 * @param array<string, mixed> $ban
 */
function admin_security_expires_cell(array $ban): string
{
	$expires = (int) ($ban['expires'] ?? 0);
	$label = Format::today($expires);
	$is_permanent = $expires <= 0;
	$is_expired = $expires > 0 && $expires < time();

	if ($is_permanent) {
		return '<span class="admin-modules-chip admin-security-expires-chip admin-security-expires-chip--permanent">'
			. html_encode($label)
			. '</span>';
	}

	if ($is_expired) {
		return '<span class="admin-modules-status admin-modules-status--is-inactive">'
			. '<span class="admin-modules-status__dot" aria-hidden="true"></span>'
			. html_encode($label)
			. '</span>';
	}

	return '<span class="admin-modules-chip admin-security-expires-chip">' . html_encode($label) . '</span>';
}

/**
 * @param array<string, mixed> $ban
 */
function admin_security_actions(array $ban, string $delete_confirm): string
{
	return admin_modules_actions_group(
		admin_modules_action_button(
			'delete',
			(string) ($ban['id'] ?? ''),
			'far fa-trash-alt',
			__('admin/general.btn_delete'),
			'btn-outline-danger',
			'onclick="return confirm(\'' . $delete_confirm . '\');"'
		)
	);
}

/**
 * @param array<string, mixed> $ban
 * @param array<string, string> $types
 * @return array<int, string>
 */
function admin_security_table_row(array $ban, array $types, string $delete_confirm, bool $highlight = false): array
{
	$row = [
		admin_security_rule_cell($ban, $types),
		admin_security_reason_cell($ban),
		admin_security_expires_cell($ban),
		admin_modules_table_actions_cell(admin_security_actions($ban, $delete_confirm)),
	];

	if ($highlight) {
		$row['_class'] = 'admin-security-row--highlight';
	}

	return $row;
}

/**
 * @param array<int, array<string, mixed>> $banlist
 * @param array<string, string> $types
 */
function admin_security_list_board(array $banlist, array $types, string $delete_confirm, string $filter = ''): string
{
	$filter = trim($filter);
	$toolbar = '<form method="get" class="admin-security-filter" role="search">'
		. '<input type="hidden" name="page" value="security">'
		. '<input type="hidden" name="tab" value="list">'
		. '<div class="input-group input-group-sm">'
		. '<input class="form-control" name="filter" type="search" value="' . html_encode($filter) . '" placeholder="' . html_encode(__('admin/security.filter_placeholder')) . '">'
		. '<button class="btn btn-outline-secondary" type="submit" aria-label="' . html_encode(__('admin/security.filter_submit')) . '">'
		. '<i class="fas fa-search" aria-hidden="true"></i>'
		. '</button>'
		. '</div></form>';

	if (!$banlist) {
		$html = '<div class="admin-security-list-board">';

		if ($filter !== '') {
			$html .= admin_status_bar(
				'info',
				'<i class="fas fa-info-circle me-1" aria-hidden="true"></i> ' . html_encode(__('admin/security.alert_notfound'))
			);
		} else {
			$html .= admin_security_empty(__('admin/security.alert_notfound'));
		}

		return $html . '</div>';
	}

	$rows = [];
	$highlight_username = App::GET('username');
	$highlight_ip = App::GET('ip');

	foreach ($banlist as $ban) {
		$highlight = ($ban['rule'] ?? '') === $highlight_username || ($ban['rule'] ?? '') === $highlight_ip;
		$rows[] = admin_security_table_row($ban, $types, $delete_confirm, $highlight);
	}

	$html = '<form method="post" class="admin-security-list-form">';
	$html .= admin_modules_table(admin_security_columns(), $rows, [
		'caption' => __('admin/security.main_title'),
		'icon' => 'fa fa-shield-alt',
		'accent' => 'danger',
		'layout' => 'security',
		'class' => 'admin-security-table-wrap',
		'toolbar_actions' => $toolbar,
	]);

	return $html . '</form>';
}

/**
 * @param array<string, string> $types
 */
function admin_security_form_type_fields(array $types): string
{
	$html = admin_form_field_row(
		__('admin/security.table_type'),
		Widgets::select('type', $types, '', true, 'class="form-control" id="security-type"'),
		['for' => 'security-type']
	);

	$html .= '<div class="admin-security-form__help">' . __('admin/security.small_type') . '</div>';

	return $html;
}

/**
 * @param array<string, string> $types
 */
function admin_security_form_rule_fields(array $types): string
{
	$rule_value = html_encode(App::GET('username', ''));
	$rule_style = App::GET('username') ? ' style="background-color:#fff3f3;"' : '';

	$html = '<div class="admin-security-form__rule ban ban-username ban-email ban-ip">';
	$html .= admin_form_field_row(
		__('admin/security.table_rule'),
		'<input class="form-control" data-autocomplete="userlist" name="rule" id="security-rule" type="text" value="' . $rule_value . '"' . $rule_style . '>',
		['for' => 'security-rule']
	);
	$html .= '<div class="admin-security-form__help">' . __('admin/security.small_rule') . '</div>';
	$html .= '</div>';

	$html .= '<div class="admin-security-form__country ban ban-country">';
	$html .= admin_form_field_row(
		__('admin/security.table_country'),
		Widgets::select('country', COUNTRIES, '', true, 'class="form-control" id="security-country"'),
		['for' => 'security-country']
	);
	$html .= '</div>';

	return $html;
}

function admin_security_form_details_fields(): string
{
	$html = admin_form_field_row(
		__('admin/security.table_reason'),
		'<input class="form-control" id="security-reason" name="reason" type="text" value="">',
		['for' => 'security-reason']
	);

	$html .= admin_form_field_row(
		__('admin/security.table_expiration'),
		'<input class="form-control" id="security-expires" name="expires" type="text" value="+1 week">',
		['for' => 'security-expires']
	);
	$html .= '<div class="admin-security-form__help">'
		. __('admin/security.small_expiration')
		. ' : <a href="https://www.php.net/manual/fr/function.strtotime.php" target="_blank" rel="noopener">strtotime</a>.'
		. '</div>';

	return $html;
}

/**
 * @param array<string, string> $types
 */
function admin_security_form_board(array $types): string
{
	$html = '<div class="admin-security-form admin-security-form--config">';
	$html .= '<form method="post" role="form" class="form-horizontal admin-settings-grouped-form admin-security-form__form" id="admin-security-form">';
	$html .= '<section class="admin-settings-section admin-settings-section--grouped">';
	$html .= '<div class="admin-settings-section__body admin-settings-section__body--grouped">';

	$html .= '<div class="admin-settings-subsection">';
	$html .= '<header class="admin-settings-subsection__header">';
	$html .= '<span class="admin-settings-subsection__icon"><i class="fas fa-ban" aria-hidden="true"></i></span>';
	$html .= '<div class="admin-settings-subsection__heading">';
	$html .= '<h3 class="admin-settings-subsection__title">' . html_encode(__('admin/security.form_rule')) . '</h3>';
	$html .= '<p class="admin-settings-subsection__desc">' . html_encode(__('admin/security.form_intro')) . '</p>';
	$html .= '</div></header>';
	$html .= '<div class="admin-settings-subsection__body">';
	$html .= admin_security_form_type_fields($types);
	$html .= admin_security_form_rule_fields($types);
	$html .= '</div></div>';

	$html .= '<hr class="admin-settings-subsection__divider">';

	$html .= '<div class="admin-settings-subsection">';
	$html .= '<header class="admin-settings-subsection__header">';
	$html .= '<span class="admin-settings-subsection__icon"><i class="fas fa-info-circle" aria-hidden="true"></i></span>';
	$html .= '<div class="admin-settings-subsection__heading">';
	$html .= '<h3 class="admin-settings-subsection__title">' . html_encode(__('admin/security.form_details')) . '</h3>';
	$html .= '</div></header>';
	$html .= '<div class="admin-settings-subsection__body">';
	$html .= admin_security_form_details_fields();
	$html .= '</div></div>';

	$html .= '</div>';
	$html .= '<footer class="admin-settings-section__footer">';
	$html .= '<div class="text-center">';
	$html .= '<button class="btn btn-primary" name="add_menu" value="" type="submit">';
	$html .= '<i class="fas fa-save me-1" aria-hidden="true"></i>' . html_encode(__('admin/general.btn_save'));
	$html .= '</button> ';
	$html .= '<a class="btn btn-outline-secondary" href="?page=security&amp;tab=list">' . html_encode(__('admin/menu.btn_cancel')) . '</a>';
	$html .= '</div></footer>';
	$html .= '</section></form></div>';

	return $html . admin_security_form_script();
}

function admin_security_form_script(): string
{
	$ip = addslashes(App::GET('ip', ''));
	$username = addslashes(App::GET('username', ''));
	$email = addslashes(App::GET('email', ''));

	return '<script>
(function () {
	var $type = $(\'#security-type\');
	if (!$type.length) return;

	$type.on(\'change\', function () {
		var value = this.value;
		switch (value) {
			case \'ip\':
				$(\'#security-rule\').val(\'' . $ip . '\').removeAttr(\'data-autocomplete\');
				break;
			case \'username\':
				$(\'#security-rule\').val(\'' . $username . '\').attr(\'data-autocomplete\', \'userlist\');
				break;
			case \'email\':
				$(\'#security-rule\').val(\'' . $email . '\').removeAttr(\'data-autocomplete\');
				break;
		}
		$(\'.ban\').hide();
		$(\'.ban.ban-\' + value).show();
	}).trigger(\'change\');
})();
</script>';
}

/**
 * Affiche la navigation de la page pages admin (style dashboard).
 *
 * @param array<string, array{label: string, icon?: string, href?: string, external?: bool, ms_auto?: bool}> $tabs
 */
function admin_reports_type_meta(string $type): array
{
	static $map = [
		'forum' => ['label' => 'admin/general.report_cat_forum', 'icon' => 'fa-comments', 'accent' => 'primary'],
		'comment' => ['label' => 'admin/general.report_cat_comment', 'icon' => 'fa-comment', 'accent' => 'info'],
		'profile' => ['label' => 'admin/general.report_cat_profil', 'icon' => 'fa-user', 'accent' => 'warning'],
	];

	return $map[$type] ?? ['label' => '', 'icon' => 'fa-flag', 'accent' => 'secondary'];
}

function admin_forums_empty(string $message, string $icon = 'fa-comments'): string
{
	return admin_settings_empty($message, $icon);
}

/**
 * Construit les KPI de l'éditeur de forums.
 *
 * @param array<int, array<string, mixed>> $categories
 * @param array<int, array<string, mixed>> $forums
 */
function admin_forums_build_stats(array $categories, array $forums): array
{
	$total_posts = 0;
	$redirects = 0;

	foreach ($forums as $forum) {
		$total_posts += (int) ($forum['num_posts'] ?? 0);

		if (trim((string) ($forum['redirect'] ?? '')) !== '') {
			$redirects++;
		}
	}

	return [
		['icon' => 'fa-solid fa-layer-group', 'value' => (string) count($categories), 'label' => __('admin/forums.stats_categories'), 'variant' => 'primary'],
		['icon' => 'fa-solid fa-comments', 'value' => (string) count($forums), 'label' => __('admin/forums.stats_forums'), 'variant' => 'info'],
		['icon' => 'fa-solid fa-reply-all', 'value' => (string) $total_posts, 'label' => __('admin/forums.stats_posts'), 'variant' => 'success'],
		['icon' => 'fa-solid fa-arrow-up-right-from-square', 'value' => (string) $redirects, 'label' => __('admin/forums.stats_redirects'), 'variant' => 'warning'],
	];
}

/**
 * Affiche les actions d'une catégorie de forum.
 */
function admin_forums_category_actions(): string
{
	$actions = admin_modules_action_button(
		'move_category',
		'-1',
		'fa-solid fa-arrow-up',
		__('admin/forums.category_move_up'),
		'btn-outline-secondary'
	);

	$actions .= admin_modules_action_button(
		'move_category',
		'1',
		'fa-solid fa-arrow-down',
		__('admin/forums.category_move_down'),
		'btn-outline-secondary'
	);

	$actions .= admin_modules_action_button(
		'edit_category',
		'1',
		'fa-solid fa-pencil',
		__('admin/forums.table_btn_rename'),
		'btn-outline-primary'
	);

	$delete_confirm = html_encode(__('admin/forums.delete_confirm'), ENT_QUOTES);
	$actions .= admin_modules_action_button(
		'delete_category',
		'1',
		'fa-regular fa-trash-can',
		__('admin/general.btn_delete'),
		'btn-outline-danger',
		'onclick="return confirm(\'' . $delete_confirm . '\');"'
	);

	return admin_modules_table_actions_cell($actions);
}

/**
 * @param array<int|string, array<string, mixed>> $groups
 * @param array<int|string> $group_ids
 */
function admin_forums_group_badges(array $group_ids, array $groups): string
{
	$html = '';

	foreach ($group_ids as $group_id) {
		if (!isset($groups[$group_id])) {
			continue;
		}

		$group = $groups[$group_id];
		$html .= '<span class="admin-forums-permission-group group-color-' . html_encode((string) ($group['color'] ?? '')) . '">'
			. html_encode((string) ($group['name'] ?? ''))
			. '</span>';
	}

	return $html !== '' ? $html : '<span class="admin-modules-muted">&mdash;</span>';
}

/**
 * @param array<string, mixed> $forum
 * @param array<int|string, array<string, mixed>> $groups
 */
function admin_forums_permission_value(array $forum, array $groups, string $permission): string
{
	$key = 'forum.' . $permission;
	$labels = [
		'read' => ['empty' => __('admin/forums.table_read_none'), 'all' => __('admin/forums.table_read_all')],
		'write' => ['empty' => __('admin/forums.table_write_none'), 'all' => __('admin/forums.table_write_all')],
		'moderation' => ['empty' => __('admin/forums.table_mod_global'), 'all' => __('admin/forums.table_mod_global_all')],
	];

	if (!isset($forum[$key]) || !is_array($forum[$key])) {
		return '<strong>' . html_encode($labels[$permission]['empty'] ?? '') . '</strong>';
	}

	$group_ids = $forum[$key];
	$all_group_ids = array_map('strval', array_keys($groups));
	$selected_ids = array_map('strval', $group_ids);

	if (!array_diff($all_group_ids, $selected_ids)) {
		return '<strong>' . html_encode($labels[$permission]['all'] ?? '') . '</strong>';
	}

	return admin_forums_group_badges($group_ids, $groups);
}

/**
 * @param array<string, mixed> $forum
 * @param array<int|string, array<string, mixed>> $groups
 */
function admin_forums_permissions_cell(array $forum, array $groups): string
{
	$rows = [
		__('admin/forums.table_read') => admin_forums_permission_value($forum, $groups, 'read'),
		__('admin/forums.table_forum_write_title') => admin_forums_permission_value($forum, $groups, 'write'),
		__('admin/forums.table_forum_mod_title') => admin_forums_permission_value($forum, $groups, 'moderation'),
	];

	$html = '<div class="admin-forums-permissions">';

	foreach ($rows as $label => $value) {
		$html .= '<div class="admin-forums-permissions__row">'
			. '<span class="admin-forums-permissions__label">' . html_encode($label) . '</span>'
			. '<span class="admin-forums-permissions__value">' . $value . '</span>'
			. '</div>';
	}

	return $html . '</div>';
}

/**
 * @param array<string, mixed> $forum
 */
function admin_forums_item_cell(array $forum): string
{
	$name = trim((string) ($forum['name'] ?? ''));
	$description = trim(strip_tags(bbcode2html((string) ($forum['description'] ?? ''))));
	$redirect = trim((string) ($forum['redirect'] ?? ''));

	$html = admin_modules_item_cell($name !== '' ? $name : '#' . (int) ($forum['id'] ?? 0), [
		'description' => $description,
		'url' => App::getURL('forums', $forum['id'] ?? 0),
		'icon' => 'fa-comments',
		'accent' => $redirect !== '' ? 'warning' : 'primary',
	]);

	if ($redirect !== '') {
		$html .= '<span class="admin-forums-redirect">'
			. '<i class="fa-solid fa-arrow-up-right-from-square" aria-hidden="true"></i>'
			. html_encode(__('admin/forums.table_redirect')) . ' : '
			. '<strong>' . html_encode($redirect) . '</strong>'
			. '</span>';
	}

	return '<div class="admin-forums-item">' . $html . '</div>';
}

/**
 * @param array<string, mixed> $forum
 */
function admin_forums_icon_cell(array $forum): string
{
	$icon = trim((string) ($forum['icon'] ?? ''));

	if ($icon === '') {
		return '<span class="admin-modules-muted">&mdash;</span>';
	}

	return '<span class="admin-forums-icon"><i class="' . html_encode($icon) . '" aria-hidden="true"></i></span>';
}

/**
 * @param array<string, mixed> $forum
 */
function admin_forums_actions_cell(array $forum): string
{
	$delete_confirm = html_encode(__('admin/forums.delete_confirm'), ENT_QUOTES);
	$actions = admin_modules_action_button(
		'edit_forum',
		(string) ($forum['id'] ?? ''),
		'fa-solid fa-pencil',
		__('admin/forums.table_edit'),
		'btn-outline-primary'
	);

	$actions .= admin_modules_action_button(
		'del_forum',
		(string) ($forum['id'] ?? ''),
		'fa-regular fa-trash-can',
		__('admin/forums.table_delete'),
		'btn-outline-danger',
		'onclick="return confirm(\'' . $delete_confirm . '\');"'
	);

	return admin_modules_table_actions_cell($actions);
}

/**
 * @param array<string, mixed> $category
 * @param array<int|string, array<string, mixed>> $groups
 */
function admin_forums_table(array $category, array $groups): string
{
	$forums = $category['forums'] ?? [];

	if (!$forums) {
		return admin_panel_empty(__('admin/forums.table_empty_category'), 'fa-comment-dots', [
			'class' => 'admin-forums-category__empty',
			'icon_style' => 'fas',
		]);
	}

	$columns = [
		__('admin/forums.table_name'),
		__('admin/forums.table_ico'),
		__('admin/forums.table_posts'),
		__('admin/forums.table_access'),
		__('admin/forums.table_actions'),
	];

	$html = '<div class="table-responsive admin-modules-table-scroll">';
	$html .= '<table class="table admin-modules-table admin-forums-table sortable mb-0" id="reorder_forums[' . (int) ($category['id'] ?? 0) . ']">';
	$html .= '<colgroup><col class="admin-forums-col-item"><col class="admin-forums-col-icon"><col class="admin-forums-col-posts"><col class="admin-forums-col-permissions"><col class="admin-forums-col-actions"></colgroup>';
	$html .= '<thead><tr>';

	foreach ($columns as $column) {
		$html .= '<th scope="col">' . html_encode($column) . '</th>';
	}

	$html .= '</tr></thead><tbody>';

	foreach ($forums as $forum) {
		$html .= '<tr id="' . (int) ($forum['id'] ?? 0) . '">';
		$html .= '<td data-label="' . html_encode($columns[0]) . '">' . admin_forums_item_cell($forum) . '</td>';
		$html .= '<td data-label="' . html_encode($columns[1]) . '">' . admin_forums_icon_cell($forum) . '</td>';
		$html .= '<td data-label="' . html_encode($columns[2]) . '"><span class="admin-modules-chip">' . (int) ($forum['num_posts'] ?? 0) . '</span></td>';
		$html .= '<td data-label="' . html_encode($columns[3]) . '">' . admin_forums_permissions_cell($forum, $groups) . '</td>';
		$html .= '<td data-label="' . html_encode($columns[4]) . '">' . admin_forums_actions_cell($forum) . '</td>';
		$html .= '</tr>';
	}

	return $html . '</tbody></table></div>';
}

/**
 * @param array<int, array<string, mixed>> $categories
 * @param array<int|string, array<string, mixed>> $groups
 */
function admin_forums_categories_list(array $categories, array $groups): string
{
	if (!$categories) {
		return admin_forums_empty(__('admin/forums.empty_categories'), 'fa-layer-group');
	}

	$html = '<div class="admin-forums-categories">';

	foreach ($categories as $category) {
		$id = (int) ($category['id'] ?? 0);
		$name = (string) ($category['name'] ?? '');
		$count = count($category['forums'] ?? []);

		$html .= '<form method="post" class="admin-forums-category">';
		$html .= admin_csrf_field();
		$html .= '<input type="hidden" name="edit_mode" value="1">';
		$html .= '<input type="hidden" name="cat_id" value="' . $id . '">';
		$html .= '<div class="admin-forums-category__header">';
		$html .= '<div class="admin-modules-table__caption">';
		$html .= '<span class="admin-modules-table__caption-icon admin-modules-table__caption-icon--primary"><i class="fa-solid fa-layer-group" aria-hidden="true"></i></span>';
		$html .= '<span class="admin-modules-table__caption-text">' . html_encode($name) . '</span>';
		$html .= '</div>';
		$html .= '<span class="admin-modules-table__count">' . $count . '</span>';
		$html .= admin_forums_category_actions();
		$html .= '</div>';
		$html .= admin_forums_table($category, $groups);
		$html .= '</form>';
	}

	return $html . '</div>';
}

function admin_reports_type_label(string $type): string
{
	$meta = admin_reports_type_meta($type);

	return $meta['label'] !== '' ? __($meta['label']) : ucfirst($type);
}

function admin_reports_link(array $report): string
{
	switch ($report['type'] ?? '') {
		case 'forum':
			return App::getURL('forums', ['pid' => $report['rel_id']], 'alert' . $report['rel_id']);
		case 'comment':
			return App::getURL('pageview', $report['page_id'], 'alert' . $report['rel_id']);
		case 'profile':
			return App::getURL('user', $report['rel_id']);
		default:
			return '#';
	}
}

function admin_reports_content_cell(array $report): string
{
	$summary = Format::truncate(strip_tags((string) ($report['message'] ?? '')), 120);
	$link = admin_reports_link($report);
	$type_meta = admin_reports_type_meta($report['type'] ?? '');

	return admin_modules_item_cell($summary !== '' ? $summary : __('admin/reports.content_missing'), [
		'description' => admin_reports_type_label($report['type'] ?? '') . ' #' . (int) ($report['rel_id'] ?? 0),
		'url' => $link,
		'icon' => $type_meta['icon'],
		'accent' => $type_meta['accent'],
	]);
}

function admin_reports_reporter_cell(array $report): string
{
	$reporter = trim((string) ($report['username'] ?? ''));

	if ($reporter === '') {
		$reporter = trim((string) ($report['user_ip'] ?? ''));

		if ($reporter === '') {
			$reporter = __('admin/reports.anonymous');
		}
	}

	$date = !empty($report['reported'])
		? html_encode(Format::today((int) $report['reported'], true))
		: '<span class="admin-modules-muted">&mdash;</span>';

	return '<div class="admin-reports-reporter">'
		. '<span class="admin-reports-reporter__name">' . html_encode($reporter) . '</span>'
		. '<span class="admin-reports-reporter__date">' . $date . '</span>'
		. '</div>';
}

function admin_reports_reason_cell(array $report): string
{
	$reason = trim((string) ($report['reason'] ?? ''));

	if ($reason === '') {
		return '<span class="admin-modules-muted">&mdash;</span>';
	}

	return '<span class="admin-reports-reason">' . html_encode($reason) . '</span>';
}

function admin_reports_actions_cell(array $report): string
{
	$link = admin_reports_link($report);
	$dismiss_confirm = html_encode(__('admin/reports.btn_dismiss_confirm'), ENT_QUOTES);

	$actions = admin_modules_action_link(
		$link,
		'fa-solid fa-eye',
		__('admin/reports.btn_view'),
		'btn-outline-primary'
	);

	$actions .= admin_modules_action_button(
		'dismiss',
		(string) ($report['id'] ?? ''),
		'fa-solid fa-check',
		__('admin/reports.btn_dismiss'),
		'btn-outline-warning',
		'onclick="return confirm(\'' . $dismiss_confirm . '\');"'
	);

	return admin_modules_table_actions_cell($actions);
}

/**
 * Construit les KPI de la page signalements.
 *
 * @param array<string, array{type: string, cnt: int|string}> $type_counts
 */
function admin_reports_build_stats(int $total_pending, array $type_counts): array
{
	$stats = [[
		'icon' => 'fa-solid fa-flag',
		'value' => (string) $total_pending,
		'label' => __('admin/reports.stats_total'),
		'variant' => 'danger',
	]];

	$known = [
		'forum' => ['icon' => 'fa-solid fa-comments', 'label' => __('admin/reports.stats_forum'), 'variant' => 'primary'],
		'comment' => ['icon' => 'fa-solid fa-comment', 'label' => __('admin/reports.stats_comment'), 'variant' => 'info'],
		'profile' => ['icon' => 'fa-solid fa-user', 'label' => __('admin/reports.stats_profile'), 'variant' => 'warning'],
	];

	foreach ($known as $type => $meta) {
		$count = 0;

		foreach ($type_counts as $row) {
			if (($row['type'] ?? '') === $type) {
				$count = (int) ($row['cnt'] ?? 0);
				break;
			}
		}

		$stats[] = [
			'icon' => $meta['icon'],
			'value' => (string) $count,
			'label' => $meta['label'],
			'variant' => $meta['variant'],
		];
	}

	return $stats;
}

/**
 * Affiche les filtres par type pour la page signalements.
 *
 * @param array<string, array{type: string}> $types
 * @param array<int, string> $selected_types
 */
function admin_reports_filters(array $types, array $selected_types): string
{
	if (!$types) {
		return '';
	}

	$html = '<div class="admin-reports-filters">';
	$html .= '<span class="admin-reports-filters__label">' . html_encode(__('admin/reports.filter_label')) . '</span>';
	$html .= '<div class="admin-reports-filters__list">';

	foreach ($types as $type => $row) {
		$type_name = $row['type'] ?? $type;
		$checked = empty($selected_types) || in_array($type_name, $selected_types, true);
		$meta = admin_reports_type_meta($type_name);

		$html .= '<label class="admin-reports-filter-chip' . ($checked ? ' admin-reports-filter-chip--active' : '') . '">';
		$html .= '<input type="checkbox" name="types[]" value="' . html_encode($type_name) . '"' . ($checked ? ' checked' : '') . ' class="admin-reports-filter-chip__input">';
		$html .= '<span class="admin-reports-filter-chip__content">';
		$html .= '<i class="fa-solid ' . html_encode($meta['icon']) . '" aria-hidden="true"></i>';
		$html .= html_encode(admin_reports_type_label($type_name));
		$html .= '</span></label>';
	}

	return $html . '</div></div>';
}

function admin_reports_empty(string $message, string $icon = 'fa-flag'): string
{
	return admin_settings_empty($message, $icon);
}

/**
 * Affiche le tableau des signalements.
 *
 * @param array<int, array<string, mixed>> $reports
 */
function admin_reports_table(array $reports, array $options = []): string
{
	if (!$reports) {
		return admin_reports_empty(
			$options['empty'] ?? __('admin/reports.empty_filtered'),
			$options['empty_icon'] ?? 'fa-filter'
		);
	}

	$columns = [
		__('admin/reports.table_content'),
		__('admin/reports.table_reporter'),
		__('admin/reports.table_reason'),
		__('admin/reports.table_actions'),
	];

	$rows = [];

	foreach ($reports as $report) {
		$rows[] = [
			admin_reports_content_cell($report),
			admin_reports_reporter_cell($report),
			admin_reports_reason_cell($report),
			admin_reports_actions_cell($report),
		];
	}

	return admin_modules_table($columns, $rows, [
		'caption' => $options['caption'] ?? __('admin/reports.caption'),
		'icon' => $options['icon'] ?? 'fa-solid fa-flag',
		'accent' => $options['accent'] ?? 'danger',
		'class' => trim('admin-reports-table-wrap ' . ($options['class'] ?? '')),
		'layout' => 'reports',
	]);
}

function admin_users_empty(string $message, string $icon = 'fa-users'): string
{
	return admin_settings_empty($message, $icon);
}

/**
 * @param array{total: int, online: int, banned: int, staff: int} $counts
 * @return array<int, array{icon: string, value: string, label: string, variant: string}>
 */
function admin_users_build_stats(array $counts): array
{
	return [
		['icon' => 'fa-solid fa-users', 'value' => (string) ($counts['total'] ?? 0), 'label' => __('admin/users.stats_total'), 'variant' => 'primary'],
		['icon' => 'fa-solid fa-signal', 'value' => (string) ($counts['online'] ?? 0), 'label' => __('admin/users.stats_online'), 'variant' => 'success'],
		['icon' => 'fa-solid fa-user-shield', 'value' => (string) ($counts['staff'] ?? 0), 'label' => __('admin/users.stats_staff'), 'variant' => 'info'],
		['icon' => 'fa-solid fa-user-lock', 'value' => (string) ($counts['banned'] ?? 0), 'label' => __('admin/users.stats_banned'), 'variant' => 'danger'],
	];
}

function admin_users_filter(string $filter): string
{
	return '<div class="admin-users-filter">'
		. '<div class="input-group input-group-sm">'
		. '<span class="input-group-text"><i class="fa-solid fa-search" aria-hidden="true"></i></span>'
		. '<input id="filter" name="filter" type="search" class="form-control" value="' . html_encode($filter) . '" placeholder="' . html_encode(__('admin/users.search_placeholder')) . '" autocomplete="off">'
		. '<button class="btn btn-outline-secondary" type="submit">' . html_encode(__('admin/security.filter_submit')) . '</button>'
		. '</div></div>';
}

/**
 * @param array<string, mixed> $member
 */
function admin_users_activity_title(array $member): string
{
	$last_ip = trim((string) ($member['last_ip'] ?? ''));
	$country = $last_ip !== '' ? (@COUNTRIES[geoip_country_code($last_ip)] ?? '') : '';
	$title = __('admin/users.result_life') . ' : ' . Format::today((int) ($member['activity'] ?? 0), 'H:i');

	if ($last_ip !== '') {
		$title .= ' - ' . __('admin/users.result_last_ip') . ' : ' . $last_ip . ($country !== '' ? ' (' . $country . ')' : '');
	}

	return $title;
}

/**
 * @param array<string, mixed> $member
 */
function admin_users_avatar_status(array $member): string
{
	$is_online = (int) ($member['activity'] ?? 0) > time() - 120;
	$status_label = $is_online ? __('admin/users.result_online') : __('admin/users.result_offline');

	return '<span class="admin-users-avatar-status admin-users-avatar-status--' . ($is_online ? 'online' : 'offline') . '" title="' . html_encode(admin_users_activity_title($member)) . '" aria-label="' . html_encode($status_label) . '">'
		. '<span class="admin-users-avatar-status__dot" aria-hidden="true"></span>'
		. '</span>';
}

/**
 * @param array<string, mixed> $member
 */
function admin_users_member_cell(array $member): string
{
	$id = (int) ($member['id'] ?? 0);
	$username = trim((string) ($member['username'] ?? ''));
	$username = $username !== '' ? $username : '#' . $id;

	$html = '<div class="admin-modules-item">';
	$html .= '<span class="admin-modules-item__avatar admin-modules-item__avatar--custom admin-users-item__avatar">';
	$html .= get_avatar($member, 38);
	$html .= admin_users_avatar_status($member);
	$html .= '</span>';
	$html .= '<span class="admin-modules-item__content">';
	$html .= '<a href="' . html_encode(App::getAdminURL('user_view', ['id' => $id])) . '" class="admin-modules-item__link">' . html_encode($username) . '</a>';

	return $html . '</span></div>';
}

/**
 * @param array<string, mixed> $member
 */
function admin_users_group_cell(array $member): string
{
	$name = trim((string) ($member['gname'] ?? ''));

	if ($name === '') {
		return '<span class="admin-modules-muted">&mdash;</span>';
	}

	return '<a class="admin-users-group group-color-' . html_encode((string) ($member['color'] ?? '')) . '" href="?page=users&amp;filter=group_id:%20' . (int) ($member['group_id'] ?? 0) . '">'
		. html_encode($name)
		. '</a>';
}

/**
 * @param array<string, mixed> $member
 */
function admin_users_email_cell(array $member): string
{
	$email = trim((string) ($member['email'] ?? ''));

	if ($email === '') {
		return '<span class="admin-modules-muted">&mdash;</span>';
	}

	return '<a class="admin-users-email" href="mailto:' . html_encode($email) . '">' . html_encode($email) . '</a>';
}

/**
 * @param array<string, mixed> $member
 */
function admin_users_register_cell(array $member): string
{
	$registered = (int) ($member['registered'] ?? 0);

	if ($registered <= 0) {
		return '<span class="admin-modules-muted">&mdash;</span>';
	}

	return '<span class="admin-users-register admin-modules-chip" title="' . html_encode(date('Y-m-d H:i', $registered)) . '">'
		. html_encode(Format::today($registered))
		. '</span>';
}

/**
 * @param array<string, mixed> $member
 */
function admin_users_ip_cell(array $member): string
{
	$last_ip = trim((string) ($member['last_ip'] ?? ''));

	if ($last_ip === '') {
		return '<span class="admin-modules-muted">&mdash;</span>';
	}

	return '<a class="admin-users-ip" href="?page=users&amp;filter=last_ip:%20' . rawurlencode($last_ip) . '" title="' . html_encode(__('admin/users.result_last_ip') . ' : ' . $last_ip) . '">'
		. html_encode($last_ip)
		. '</a>';
}

/**
 * @param array<string, mixed> $member
 */
function admin_users_country_cell(array $member): string
{
	$country = trim((string) ($member['country'] ?? ''));

	if ($country === '') {
		return '<span class="admin-modules-muted">&mdash;</span>';
	}

	return '<span class="admin-users-country">' . Widgets::countryFlag($country) . '<span>' . html_encode(@COUNTRIES[$country] ?? $country) . '</span></span>';
}

/**
 * @param array<string, mixed> $member
 */
function admin_users_role_badge(array $member): string
{
	$group_id = (int) ($member['group_id'] ?? 0);
	$roles = [
		'admin.backup' => ['icon' => 'fa-solid fa-star', 'label' => __('admin/users.result_btn_title_sadm'), 'class' => 'admin-users-role-badge--super'],
		'administrator' => ['icon' => 'fa-solid fa-star-half-stroke', 'label' => __('admin/users.result_btn_title_adm'), 'class' => 'admin-users-role-badge--admin'],
		'moderator' => ['icon' => 'fa-regular fa-star', 'label' => __('admin/users.result_btn_title_mod'), 'class' => 'admin-users-role-badge--mod'],
	];

	foreach ($roles as $permission => $meta) {
		if (App::groupHasPermission($group_id, $permission)) {
			return '<span class="admin-users-role-badge ' . $meta['class'] . '" title="' . html_encode($meta['label']) . '">'
				. '<i class="' . html_encode($meta['icon']) . '" aria-hidden="true"></i>'
				. '<span class="visually-hidden">' . html_encode($meta['label']) . '</span>'
				. '</span>';
		}
	}

	return '';
}

/**
 * @param array<string, mixed> $member
 */
function admin_users_actions_cell(array $member): string
{
	$id = (int) ($member['id'] ?? 0);
	$username = (string) ($member['username'] ?? '');
	$last_ip = (string) ($member['last_ip'] ?? '');
	$email = (string) ($member['email'] ?? '');
	$actions = '';

	if (has_permission('admin.edit_uprofile')) {
		$actions .= admin_modules_action_link('?page=user_view&id=' . $id, 'fa-solid fa-pencil', __('admin/users.result_edit'), 'btn-outline-primary');
	}

	if (has_permission('admin.del_member')) {
		$actions .= admin_modules_action_link('?page=user_delete&id=' . $id, 'fa-regular fa-trash-can', __('admin/users.result_delete'), 'btn-outline-danger');
	}

	if (has_permission('mod.ban_member')) {
		if (($member['ban_reason'] ?? null) !== null) {
			$actions .= admin_modules_action_link(
				'?page=security&filter=' . rawurlencode($username . ',' . $last_ip . ',' . $email),
				'fa-solid fa-unlock',
				__('admin/users.result_unban'),
				'btn-outline-info'
			);
		} else {
			$actions .= admin_modules_action_link(
				'?page=security&tab=add&username=' . rawurlencode($username) . '&ip=' . rawurlencode($last_ip) . '&email=' . rawurlencode($email),
				'fa-solid fa-lock',
				__('admin/users.result_ban'),
				'btn-outline-info'
			);
		}
	}

	return admin_modules_table_actions_cell($actions);
}

/**
 * @param array<int, array<string, mixed>> $users
 */
function admin_users_table(array $users): string
{
	if (!$users) {
		return admin_users_empty(__('admin/users.alert_not_found'), 'fa-user-slash');
	}

	$columns = [
		__('admin/users.table_username'),
		__('admin/users.table_email'),
		__('admin/users.table_rank'),
		__('admin/users.table_register'),
		__('admin/users.table_country'),
		__('admin/users.table_ip'),
		__('admin/users.table_actions'),
	];
	$rows = [];

	foreach ($users as $member) {
		$row = [
			admin_users_member_cell($member),
			admin_users_email_cell($member),
			admin_users_group_cell($member),
			admin_users_register_cell($member),
			admin_users_country_cell($member),
			admin_users_ip_cell($member),
			admin_users_actions_cell($member),
		];

		if (($member['ban_reason'] ?? null) !== null) {
			$row['_class'] = 'admin-users-row--banned';
		}

		$rows[] = $row;
	}

	return admin_modules_table($columns, $rows, [
		'caption' => __('admin/users.caption'),
		'icon' => 'fa-solid fa-users',
		'accent' => 'primary',
		'class' => 'admin-users-table-wrap',
		'layout' => 'users',
		'text_size' => [
			'storage_key' => 'admin-users-table-text-size',
			'default' => 1,
		],
	]);
}

function admin_user_view_nav(array $tabs, string $active): string
{
	return admin_tabs($tabs, [
		'active' => $active,
		'type' => 'bootstrap',
		'aria_label' => __('admin/user_view.page_title_short'),
	]);
}

function admin_user_view_tab_open(string $id, bool $active): string
{
	return admin_tab_pane_open($id, $active, 'admin-user-view-board__pane');
}

function admin_user_view_tab_close(): string
{
	return '</div>';
}

function admin_user_view_empty(string $message, string $icon = 'fa-user'): string
{
	return admin_settings_empty($message, $icon);
}

function admin_user_view_denied(): string
{
	return admin_status_bar(
		'warning',
		'<i class="fa-solid fa-lock me-1" aria-hidden="true"></i> ' . html_encode(__('admin/user_view.tab_denied'))
	);
}

/**
 * @return array<string, mixed>
 */
function admin_user_view_profile_data(object $user_info): array
{
	return [
		'ban_reason'   => Db::Get('select reason from {banlist} where rule = ? and type = "username"', $user_info->username),
		'num_friends'  => Db::Get('select count(*) from {friends} where u_id = ?', $user_info->id),
		'num_comments' => Db::Get('select count(*) from {comments} where user_id = ?', $user_info->id),
		'can_edit'     => has_permission('admin.edit_uprofile'),
		'can_mod'      => has_permission('moderator'),
		'is_mine'      => $user_info->id === App::getCurrentUser()->id,
		'user_info'    => $user_info,
	];
}

function admin_user_view_profile_board(object $user_info): string
{
	return App::renderTemplate('pages/user.php', admin_user_view_profile_data($user_info), true);
}

function admin_user_view_edit_board(): string
{
	ob_start();
	include ROOT_DIR . '/pages/profile.php';

	return '<div class="admin-user-view-form">' . ob_get_clean() . '</div>';
}

function admin_user_view_build_stats(object $user, array $mails, array $history): array
{
	$activity = (int) ($user->activity ?? 0);

	return [
		['icon' => 'fa-solid fa-user', 'value' => html_encode((string) $user->username), 'label' => __('admin/users.table_username'), 'variant' => 'primary'],
		['icon' => 'fa-solid fa-envelope', 'value' => (string) count($mails), 'label' => __('admin/user_view.stats_messages'), 'variant' => 'info'],
		['icon' => 'fa-solid fa-clock-rotate-left', 'value' => (string) count($history), 'label' => __('admin/user_view.stats_history'), 'variant' => 'warning'],
		['icon' => 'fa-solid fa-signal', 'value' => $activity > 0 ? html_encode(date('Y-m-d H:i', $activity)) : '&mdash;', 'label' => __('admin/users.result_life'), 'variant' => 'success'],
	];
}

/**
 * @param array<int, array<string, mixed>> $mails
 */
function admin_user_view_messages_table(array $mails): string
{
	if (!$mails) {
		return admin_user_view_empty(__('admin/user_view.empty_messages'), 'fa-envelope-open');
	}

	$columns = [
		__('admin/user_view.tab_send'),
		__('admin/user_view.tab_from'),
		__('admin/user_view.tab_to'),
		__('admin/user_view.tab_subject'),
		__('admin/user_view.tab_content'),
	];
	$rows = [];

	foreach ($mails as $data) {
		$rows[] = [
			'<span class="admin-modules-chip">' . html_encode(date('Y-m-d H:i', (int) ($data['posted'] ?? 0))) . '</span>',
			html_encode((string) ($data['su'] ?? '')),
			html_encode((string) ($data['ru'] ?? '')),
			html_encode((string) ($data['sujet'] ?? '')),
			nl2br(html_encode((string) ($data['message'] ?? ''))),
		];
	}

	return admin_modules_table($columns, $rows, [
		'caption' => __('admin/user_view.tab_mail'),
		'icon' => 'fa-solid fa-envelope',
		'accent' => 'info',
		'class' => 'admin-user-view-table-wrap',
		'layout' => 'user_view_messages',
	]);
}

/**
 * @param array<int, array<string, mixed>> $history
 */
function admin_user_view_history_table(array $history): string
{
	if (!$history) {
		return admin_user_view_empty(__('admin/history.alert_no_record'), 'fa-clock-rotate-left');
	}

	$columns = [
		__('admin/history.date'),
		__('admin/history.username'),
		__('admin/history.affected'),
		__('admin/history.ip'),
		__('admin/history.event'),
	];
	$rows = [];

	foreach ($history as $data) {
		$rows[] = [
			'<span class="admin-modules-chip">' . html_encode(date('Y-m-d H:i', (int) ($data['timestamp'] ?? 0))) . '</span>',
			html_encode((string) ($data['username'] ?? '')),
			html_encode((string) ($data['ausername'] ?? '')),
			html_encode((string) ($data['ip'] ?? '')),
			nl2br(html_encode((string) ($data['event'] ?? ''))),
		];
	}

	return admin_modules_table($columns, $rows, [
		'caption' => __('admin/user_view.tab_logs'),
		'icon' => 'fa-solid fa-clock-rotate-left',
		'accent' => 'warning',
		'class' => 'admin-user-view-table-wrap',
		'layout' => 'user_view_history',
	]);
}

function admin_groups_empty(string $message, string $icon = 'fa-users-gear'): string
{
	return admin_settings_empty($message, $icon);
}

/**
 * @param array<int|string, array<string, mixed>> $groups
 * @return array<int, array{icon: string, value: string, label: string, variant: string}>
 */
function admin_groups_build_stats(array $groups): array
{
	$total_users = 0;
	$system_groups = 0;

	foreach ($groups as $group) {
		$total_users += (int) ($group['count'] ?? 0);

		if (!empty($group['internal'])) {
			$system_groups++;
		}
	}

	return [
		['icon' => 'fa-solid fa-users-gear', 'value' => (string) count($groups), 'label' => __('admin/groups.stats_total'), 'variant' => 'primary'],
		['icon' => 'fa-solid fa-users', 'value' => (string) $total_users, 'label' => __('admin/groups.stats_members'), 'variant' => 'info'],
		['icon' => 'fa-solid fa-lock', 'value' => (string) $system_groups, 'label' => __('admin/groups.stats_system'), 'variant' => 'warning'],
		['icon' => 'fa-solid fa-sort', 'value' => (string) count($groups), 'label' => __('admin/groups.stats_order'), 'variant' => 'success'],
	];
}

function admin_groups_nav(array $tabs, string $active): string
{
	return admin_tabs($tabs, [
		'active' => $active,
		'type' => 'bootstrap',
		'aria_label' => __('admin/groups.management_title'),
	]);
}

function admin_groups_tab_open(string $id, bool $active): string
{
	return admin_tab_pane_open($id, $active, 'admin-groups-board__pane');
}

function admin_groups_tab_close(): string
{
	return '</div>';
}

function admin_groups_create_board(): string
{
	$html = '<form class="admin-groups-create" role="form" method="post">';
	$html .= admin_csrf_field();
	$html .= '<div class="admin-modules-table__toolbar">';
	$html .= '<div class="admin-modules-table__caption">';
	$html .= '<span class="admin-modules-table__caption-icon admin-modules-table__caption-icon--success"><i class="fa-solid fa-plus" aria-hidden="true"></i></span>';
	$html .= '<span class="admin-modules-table__caption-text">' . html_encode(__('admin/groups.creation_title')) . '</span>';
	$html .= '</div>';
	$html .= '<div class="admin-groups-create__control input-group input-group-sm">';
	$html .= '<input type="text" class="form-control" name="new_group_name" placeholder="' . html_encode(__('admin/groups.creation_name')) . '" required>';
	$html .= '<button type="submit" class="btn btn-success">' . html_encode(__('admin/groups.creation_btn')) . '</button>';
	$html .= '</div></div></form>';

	return $html;
}

/**
 * @param array<int|string, array<string, mixed>> $groups
 */
function admin_groups_list(array $groups, int $cur_id): string
{
	$columns = [
		__('admin/groups.creation_name'),
		__('admin/groups.management_users'),
	];
	$html = '<div class="admin-groups-list">';
	$html .= '<p class="admin-groups-list__hint"><i class="fa-solid fa-up-down-left-right" aria-hidden="true"></i> ' . html_encode(__('admin/groups.rank_small_order')) . '</p>';
	$html .= '<div class="admin-modules-table-wrap admin-groups-table-wrap">';
	$html .= '<div class="admin-modules-table__toolbar">';
	$html .= '<div class="admin-modules-table__caption">';
	$html .= '<span class="admin-modules-table__caption-icon admin-modules-table__caption-icon--primary"><i class="fa-solid fa-layer-group" aria-hidden="true"></i></span>';
	$html .= '<span class="admin-modules-table__caption-text">' . html_encode(__('admin/groups.list_caption')) . '</span>';
	$html .= '</div><span class="admin-modules-table__count">' . count($groups) . '</span></div>';
	$html .= '<div class="table-responsive admin-modules-table-scroll">';
	$html .= '<table id="reorder" class="table admin-modules-table admin-groups-table sortable mb-0">';
	$html .= admin_modules_table_colgroup('groups');
	$html .= '<thead><tr>';

	foreach ($columns as $column) {
		$html .= '<th scope="col">' . html_encode($column) . '</th>';
	}

	$html .= '</tr></thead><tbody>';

	foreach ($groups as $id => $group) {
		$name = html_encode((string) ($group['name'] ?? ''));
		$title = $cur_id === (int) $id
			? '<span class="admin-modules-item__title group-color-' . html_encode((string) ($group['color'] ?? '')) . '">' . $name . '</span>'
			: '<a href="?page=groups&amp;id=' . (int) $id . '" class="admin-modules-item__link group-color-' . html_encode((string) ($group['color'] ?? '')) . '">' . $name . '</a>';

		$item = '<div class="admin-modules-item">'
			. '<span class="admin-modules-item__avatar admin-modules-item__avatar--primary"><i class="fa-solid fa-grip-vertical" aria-hidden="true"></i></span>'
			. '<span class="admin-modules-item__content">' . $title
			. '<span class="admin-modules-item__desc">' . html_encode((string) ($group['role'] ?? '')) . '</span>'
			. '</span></div>';

		$html .= '<tr id="' . (int) ($group['id'] ?? $id) . '"' . ($cur_id === (int) $id ? ' class="admin-groups-row--active"' : '') . '>';
		$html .= '<td data-label="' . html_encode($columns[0]) . '">' . $item . '</td>';
		$html .= '<td data-label="' . html_encode($columns[1]) . '"><span class="admin-modules-chip">' . (int) ($group['count'] ?? 0) . '</span></td>';
		$html .= '</tr>';
	}

	return $html . '</tbody></table></div></div></div>';
}

/**
 * @param array<string, mixed> $group
 * @param array<int|string, array<string, mixed>> $groups
 */
function admin_groups_general_board(array $group, array $groups, int $cur_id): string
{
	$html = '<div class="admin-settings-subsection">';
	$html .= '<header class="admin-settings-subsection__header">';
	$html .= '<span class="admin-settings-subsection__icon"><i class="fa-solid fa-gear" aria-hidden="true"></i></span>';
	$html .= '<div class="admin-settings-subsection__heading">';
	$html .= '<h3 class="admin-settings-subsection__title">' . html_encode(__('admin/groups.config_title')) . '</h3>';
	$html .= '</div></header><div class="admin-settings-subsection__body">';
	$html .= admin_form_field_row(
		__('admin/groups.config_gname'),
		'<input type="text" class="form-control" id="group-name" name="group_name" value="' . html_encode((string) ($group['name'] ?? '')) . '">',
		['for' => 'group-name']
	);

	$role_input = !empty($group['internal'])
		? '<input class="form-control" id="group-role" disabled value="' . html_encode((string) ($group['role'] ?? '')) . '">'
		: Widgets::select('group_role', ['' => ''] + array_combine(GROUP_ROLES, GROUP_ROLES), $group['role'] ?? '');

	$html .= admin_form_field_row(__('admin/groups.config_grole'), $role_input, ['for' => 'group-role']);
	$color_select = '<select class="form-control group-color-' . html_encode((string) ($group['color'] ?? 0)) . '" id="group-color" name="color" onchange="this.className = \'form-control \' + $(this).find(\':selected\')[0].className;">';

	for ($i = 0; $i < 16; $i++) {
		$color_select .= '<option ' . ($i === (int) ($group['color'] ?? 0) ? 'selected="selected"' : '') . ' value="' . $i . '" class="group-color-' . $i . '">' . $i . ' ██████████</option>';
	}

	$color_select .= '</select>';
	$html .= admin_form_field_row(__('admin/groups.config_cname'), $color_select, ['for' => 'group-color']);
	$html .= '</div></div>';
	$html .= '<hr class="admin-settings-subsection__divider">';
	$html .= '<div class="admin-settings-subsection">';
	$html .= '<header class="admin-settings-subsection__header">';
	$html .= '<span class="admin-settings-subsection__icon"><i class="fa-solid fa-trash-can" aria-hidden="true"></i></span>';
	$html .= '<div class="admin-settings-subsection__heading">';
	$html .= '<h3 class="admin-settings-subsection__title">' . html_encode(__('admin/groups.delete_title')) . '</h3>';
	$html .= '</div></header><div class="admin-settings-subsection__body admin-groups-delete">';

	if (!empty($group['internal'])) {
		$html .= '<p class="admin-modules-muted mb-0">' . html_encode(__('admin/groups.delete_violation', ['%gid%' => $group['internal']])) . '</p>';
	} else {
		$options = [];

		foreach ($groups as $_group) {
			$options[(int) $_group['id']] = (string) $_group['name'];
		}

		$html .= '<button type="submit" name="delete_group" class="btn btn-outline-danger" onclick="return confirm(\'Sur?\');" value="' . $cur_id . '">'
			. '<i class="fa-regular fa-trash-can me-1" aria-hidden="true"></i>' . html_encode(__('admin/groups.delete_btn'))
			. '</button>';
		$html .= '<span class="admin-groups-delete__move">' . html_encode(__('admin/groups.delete_move')) . '</span>';
		$html .= Widgets::select('delete_new_group', $options, App::getConfig('default_user_group'), true, 'class="form-control form-select-sm"');
	}

	return $html . '</div></div>';
}

/**
 * @param array<string, mixed> $perms
 */
function admin_groups_permissions_board(string $id, array $perms, int $cur_id): string
{
	$html = '<div class="admin-groups-permissions">';
	$html .= '<label class="admin-groups-check-all">' . html_encode(__('admin/groups.config_check_all')) . ' <input type="checkbox" class="check-all" data-group="' . html_encode($id) . '"></label>';
	$permissions_count = 0;

	foreach ($perms as $title => $permissions) {
		if (!is_array($permissions)) {
			continue;
		}

		$html .= '<section class="admin-groups-permission-section">';
		$html .= '<h4 class="admin-groups-permission-section__title">' . html_encode((string) $title) . '</h4>';

		foreach ($permissions as $pname => $ptag) {
			$permissions_count++;
			$name = $id . '.' . $pname;
			$html .= '<label class="admin-groups-permission">'
				. '<input type="checkbox" data-group="' . html_encode($id) . '" autocomplete="off" name="perms[' . html_encode($name) . ']" ' . (App::groupHasPermission($cur_id, $name) ? 'checked="checked"' : '') . ' value="1">'
				. '<span>' . html_encode((string) $ptag) . '</span>'
				. '</label>';
		}

		$html .= '</section>';
	}

	if ($permissions_count === 0) {
		$html .= admin_panel_empty(__('admin/groups.no_perms'), 'fa-square-check', ['class' => 'admin-groups-permissions__empty']);
	}

	return $html . '</div>';
}

/**
 * Construit les KPI de la page newsletter.
 *
 * @param array<int, array{id?: int|string, cnt?: int|string}> $groups
 * @param array<int, array<string, mixed>> $letters
 */
function admin_broadcast_build_stats(array $groups, array $letters): array
{
	$newsletter_members = 0;
	$total_sent = 0;
	$total_failed = 0;

	foreach ($groups as $group) {
		if ((int) ($group['id'] ?? -1) === 0) {
			$newsletter_members = (int) ($group['cnt'] ?? 0);
			break;
		}
	}

	foreach ($letters as $letter) {
		$total_sent += (int) ($letter['mail_sent'] ?? 0);
		$total_failed += (int) ($letter['mail_failed'] ?? 0);
	}

	return [
		[
			'icon' => 'fa-solid fa-envelope-open-text',
			'value' => (string) count($letters),
			'label' => __('admin/broadcast.stats_campaigns'),
			'variant' => 'primary',
		],
		[
			'icon' => 'fa-solid fa-user-check',
			'value' => (string) $newsletter_members,
			'label' => __('admin/broadcast.stats_newsletter'),
			'variant' => 'info',
		],
		[
			'icon' => 'fa-solid fa-paper-plane',
			'value' => (string) $total_sent,
			'label' => __('admin/broadcast.stats_sent'),
			'variant' => 'success',
		],
		[
			'icon' => 'fa-solid fa-triangle-exclamation',
			'value' => (string) $total_failed,
			'label' => __('admin/broadcast.stats_failed'),
			'variant' => $total_failed ? 'danger' : 'secondary',
		],
	];
}

function admin_broadcast_nav(array $tabs, string $active): string
{
	return admin_tabs($tabs, [
		'active' => $active,
		'type' => 'bootstrap',
		'aria_label' => __('admin/broadcast.title'),
	]);
}

function admin_broadcast_tab_open(string $id, bool $active): string
{
	return admin_tab_pane_open($id, $active, 'admin-broadcast-board__pane');
}

function admin_broadcast_tab_close(): string
{
	return '</div>';
}

/**
 * Affiche le formulaire d'envoi de newsletter.
 *
 * @param array<int, array{id: int|string, name: string, cnt: int|string}> $groups
 * @param array<int, int> $selected_group_ids
 */
function admin_broadcast_form(string $subject, string $message, string $preset, array $groups, array $selected_group_ids): string
{
	if (!IS_POST && !$selected_group_ids) {
		$selected_group_ids = [0];
	}

	$subject_input = '<input id="sujet" name="sujet" class="form-control" type="text" maxlength="32" value="' . html_encode($subject) . '">';
	$message_input = '<textarea id="editor" name="message" class="form-control admin-broadcast-form__editor" placeholder="' . html_encode(__('admin/broadcast.form_content_ph')) . '...">' . html_encode($message ?: nl2br($preset)) . '</textarea>';

	$html = '<form method="post" id="admin-broadcast-form" class="admin-broadcast-form">';
	$html .= admin_csrf_field();
	$html .= '<input type="hidden" name="cycle" value="100">';
	$html .= '<div class="admin-broadcast-compose">';
	$html .= '<div class="admin-broadcast-compose__main">';
	$html .= '<div class="admin-form-fields-grid admin-broadcast-form__fields">';
	$html .= admin_form_field_stack(__('admin/broadcast.form_subject'), $subject_input, ['for' => 'sujet']);
	$html .= '</div></div>';
	$html .= '<aside class="admin-broadcast-compose__sidebar">';
	$html .= '<div class="admin-broadcast-recipients">';
	$html .= '<div class="admin-broadcast-recipients__header">';
	$html .= '<span class="admin-broadcast-recipients__title">' . html_encode(__('admin/broadcast.table_group')) . '</span>';
	$html .= '<span class="admin-broadcast-recipients__hint">' . html_encode(__('admin/broadcast.recipients_hint')) . '</span>';
	$html .= '</div>';
	$selected_labels = [];

	foreach ($groups as $group) {
		$id = (int) ($group['id'] ?? 0);

		if (in_array($id, $selected_group_ids, true)) {
			$selected_labels[] = (string) ($group['name'] ?? '');
		}
	}

	$html .= '<div class="admin-broadcast-selectbox" id="admin-broadcast-recipients">';
	$html .= '<button class="admin-broadcast-selectbox__toggle" type="button" aria-expanded="false">';
	$html .= '<span class="admin-broadcast-selectbox__value" data-placeholder="' . html_encode(__('admin/broadcast.recipients_placeholder')) . '">';
	$html .= html_encode($selected_labels ? implode(', ', $selected_labels) : __('admin/broadcast.recipients_placeholder'));
	$html .= '</span><i class="fa-solid fa-chevron-down" aria-hidden="true"></i></button>';
	$html .= '<div class="admin-broadcast-selectbox__menu">';

	foreach ($groups as $group) {
		$id = (int) ($group['id'] ?? 0);
		$count = (int) ($group['cnt'] ?? 0);
		$checked = in_array($id, $selected_group_ids, true);

		$html .= '<label class="admin-broadcast-selectbox__option">';
		$html .= '<input class="admin-broadcast-selectbox__input" name="groups[]" type="checkbox" value="' . $id . '"' . ($checked ? ' checked' : '') . '>';
		$html .= '<span class="admin-broadcast-selectbox__checkbox" aria-hidden="true"></span>';
		$html .= '<span class="admin-broadcast-selectbox__label">' . html_encode((string) ($group['name'] ?? '')) . '</span>';
		$html .= '<span class="admin-broadcast-selectbox__count">' . $count . '</span>';
		$html .= '</label>';
	}

	$html .= '</div></div></div></aside>';
	$html .= '<div class="admin-broadcast-compose__content">';
	$html .= '<div class="admin-form-fields-grid admin-broadcast-form__fields">';
	$html .= admin_form_field_stack(__('admin/broadcast.form_content'), $message_input, ['for' => 'editor']);
	$html .= '<div class="admin-form-fields-grid__actions"><button class="btn btn-primary" type="submit"><i class="fa-solid fa-paper-plane" aria-hidden="true"></i> ' . html_encode(__('admin/broadcast.form_send')) . '</button></div>';
	$html .= '</div></div>';
	$html .= '</div></form>';

	return $html;
}

/**
 * Affiche le résultat d'un envoi de newsletter.
 *
 * @param array<int, string> $mail_targets
 */
function admin_broadcast_result(array $mail_targets): string
{
	if (!$mail_targets) {
		return '';
	}

	return '<div class="admin-broadcast-result">'
		. '<button class="btn btn-outline-secondary" type="button" data-bs-toggle="collapse" data-bs-target="#admin-broadcast-targets" aria-expanded="false" aria-controls="admin-broadcast-targets">'
		. '<i class="fa-solid fa-list-check" aria-hidden="true"></i> ' . html_encode(__('admin/broadcast.title_view'))
		. '</button>'
		. '<div class="collapse admin-broadcast-result__targets" id="admin-broadcast-targets">'
		. implode('<br>', $mail_targets)
		. '</div></div>';
}

/**
 * Affiche l'historique des newsletters envoyées.
 *
 * @param array<int, array<string, mixed>> $letters
 * @param array<int|string, string> $group_map
 */
function admin_broadcast_history(array $letters, array $group_map, int $active_id = 0): string
{
	$html = '<div class="admin-broadcast-history-shell">';

	if (!$letters) {
		return $html . admin_settings_empty(__('admin/broadcast.empty_history'), 'fa-envelope-open-text') . '</div>';
	}

	$active_id = $active_id ?: (int) ($letters[0]['id'] ?? 0);
	$html .= '<div id="admin-broadcast-history" class="admin-broadcast-history">';
	$html .= '<div class="admin-broadcast-history__list" role="list">';

	foreach ($letters as $letter) {
		$id = (int) ($letter['id'] ?? 0);
		$letter_groups = array_intersect_key($group_map, array_flip(explode(',', (string) ($letter['groups'] ?? ''))));
		$sent = (int) ($letter['mail_sent'] ?? 0);
		$failed = (int) ($letter['mail_failed'] ?? 0);
		$total = $sent + $failed;

		$html .= '<button type="button" class="admin-broadcast-history__item' . ($id === $active_id ? ' active' : '') . '" data-target="admin-broadcast-message-' . $id . '">';
		$html .= '<span class="admin-broadcast-history__time">' . html_encode(Format::today((int) ($letter['date_sent'] ?? 0), 'H:i')) . '</span>';
		$html .= '<span class="admin-broadcast-history__subject">' . html_encode((string) ($letter['subject'] ?? '')) . '</span>';
		$html .= '<span class="admin-broadcast-history__meta">';
		$html .= html_encode(__('admin/broadcast.table_group')) . ' : <em>' . html_encode(implode(', ', $letter_groups)) . '</em>';
		$html .= ' &middot; ' . html_encode(__('admin/broadcast.table_author')) . ' : <em>' . html_encode((string) ($letter['username'] ?? '')) . '</em>';
		$html .= ' &middot; ' . html_encode(__('admin/broadcast.table_sent')) . ' : <em>' . $sent . '/' . $total . '</em>';
		$html .= '</span></button>';
	}

	$html .= '</div><div class="admin-broadcast-history__preview">';

	foreach ($letters as $letter) {
		$id = (int) ($letter['id'] ?? 0);
		$html .= '<article class="admin-broadcast-preview" id="admin-broadcast-message-' . $id . '"' . ($id === $active_id ? '' : ' hidden') . '>';
		$html .= '<div class="admin-broadcast-preview__body">' . markdown2html((string) ($letter['message'] ?? '')) . '</div>';
		$html .= '</article>';
	}

	return $html . '</div></div></div>';
}

/**
 * @return array<int, string>
 */
function admin_comments_columns(): array
{
	return [
		__('admin/comments.table_msg'),
		__('admin/comments.table_user'),
		__('admin/comments.table_state'),
		__('admin/comments.table_actions'),
	];
}

function admin_comments_empty(string $message, string $icon = 'fa-comment'): string
{
	return admin_settings_empty($message, $icon);
}

function admin_comments_message_cell(array $comment): string
{
	$message = trim(strip_tags((string) ($comment['message'] ?? '')));
	$page_id = (int) ($comment['page_id'] ?? 0);

	return admin_modules_item_cell($message !== '' ? Format::truncate($message, 140) : __('admin/comments.message_empty'), [
		'description' => $page_id > 0 ? __('admin/comments.page_ref') . ' #' . $page_id : '',
		'url' => $page_id > 0 ? App::getURL($page_id, [], '#msg' . (int) ($comment['id'] ?? 0)) : '',
		'icon' => 'fa-comment',
		'accent' => 'info',
	]);
}

function admin_comments_user_cell(array $comment): string
{
	$user = trim((string) ($comment['username'] ?? ''));

	if ($user === '') {
		$user = trim((string) ($comment['poster_name'] ?? ''));
	}

	$details = [];

	if (!empty($comment['date'])) {
		$details[] = Format::today((int) $comment['date'], true);
	} elseif (!empty($comment['posted'])) {
		$details[] = Format::today((int) $comment['posted'], true);
	}

	if (!empty($comment['user_ip'])) {
		$details[] = (string) $comment['user_ip'];
	}

	$html = '<div class="admin-comments-user">';
	$html .= '<span class="admin-comments-user__name">' . html_encode($user !== '' ? $user : __('admin/comments.user_anonymous')) . '</span>';

	if ($details) {
		$html .= '<span class="admin-comments-user__meta">' . html_encode(implode(' · ', $details)) . '</span>';
	}

	return $html . '</div>';
}

function admin_comments_state_cell(array $comment, array $status_labels): string
{
	$state = (int) ($comment['state'] ?? 1);
	$label = $status_labels[$state] ?? $status_labels[1] ?? '';
	$variant = 'secondary';

	if ($state === 0) {
		$variant = 'warning';
	} elseif ($state === 2) {
		$variant = 'danger';
	} elseif ($state === 1) {
		$variant = 'success';
	}

	return '<span class="admin-modules-status admin-comments-state admin-modules-status--is-' . html_encode($variant) . '">'
		. '<span class="admin-modules-status__dot" aria-hidden="true"></span>'
		. html_encode($label)
		. '</span>';
}

function admin_comments_actions_cell(array $comment): string
{
	$state = (int) ($comment['state'] ?? 1);
	$actions = '';
	$confirm = html_encode(__('admin/menu.ajax_confirm'), ENT_QUOTES);

	if ($state === 2) {
		$actions .= admin_modules_action_button(
			'com_accept',
			(string) ($comment['id'] ?? ''),
			'fa-solid fa-check',
			__('admin/comments.btn_accept'),
			'btn-outline-success',
			'onclick="return confirm(\'' . $confirm . '\');"'
		);
	}

	if (has_permission('mod.comment_censure') && $state !== 2) {
		$actions .= admin_modules_action_button(
			'com_censure',
			(string) ($comment['id'] ?? ''),
			'fa-solid fa-ban',
			__('admin/comments.btn_censor'),
			'btn-outline-warning',
			'onclick="return confirm(\'' . $confirm . '\');"'
		);
	}

	if (has_permission('mod.comment_delete')) {
		$actions .= admin_modules_action_button(
			'com_delete',
			(string) ($comment['id'] ?? ''),
			'fa-solid fa-trash-can',
			__('admin/comments.btn_delete'),
			'btn-outline-danger',
			'onclick="return confirm(\'' . $confirm . '\');"'
		);
	}

	$page_id = (int) ($comment['page_id'] ?? 0);

	if ($page_id > 0) {
		$actions .= admin_modules_action_link(
			App::getURL($page_id, [], '#msg' . (int) ($comment['id'] ?? 0)),
			'fa-solid fa-eye',
			__('admin/comments.btn_view'),
			'btn-outline-primary'
		);
	}

	return admin_modules_table_actions_cell($actions);
}

/**
 * @param array<int, string> $status_labels
 * @param array<int, int|string> $selected_states
 */
function admin_comments_filters(array $status_labels, array $selected_states): string
{
	if (!$status_labels) {
		return '';
	}

	$html = '<div class="admin-reports-filters admin-comments-filters">';
	$html .= '<span class="admin-reports-filters__label">' . html_encode(__('admin/comments.filter_label')) . '</span>';
	$html .= '<div class="admin-reports-filters__list">';

	foreach ($status_labels as $state => $label) {
		$state = (int) $state;
		$checked = in_array($state, array_map('intval', $selected_states), true);
		$icon = $state === 0 ? 'fa-clock' : ($state === 2 ? 'fa-ban' : 'fa-check');

		$html .= '<label class="admin-reports-filter-chip' . ($checked ? ' admin-reports-filter-chip--active' : '') . '">';
		$html .= '<input type="checkbox" name="states[]" value="' . $state . '"' . ($checked ? ' checked' : '') . ' class="admin-reports-filter-chip__input admin-comments-filter-chip__input">';
		$html .= '<span class="admin-reports-filter-chip__content">';
		$html .= '<i class="fa-solid ' . html_encode($icon) . '" aria-hidden="true"></i>';
		$html .= html_encode($label);
		$html .= '</span></label>';
	}

	return $html . '</div></div>';
}

/**
 * @param array<int, array<string, mixed>> $comments
 * @param array<int, string> $status_labels
 */
function admin_comments_table(array $comments, array $status_labels, array $options = []): string
{
	if (!$comments) {
		return admin_comments_empty(
			$options['empty'] ?? __('admin/comments.no_comment'),
			$options['empty_icon'] ?? 'fa-comment'
		);
	}

	$columns = admin_comments_columns();
	$rows = [];

	foreach ($comments as $comment) {
		$state = (int) ($comment['state'] ?? 1);
		$row = [
			admin_comments_message_cell($comment),
			admin_comments_user_cell($comment),
			admin_comments_state_cell($comment, $status_labels),
			admin_comments_actions_cell($comment),
		];

		if ($state === 0) {
			$row['_class'] = 'admin-comments-row--pending';
		} elseif ($state === 2) {
			$row['_class'] = 'admin-comments-row--censored';
		}

		$rows[] = $row;
	}

	return admin_modules_table($columns, $rows, [
		'caption' => $options['caption'] ?? __('admin/comments.title'),
		'icon' => $options['icon'] ?? 'fa-solid fa-comments',
		'accent' => $options['accent'] ?? 'primary',
		'class' => trim('admin-comments-table-wrap ' . ($options['class'] ?? '')),
		'layout' => 'comments',
	]);
}

function admin_comments_build_stats(int $total, int $pending, int $censored): array
{
	$approved = max(0, $total - $pending - $censored);

	return [
		[
			'icon' => 'fa-solid fa-comments',
			'value' => (string) $total,
			'label' => __('admin/comments.stats_total'),
			'variant' => 'primary',
		],
		[
			'icon' => 'fa-solid fa-clock',
			'value' => (string) $pending,
			'label' => __('admin/comments.stats_pending'),
			'variant' => 'warning',
		],
		[
			'icon' => 'fa-solid fa-ban',
			'value' => (string) $censored,
			'label' => __('admin/comments.stats_censored'),
			'variant' => 'danger',
		],
		[
			'icon' => 'fa-solid fa-check',
			'value' => (string) $approved,
			'label' => __('admin/comments.stats_ok'),
			'variant' => 'success',
		],
	];
}

/**
 * @param array<int, array<string, mixed>> $comments
 * @param array<int, string> $status_labels
 */
function admin_comments_board(array $comments, int $total, int $page_num, int $page_id, array $status_labels, array $selected_states = [], bool $show_filters = false): string
{
	$form_action = '?page=comments';

	if ($page_id > 0) {
		$form_action .= '&page_id=' . $page_id;
	}

	$html = '<form method="post" action="' . html_encode($form_action) . '" class="admin-comments-form">';
	$html .= admin_csrf_field();

	if ($show_filters) {
		$html .= admin_comments_filters($status_labels, $selected_states);
	}

	$html .= admin_comments_table($comments, $status_labels);

	if ($total > 25) {
		$html .= '<div class="admin-comments-pager">';
		$html .= Widgets::pager((int) ceil($total / 25), $page_num, 10, null, (int) App::GET('prevpn', 0));
		$html .= '</div>';
	}

	return $html . '</form>';
}

function admin_downloads_empty(string $message, string $icon = 'fa-download'): string
{
	return admin_settings_empty($message, $icon);
}

/**
 * @return array<string, array{icon: string, accent: string}>
 */
function admin_file_type_map(): array
{
	return [
		'image' => ['icon' => 'fa-image', 'accent' => 'info'],
		'video' => ['icon' => 'fa-film', 'accent' => 'danger'],
		'audio' => ['icon' => 'fa-music', 'accent' => 'success'],
		'archive' => ['icon' => 'fa-file-archive', 'accent' => 'warning'],
		'document' => ['icon' => 'fa-file-alt', 'accent' => 'primary'],
		'spreadsheet' => ['icon' => 'fa-file-excel', 'accent' => 'success'],
		'presentation' => ['icon' => 'fa-file-powerpoint', 'accent' => 'warning'],
		'text' => ['icon' => 'fa-file-alt', 'accent' => 'primary'],
		'code' => ['icon' => 'fa-file-code', 'accent' => 'primary'],
		'font' => ['icon' => 'fa-font', 'accent' => 'secondary'],
		'ebook' => ['icon' => 'fa-book', 'accent' => 'primary'],
		'database' => ['icon' => 'fa-database', 'accent' => 'info'],
		'executable' => ['icon' => 'fa-cogs', 'accent' => 'secondary'],
		'unknown' => ['icon' => 'fa-file', 'accent' => 'secondary'],
	];
}

/**
 * @return array<string, array{icon: string, accent: string}>
 */
function admin_file_extension_icon_map(): array
{
	return [
		'pdf' => ['icon' => 'fa-file-pdf', 'accent' => 'danger'],
		'doc' => ['icon' => 'fa-file-word', 'accent' => 'primary'],
		'docx' => ['icon' => 'fa-file-word', 'accent' => 'primary'],
		'odt' => ['icon' => 'fa-file-word', 'accent' => 'primary'],
		'rtf' => ['icon' => 'fa-file-word', 'accent' => 'primary'],
		'xls' => ['icon' => 'fa-file-excel', 'accent' => 'success'],
		'xlsx' => ['icon' => 'fa-file-excel', 'accent' => 'success'],
		'ods' => ['icon' => 'fa-file-excel', 'accent' => 'success'],
		'ppt' => ['icon' => 'fa-file-powerpoint', 'accent' => 'warning'],
		'pptx' => ['icon' => 'fa-file-powerpoint', 'accent' => 'warning'],
		'odp' => ['icon' => 'fa-file-powerpoint', 'accent' => 'warning'],
		'zip' => ['icon' => 'fa-file-archive', 'accent' => 'warning'],
		'rar' => ['icon' => 'fa-file-archive', 'accent' => 'warning'],
		'7z' => ['icon' => 'fa-file-archive', 'accent' => 'warning'],
		'tar' => ['icon' => 'fa-file-archive', 'accent' => 'warning'],
		'gz' => ['icon' => 'fa-file-archive', 'accent' => 'warning'],
		'bz2' => ['icon' => 'fa-file-archive', 'accent' => 'warning'],
		'xz' => ['icon' => 'fa-file-archive', 'accent' => 'warning'],
		'jar' => ['icon' => 'fa-file-archive', 'accent' => 'warning'],
		'apk' => ['icon' => 'fa-file-archive', 'accent' => 'warning'],
		'mp3' => ['icon' => 'fa-file-audio', 'accent' => 'success'],
		'wav' => ['icon' => 'fa-file-audio', 'accent' => 'success'],
		'ogg' => ['icon' => 'fa-file-audio', 'accent' => 'success'],
		'flac' => ['icon' => 'fa-file-audio', 'accent' => 'success'],
		'aac' => ['icon' => 'fa-file-audio', 'accent' => 'success'],
		'm4a' => ['icon' => 'fa-file-audio', 'accent' => 'success'],
		'mp4' => ['icon' => 'fa-file-video', 'accent' => 'danger'],
		'avi' => ['icon' => 'fa-file-video', 'accent' => 'danger'],
		'mkv' => ['icon' => 'fa-file-video', 'accent' => 'danger'],
		'mov' => ['icon' => 'fa-file-video', 'accent' => 'danger'],
		'webm' => ['icon' => 'fa-file-video', 'accent' => 'danger'],
		'flv' => ['icon' => 'fa-file-video', 'accent' => 'danger'],
		'wmv' => ['icon' => 'fa-file-video', 'accent' => 'danger'],
		'jpg' => ['icon' => 'fa-file-image', 'accent' => 'info'],
		'jpeg' => ['icon' => 'fa-file-image', 'accent' => 'info'],
		'png' => ['icon' => 'fa-file-image', 'accent' => 'info'],
		'gif' => ['icon' => 'fa-file-image', 'accent' => 'info'],
		'webp' => ['icon' => 'fa-file-image', 'accent' => 'info'],
		'svg' => ['icon' => 'fa-file-image', 'accent' => 'info'],
		'bmp' => ['icon' => 'fa-file-image', 'accent' => 'info'],
		'ico' => ['icon' => 'fa-file-image', 'accent' => 'info'],
		'txt' => ['icon' => 'fa-file-alt', 'accent' => 'primary'],
		'md' => ['icon' => 'fa-file-alt', 'accent' => 'primary'],
		'log' => ['icon' => 'fa-file-alt', 'accent' => 'primary'],
		'csv' => ['icon' => 'fa-file-csv', 'accent' => 'success'],
		'html' => ['icon' => 'fa-file-code', 'accent' => 'primary'],
		'htm' => ['icon' => 'fa-file-code', 'accent' => 'primary'],
		'css' => ['icon' => 'fa-file-code', 'accent' => 'primary'],
		'js' => ['icon' => 'fa-file-code', 'accent' => 'primary'],
		'ts' => ['icon' => 'fa-file-code', 'accent' => 'primary'],
		'json' => ['icon' => 'fa-file-code', 'accent' => 'primary'],
		'xml' => ['icon' => 'fa-file-code', 'accent' => 'primary'],
		'php' => ['icon' => 'fa-file-code', 'accent' => 'primary'],
		'py' => ['icon' => 'fa-file-code', 'accent' => 'primary'],
		'java' => ['icon' => 'fa-file-code', 'accent' => 'primary'],
		'c' => ['icon' => 'fa-file-code', 'accent' => 'primary'],
		'cpp' => ['icon' => 'fa-file-code', 'accent' => 'primary'],
		'h' => ['icon' => 'fa-file-code', 'accent' => 'primary'],
		'sql' => ['icon' => 'fa-database', 'accent' => 'info'],
		'db' => ['icon' => 'fa-database', 'accent' => 'info'],
		'sqlite' => ['icon' => 'fa-database', 'accent' => 'info'],
		'ttf' => ['icon' => 'fa-font', 'accent' => 'secondary'],
		'otf' => ['icon' => 'fa-font', 'accent' => 'secondary'],
		'woff' => ['icon' => 'fa-font', 'accent' => 'secondary'],
		'woff2' => ['icon' => 'fa-font', 'accent' => 'secondary'],
		'eot' => ['icon' => 'fa-font', 'accent' => 'secondary'],
		'epub' => ['icon' => 'fa-book', 'accent' => 'primary'],
		'mobi' => ['icon' => 'fa-book', 'accent' => 'primary'],
		'azw' => ['icon' => 'fa-book', 'accent' => 'primary'],
		'azw3' => ['icon' => 'fa-book', 'accent' => 'primary'],
		'exe' => ['icon' => 'fa-cogs', 'accent' => 'secondary'],
		'msi' => ['icon' => 'fa-cogs', 'accent' => 'secondary'],
		'dmg' => ['icon' => 'fa-cogs', 'accent' => 'secondary'],
		'deb' => ['icon' => 'fa-cogs', 'accent' => 'secondary'],
		'rpm' => ['icon' => 'fa-cogs', 'accent' => 'secondary'],
		'iso' => ['icon' => 'fa-compact-disc', 'accent' => 'warning'],
	];
}

/**
 * @return array{icon: string, accent: string}
 */
function admin_file_type_meta(string $type): array
{
	$map = admin_file_type_map();

	return $map[$type] ?? $map['unknown'];
}

function admin_downloads_type_meta(string $type): array
{
	return admin_file_type_meta($type);
}

/**
 * @param array<int, \Evo\Models\File> $files
 * @return array<int, array{icon: string, value: string, label: string, variant: string}>
 */
function admin_downloads_build_stats(array $files): array
{
	$total_hits = 0;
	$total_size = 0;
	$types = [];

	foreach ($files as $file) {
		$total_hits += (int) $file->hits;
		$total_size += (int) $file->size;
		$type = (string) $file->type;

		if ($type !== '') {
			$types[$type] = true;
		}
	}

	return [
		['icon' => 'fa fa-download', 'value' => (string) count($files), 'label' => __('admin/downloads.stats_total'), 'variant' => 'primary'],
		['icon' => 'fa fa-mouse-pointer', 'value' => (string) $total_hits, 'label' => __('admin/downloads.stats_hits'), 'variant' => 'success'],
		['icon' => 'fa fa-hdd', 'value' => Format::size($total_size), 'label' => __('admin/downloads.stats_size'), 'variant' => 'info'],
		['icon' => 'fa fa-tags', 'value' => (string) count($types), 'label' => __('admin/downloads.stats_types'), 'variant' => 'warning'],
	];
}

/**
 * @return array<int, string>
 */
function admin_downloads_columns(): array
{
	return [
		html_encode(__('admin/downloads.table_file')),
		html_encode(__('admin/downloads.table_date')),
		html_encode(__('admin/downloads.table_size')),
		html_encode(__('admin/downloads.table_hits')),
		html_encode(__('admin/downloads.table_actions')),
	];
}

function admin_downloads_item_cell(\Evo\Models\File $file): string
{
	$caption = trim((string) $file->caption);
	$caption = $caption !== '' ? $caption : (string) $file->name;
	$meta = admin_downloads_type_meta((string) $file->type);

	return admin_modules_item_cell($caption, [
		'description' => (string) $file->name,
		'url' => App::getURL($file->path),
		'icon' => $meta['icon'],
		'accent' => $meta['accent'],
	]);
}

function admin_downloads_date_cell(\Evo\Models\File $file): string
{
	return !empty($file->posted)
		? html_encode(Format::today((int) $file->posted, true))
		: '<span class="admin-modules-muted">&mdash;</span>';
}

function admin_downloads_size_cell(\Evo\Models\File $file): string
{
	return '<span class="admin-downloads-size">' . html_encode(Format::size((int) $file->size)) . '</span>';
}

function admin_downloads_hits_cell(\Evo\Models\File $file): string
{
	return '<span class="admin-modules-chip admin-downloads-hits-chip">' . html_encode((string) (int) $file->hits) . '</span>';
}

function admin_downloads_actions(\Evo\Models\File $file, string $delete_confirm): string
{
	$id = (string) $file->id;
	$edit_href = '?page=downloads&view=add&edit=' . rawurlencode($id);

	return admin_modules_actions_group(
		admin_modules_action_link(
			$edit_href,
			'fas fa-pencil-alt',
			__('admin/downloads.btn_edit'),
			'btn-outline-primary'
		)
		. admin_modules_action_link(
			App::getURL($file->path),
			'fas fa-external-link-alt',
			__('admin/downloads.btn_open'),
			'btn-outline-info'
		)
		. admin_modules_action_button(
			'delete',
			$id,
			'far fa-trash-alt',
			__('admin/general.btn_delete'),
			'btn-outline-danger',
			'onclick="return confirm(\'' . $delete_confirm . '\');"'
		)
	);
}

/**
 * @return array<int, string>
 */
function admin_downloads_table_row(\Evo\Models\File $file, string $delete_confirm): array
{
	$search = strtolower(trim((string) $file->caption . ' ' . (string) $file->name . ' ' . (string) $file->description));

	return [
		'_search' => $search,
		admin_downloads_item_cell($file),
		admin_downloads_date_cell($file),
		admin_downloads_size_cell($file),
		admin_downloads_hits_cell($file),
		admin_modules_table_actions_cell(admin_downloads_actions($file, $delete_confirm)),
	];
}

/**
 * Barre d'outils liste téléchargements (recherche + action).
 */
function admin_downloads_filters(string $filter): string
{
	$html = '<div class="admin-downloads-toolbar">';
	$html .= '<div class="admin-downloads-toolbar__search">';
	$html .= '<span class="admin-downloads-toolbar__search-label">' . html_encode(__('admin/downloads.search_label')) . '</span>';
	$html .= '<label class="admin-downloads-toolbar__search-field">';
	$html .= '<i class="fas fa-search" aria-hidden="true"></i>';
	$html .= '<input type="search" name="filter" class="form-control" value="' . html_encode($filter) . '" placeholder="' . html_encode(__('admin/downloads.search_placeholder')) . '" autocomplete="off" data-admin-toolbar-search-input>';
	$html .= '</label>';

	if ($filter !== '') {
		$html .= '<a class="btn btn-link btn-sm admin-downloads-toolbar__reset" href="#" data-admin-toolbar-search-reset role="button">';
		$html .= html_encode(__('admin/downloads.search_reset'));
		$html .= '</a>';
	} else {
		$html .= '<a class="btn btn-link btn-sm admin-downloads-toolbar__reset d-none" href="#" data-admin-toolbar-search-reset role="button" hidden>';
		$html .= html_encode(__('admin/downloads.search_reset'));
		$html .= '</a>';
	}

	$html .= '</div>';
	$html .= '<a href="?page=downloads&amp;view=add" class="btn btn-primary btn-sm admin-downloads-toolbar__add">';
	$html .= '<i class="fas fa-plus me-1" aria-hidden="true"></i>' . html_encode(__('admin/downloads.tab_add'));
	$html .= '</a></div>';

	return $html;
}

function admin_downloads_add_board(?\Evo\Models\File $edit_file = null): string
{
	$is_edit = $edit_file !== null;
	$id = $is_edit ? (int) $edit_file->id : 0;
	$textarea_id = 'download-description-' . ($id ?: 'new');
	$caption = $is_edit ? (string) $edit_file->caption : '';
	$name = $is_edit ? (string) $edit_file->name : '';
	$posted = $is_edit ? date('Y-m-d H:i', (int) $edit_file->posted) : date('Y-m-d H:i');
	$description = $is_edit ? (string) $edit_file->description : '';

	$html = '<div class="admin-downloads-content-wrap admin-downloads-content-wrap--add">';
	$html .= '<div class="admin-downloads-add-panel">';
	$html .= '<form method="post" enctype="multipart/form-data" role="form" class="form-horizontal admin-settings-grouped-form admin-downloads-add-form" id="admin-downloads-add-form">';
	$html .= admin_csrf_field();

	if ($is_edit) {
		$html .= '<input type="hidden" name="file_id" value="' . $id . '">';
	}

	$html .= '<section class="admin-settings-section admin-settings-section--grouped">';
	$html .= '<div class="admin-settings-section__body admin-settings-section__body--grouped">';

	if (!$is_edit) {
		$html .= '<div class="admin-settings-subsection admin-downloads-add-form__file-section">';
		$html .= '<header class="admin-settings-subsection__header admin-settings-subsection__header--split admin-downloads-add-form__file-header">';
		$html .= '<span class="admin-settings-subsection__icon"><i class="fas fa-cloud-upload-alt" aria-hidden="true"></i></span>';
		$html .= '<div class="admin-settings-subsection__heading">';
		$html .= '<h3 class="admin-settings-subsection__title">' . html_encode(__('admin/downloads.add_section_file')) . '</h3>';
		$html .= '<p class="admin-settings-subsection__desc">' . html_encode(__('admin/downloads.add_file_intro')) . '</p>';
		$html .= '</div>';
		$html .= '<div class="admin-file-dropzone__header-actions">';
		$html .= '<div class="admin-file-dropzone__header-actions-group" data-dropzone-header-actions hidden>';
		$html .= '<button type="button" class="btn btn-outline-danger btn-sm" data-dropzone-detail-remove>';
		$html .= '<i class="fas fa-times me-1" aria-hidden="true"></i>' . html_encode(__('admin/general.file_drop_detail_remove'));
		$html .= '</button></div>';
		$html .= '<a class="btn btn-outline-secondary btn-sm" href="?page=downloads&amp;view=list">' . html_encode(__('admin/downloads.btn_back_list')) . '</a>';
		$html .= '</div></header>';
		$html .= '<div class="admin-settings-subsection__body admin-downloads-add-form__dropzone-body">';
		$html .= admin_file_dropzone([
			'name' => 'new_download',
			'multiple' => false,
			'preview' => true,
			'detail' => true,
			'detail_remove_in' => 'header',
			'title' => __('admin/downloads.upload_title'),
			'hint' => __('admin/general.file_drop_hint'),
			'summary' => __('admin/downloads.upload_ready'),
			'detail_status' => __('admin/downloads.upload_ready'),
			'class' => 'admin-downloads-add-form__dropzone',
			'autofill' => [
				'form' => '#admin-downloads-add-form',
				'fields' => [
					'caption' => 'file[caption]',
					'name' => 'file[name]',
					'posted' => 'file[posted]',
				],
			],
		]);
		$html .= '</div></div>';
		$html .= '<hr class="admin-settings-subsection__divider admin-downloads-add-form__info-divider">';
	} else {
		$html .= '<div class="admin-downloads-edit__meta admin-downloads-add-form__meta">';
		$html .= '<span class="admin-downloads-edit__meta-item"><span class="admin-downloads-edit__meta-label">' . html_encode(__('admin/downloads.info_size')) . '</span> <strong>' . html_encode(Format::size((int) $edit_file->size)) . '</strong></span>';
		$html .= '<span class="admin-downloads-edit__meta-item"><span class="admin-downloads-edit__meta-label">' . html_encode(__('admin/downloads.info_type')) . '</span> <strong>' . html_encode((string) $edit_file->type) . '</strong></span>';
		$html .= '<span class="admin-downloads-edit__meta-item"><span class="admin-downloads-edit__meta-label">' . html_encode(__('admin/downloads.info_mime')) . '</span> <strong>' . html_encode((string) $edit_file->mime_type) . '</strong></span>';
		$html .= '<span class="admin-downloads-edit__meta-item"><span class="admin-downloads-edit__meta-label">' . html_encode(__('admin/downloads.info_hits')) . '</span> <strong>' . html_encode((string) (int) $edit_file->hits) . '</strong></span>';
		$html .= '</div>';
	}

	$html .= '<div class="admin-settings-subsection admin-downloads-add-form__info-section">';
	$html .= '<header class="admin-settings-subsection__header admin-downloads-add-form__info-header">';
	$html .= '<span class="admin-settings-subsection__icon"><i class="fas fa-edit" aria-hidden="true"></i></span>';
	$html .= '<div class="admin-settings-subsection__heading">';
	$html .= '<h3 class="admin-settings-subsection__title">' . html_encode($is_edit ? __('admin/downloads.edit_title') : __('admin/downloads.add_section_info')) . '</h3>';
	$html .= '<p class="admin-settings-subsection__desc">' . html_encode(__('admin/downloads.add_info_intro')) . '</p>';
	$html .= '</div>';
	$html .= '</header>';
	$html .= '<div class="admin-settings-subsection__body admin-downloads-add-form__fields">';
	$html .= '<div class="admin-form-fields-grid admin-form-fields-grid--downloads">';
	$html .= '<div class="admin-form-fields-grid__row admin-form-fields-grid__row--3">';
	$html .= admin_form_field_stack(
		__('admin/downloads.info_title'),
		'<input type="text" id="download-caption-' . html_encode($textarea_id) . '" name="file[caption]" class="form-control" value="' . html_encode($caption) . '">',
		['for' => 'download-caption-' . $textarea_id]
	);
	$html .= admin_form_field_stack(
		__('admin/downloads.info_fname'),
		'<input type="text" id="download-filename-' . html_encode($textarea_id) . '" name="file[name]" class="form-control" value="' . html_encode($name) . '">',
		['for' => 'download-filename-' . $textarea_id]
	);
	$html .= admin_form_field_stack(
		__('admin/downloads.info_dposted'),
		'<input type="text" name="file[posted]" class="form-control" value="' . html_encode($posted) . '">'
	);
	$html .= '</div>';
	$html .= '<div class="admin-form-fields-grid__row admin-form-fields-grid__row--1">';
	$html .= admin_form_field_stack(
		__('admin/downloads.info_desc'),
		'<textarea name="file[description]" id="' . html_encode($textarea_id) . '" class="form-control admin-downloads-edit__textarea">' . html_encode($description) . '</textarea>',
		['for' => $textarea_id]
	);
	$html .= '</div>';

	if (!$is_edit) {
		$html .= '<div class="admin-form-fields-grid__actions">';
		$html .= '<button type="submit" name="save_download" value="1" class="btn btn-primary btn-sm">';
		$html .= '<i class="fas fa-upload me-1" aria-hidden="true"></i>' . html_encode(__('admin/downloads.btn_send'));
		$html .= '</button></div>';
	}

	$html .= '</div></div></div>';
	$html .= '</div>';

	if ($is_edit) {
		$html .= '<footer class="admin-settings-section__footer admin-downloads-add-form__footer">';
		$html .= '<div class="admin-downloads-edit__actions">';
		$html .= '<button type="submit" name="save_download" value="1" class="btn btn-primary btn-sm">';
		$html .= '<i class="fas fa-save me-1" aria-hidden="true"></i>' . html_encode(__('admin/general.btn_save'));
		$html .= '</button>';
		$html .= '<a href="' . html_encode(App::getURL($edit_file->path)) . '" class="btn btn-outline-info btn-sm" target="_blank" rel="noopener noreferrer">';
		$html .= '<i class="fas fa-external-link-alt me-1" aria-hidden="true"></i>' . html_encode(__('admin/downloads.btn_open'));
		$html .= '</a>';
		$html .= '<a class="btn btn-outline-secondary btn-sm" href="?page=downloads&amp;view=list">' . html_encode(__('admin/downloads.btn_back_list')) . '</a>';
		$html .= '</div></footer>';
	}

	$html .= '</section></form></div></div>';

	return $html;
}

/**
 * @param array<int, \Evo\Models\File> $files
 */
function admin_downloads_table(array $files, string $delete_confirm, array $options = []): string
{
	if (!$files) {
		return admin_downloads_empty(
			$options['empty'] ?? __('admin/downloads.empty_filtered'),
			$options['empty_icon'] ?? 'fa-filter'
		);
	}

	$rows = [];

	foreach ($files as $file) {
		$rows[] = admin_downloads_table_row($file, $delete_confirm);
	}

	return admin_modules_table(admin_downloads_columns(), $rows, [
		'caption' => $options['caption'] ?? __('admin/downloads.caption'),
		'icon' => $options['icon'] ?? 'fa fa-download',
		'accent' => $options['accent'] ?? 'primary',
		'class' => trim('admin-downloads-table-wrap ' . ($options['class'] ?? '')),
		'layout' => 'downloads',
	]);
}

/* ── Gallery (admin + frontend) ── */

function gallery_handle_requests(bool $mod_view, string $origin): void
{
	if (isset($_FILES['ajaxup'])) {
		try {
			$file = \Evo\Models\File::create('ajaxup', $origin);
			die(json_encode([$file->name, $file->web_id, $file->web_id, $file->size]));
		} catch (UploadException $e) {
			die('Error: ' . $e->getMessage());
		}
	}

	if (!admin_csrf_valid()) {
		return;
	}

	if ($delete = App::POST('delete')) {
		$delete_ids = is_array($delete) ? $delete : [$delete];

		foreach ($delete_ids as $fileID) {
			if (($file = \Evo\Models\File::find($fileID, 'web_id')) && ($mod_view || $file->poster->id == App::getCurrentUser()->id)) {
				$file->delete();
				App::setSuccess(__('gallery.success_file_updated'));
			} else {
				App::setWarning(__('gallery.warning_delete_failed'));
			}
		}
	} elseif (App::POST('caption')) {
		foreach (App::POST('caption') as $fileID => $newCaption) {
			if (($file = \Evo\Models\File::find($fileID, 'web_id')) && ($mod_view || $file->poster->id == App::getCurrentUser()->id)) {
				$file->caption = $newCaption;
				$file->save();
			} else {
				App::setWarning(__('gallery.warning_update_failed'));
			}
		}
	} elseif (App::POST('filename')) {
		foreach (App::POST('filename') as $fileID => $newName) {
			if (($file = \Evo\Models\File::find($fileID, 'web_id')) && ($mod_view || $file->poster->id == App::getCurrentUser()->id)) {
				$file->name = $newName;
				$file->save();
			} else {
				App::setWarning(__('gallery.warning_update_failed'));
			}
		}
	}
}

/**
 * @return array<int, \Evo\Models\File>
 */
function gallery_fetch_files(bool $mod_view, bool $embed = false): array
{
	if ($mod_view) {
		if ($embed) {
			$where = 'origin is null or origin in (?, ?, ?)';
			$where_e = ['website', 'admin', ''];
		} else {
			$where = '1';
			$where_e = [];
		}
	} else {
		$where = '(origin like ? or origin like ?) and poster = ?';
		$where_e = ['user%', 'forum%', App::getCurrentUser()->id];
	}

	if (App::REQ('filter')) {
		$filter = '%' . App::REQ('filter') . '%';
		$where .= ' AND (name like ? or path like ? or caption like ?) ';
		$where_e[] = $filter;
		$where_e[] = $filter;
		$where_e[] = $filter;
	}

	return \Evo\Models\File::select("$where order by id desc", ...$where_e);
}

function admin_gallery_count_missing(): int
{
	$not_found = 0;

	foreach (\Evo\Models\File::select() as $file) {
		if (!file_exists(ROOT_DIR . '/' . $file->path)) {
			$not_found++;
		}
	}

	return $not_found;
}

function admin_gallery_cleanup_missing(): int
{
	$removed = 0;

	foreach (\Evo\Models\File::select() as $file) {
		if (!file_exists(ROOT_DIR . '/' . $file->path)) {
			$file->delete();
			$removed++;
		}
	}

	return $removed;
}

/**
 * @param array<int, \Evo\Models\File> $files
 * @return array<int, array{icon: string, value: string, label: string, variant: string}>
 */
function admin_gallery_build_stats(array $files, int $not_found): array
{
	$images = 0;
	$total_size = 0;

	foreach ($files as $file) {
		if (str_starts_with((string) $file->mime_type, 'image/')) {
			$images++;
		}

		$total_size += (int) $file->size;
	}

	return [
		['icon' => 'fa-solid fa-images', 'value' => (string) count($files), 'label' => __('admin/gallery.stats_total'), 'variant' => 'primary'],
		['icon' => 'fa-solid fa-image', 'value' => (string) $images, 'label' => __('admin/gallery.stats_images'), 'variant' => 'success'],
		['icon' => 'fa-solid fa-triangle-exclamation', 'value' => (string) $not_found, 'label' => __('admin/gallery.stats_missing'), 'variant' => 'warning'],
		['icon' => 'fa-solid fa-hard-drive', 'value' => Format::size($total_size), 'label' => __('admin/gallery.stats_size'), 'variant' => 'info'],
	];
}

function admin_gallery_empty(string $message, string $icon = 'fa-images'): string
{
	return admin_settings_empty($message, $icon);
}

function admin_gallery_cleanup_notice(int $count): string
{
	$html = '<div class="admin-gallery-board__notice">';
	$html .= admin_status_bar(
		'warning',
		'<i class="fa-solid fa-triangle-exclamation me-1" aria-hidden="true"></i> '
		. html_encode(__('admin/gallery.cleanup_notice'))
	);
	$html .= '<form method="post" class="admin-gallery-cleanup-form">';
	$html .= admin_csrf_field();
	$html .= '<button name="cleanup" value="1" type="submit" class="btn btn-sm btn-outline-warning">';
	$html .= '<i class="fa-solid fa-broom me-1" aria-hidden="true"></i> '
		. html_encode(__('admin/gallery.btn_cleanup'))
		. ' (' . (int) $count . ')';
	$html .= '</button></form></div>';

	return $html;
}

function admin_gallery_search_field(string $filter = '', bool $embed = false): string
{
	$html = '<div class="admin-gallery-toolbar__search">';
	$html .= '<label class="admin-gallery-toolbar__search-field">';
	$html .= '<i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>';
	$html .= '<input id="filter" name="filter" type="search" class="form-control form-control-sm" value="' . html_encode($filter) . '" placeholder="' . html_encode(__('gallery.search_placeholder')) . '" autocomplete="off">';
	$html .= '</label>';

	if ($filter !== '') {
		$params = ['page' => 'gallery'];

		if ($embed) {
			$params['embed'] = '1';
		}

		$html .= '<a class="btn btn-link btn-sm admin-gallery-toolbar__reset" href="?' . html_encode(http_build_query($params)) . '">';
		$html .= html_encode(__('admin/gallery.search_reset'));
		$html .= '</a>';
	}

	$html .= '</div>';

	return $html;
}

function admin_gallery_toolbar(int $not_found = 0, bool $embed = false, string $filter = ''): string
{
	$html = '<div class="admin-gallery-toolbar">';
	$html .= '<div class="admin-gallery-toolbar__actions">';
	$html .= '<button id="uploadfile" type="button" class="btn btn-primary btn-sm">';
	$html .= '<i class="fa-solid fa-upload me-1" aria-hidden="true"></i>' . html_encode(__('gallery.menu_btn_upload'));
	$html .= '</button>';
	$html .= '</div>';
	$html .= admin_gallery_search_field($filter, $embed);
	$html .= '<div class="admin-gallery-toolbar__selection gallery-controls">';
	$html .= '<button id="insertgal" type="button" class="btn btn-primary btn-sm d-none">' . html_encode(__('gallery.menu_btn_insert_gal')) . '</button>';
	$html .= '<button id="insertfile" type="button" class="btn btn-primary btn-sm d-none">' . html_encode(__('gallery.menu_btn_insert_file')) . '</button>';
	$html .= '<button id="insertthumb" type="button" class="btn btn-primary btn-sm d-none">' . html_encode(__('gallery.menu_btn_insert_thumb')) . '</button>';
	$html .= '<select id="gallery-thumbsize" class="form-select form-select-sm d-none">';
	$html .= '<option value="100x100">' . html_encode(__('gallery.menu_btn_crop_small')) . ' (100px)</option>';
	$html .= '<option value="200x200" selected>' . html_encode(__('gallery.menu_btn_crop_medium')) . ' (200px)</option>';
	$html .= '<option value="480x480">' . html_encode(__('gallery.menu_btn_crop_large')) . ' (480px)</option>';
	$html .= '<option value="100">' . html_encode(__('gallery.menu_btn_scale_small')) . ' (100px)</option>';
	$html .= '<option value="200">' . html_encode(__('gallery.menu_btn_scale_medium')) . ' (200px)</option>';
	$html .= '<option value="480">' . html_encode(__('gallery.menu_btn_scale_large')) . ' (480px)</option>';
	$html .= '<option value="0">' . html_encode(__('gallery.menu_btn_full_size')) . '</option>';
	$html .= '</select>';
	$html .= '<button id="deletefiles" type="button" class="btn btn-danger btn-sm d-none">';
	$html .= '<i class="fa-solid fa-xmark me-1" aria-hidden="true"></i>' . html_encode(__('gallery.menu_btn_delete'));
	$html .= '</button>';
	$html .= '</div></div>';

	if ($not_found > 0) {
		$html .= admin_gallery_cleanup_notice($not_found);
	}

	return $html;
}

/**
 * @return array<int, string>
 */
function admin_gallery_columns(): array
{
	return [
		html_encode(__('admin/gallery.table_preview')),
		html_encode(__('gallery.table_details')),
		html_encode(__('admin/gallery.table_user')),
		html_encode(__('gallery.table_date')),
		html_encode(__('gallery.table_category')),
		html_encode(__('gallery.table_views')),
		html_encode(__('admin/modules.table_action')),
	];
}

function admin_gallery_origin_label(?string $origin): string
{
	$origin = trim((string) $origin);

	if ($origin === '' || $origin === 'general') {
		return __('admin/gallery.origin_general');
	}

	if ($origin === 'website') {
		return __('admin/gallery.origin_website');
	}

	if ($origin === 'admin') {
		return __('admin/gallery.origin_admin');
	}

	if ($origin === 'downloads') {
		return __('admin/menu.sub_download');
	}

	if (str_starts_with($origin, 'user')) {
		return __('admin/gallery.origin_user');
	}

	if (str_starts_with($origin, 'forum')) {
		return __('admin/gallery.origin_forum');
	}

	return ucfirst($origin);
}

/**
 * @param \Evo\Models\File $file
 */
function admin_gallery_preview_cell($file, bool $selectable = false): string
{
	$caption = html_encode($file->caption ?: $file->name);
	$class = 'admin-gallery-list-preview' . ($selectable ? ' gallery-container' : '');
	$attrs = ' class="' . $class . '"';

	if ($selectable) {
		$attrs .= ' data-id="' . html_encode($file->web_id) . '"'
			. ' data-type="' . html_encode($file->type) . '"'
			. ' data-size="' . (int) $file->size . '"'
			. ' data-caption="' . $caption . '"'
			. ' data-href="' . html_encode($file->getLink()) . '"';
	}

	$html = '<div' . $attrs . '>';
	$html .= '<span class="admin-gallery-list-preview__frame">';
	$html .= '<img src="' . html_encode($file->getLink(128)) . '" alt="" loading="lazy">';
	$html .= '</span>';
	$html .= '<a href="' . html_encode($file->getLink()) . '" class="admin-gallery-list-preview__link" target="_blank" rel="noopener">';
	$html .= '<i class="fa-solid fa-up-right-from-square me-1" aria-hidden="true"></i>';
	$html .= html_encode(__('gallery.table_file_view'));
	$html .= '</a></div>';

	return $html;
}

/**
 * @param \Evo\Models\File $file
 */
function admin_gallery_details_cell($file): string
{
	$type_meta = admin_file_type_meta((string) $file->type);

	$html = '<div class="admin-gallery-list-fields file-list">';
	$html .= '<div class="admin-gallery-list-fields__inputs">';
	$html .= '<label class="admin-gallery-list-fields__row">';
	$html .= '<span class="admin-gallery-list-fields__label">' . html_encode(__('gallery.table_file_caption')) . '</span>';
	$html .= '<input type="text" class="form-control form-control-sm" name="caption[' . html_encode($file->web_id) . ']" value="' . html_encode($file->caption) . '">';
	$html .= '</label>';
	$html .= '<label class="admin-gallery-list-fields__row">';
	$html .= '<span class="admin-gallery-list-fields__label">' . html_encode(__('gallery.table_file_name')) . '</span>';
	$html .= '<input type="text" class="form-control form-control-sm" name="filename[' . html_encode($file->web_id) . ']" value="' . html_encode($file->name) . '">';
	$html .= '</label>';
	$html .= '</div>';
	$html .= '<div class="admin-gallery-list-fields__meta">';
	$html .= '<span class="admin-modules-chip admin-gallery-type-chip">';
	$html .= '<i class="fa-solid ' . html_encode($type_meta['icon']) . ' me-1" aria-hidden="true"></i>';
	$html .= html_encode((string) $file->type);
	$html .= '</span>';
	$html .= '<span class="admin-gallery-list-fields__meta-text">';
	$html .= html_encode($file->mime_type) . ' · ' . html_encode(Format::size((int) $file->size));
	$html .= '</span></div></div>';

	return $html;
}

/**
 * @param \Evo\Models\File $file
 */
function admin_gallery_user_cell($file): string
{
	$username = trim((string) ($file->poster->username ?? ''));

	if ($username === '') {
		return '<span class="admin-modules-muted">&mdash;</span>';
	}

	return admin_modules_meta_cell($username);
}

/**
 * @param \Evo\Models\File $file
 */
function admin_gallery_date_cell($file): string
{
	return !empty($file->posted)
		? admin_modules_meta_cell(Format::today((int) $file->posted, true))
		: '<span class="admin-modules-muted">&mdash;</span>';
}

/**
 * @param \Evo\Models\File $file
 */
function admin_gallery_origin_cell($file): string
{
	return '<span class="admin-modules-chip admin-gallery-origin-chip">' . html_encode(admin_gallery_origin_label($file->origin ?? '')) . '</span>';
}

/**
 * @param \Evo\Models\File $file
 */
function admin_gallery_views_cell($file): string
{
	return '<span class="admin-modules-chip admin-gallery-views-chip">' . html_encode((string) (int) $file->hits) . '</span>';
}

/**
 * @param \Evo\Models\File $file
 */
function admin_gallery_actions_cell($file, string $delete_confirm): string
{
	return '<form method="post" class="admin-gallery-row-form m-0">'
		. admin_csrf_field()
		. admin_modules_actions_group(
			admin_modules_action_button(
				'delete',
				$file->web_id,
				'fa-solid fa-trash-can',
				__('admin/general.btn_delete'),
				'btn-outline-danger',
				'onclick="return confirm(\'' . $delete_confirm . '\');"'
			)
		)
		. '</form>';
}

/**
 * @param \Evo\Models\File $file
 */
function admin_gallery_list_row($file, string $delete_confirm, bool $selectable = false): array
{
	return [
		admin_gallery_preview_cell($file, $selectable),
		admin_gallery_details_cell($file),
		admin_gallery_user_cell($file),
		admin_gallery_date_cell($file),
		admin_gallery_origin_cell($file),
		admin_gallery_views_cell($file),
		admin_gallery_actions_cell($file, $delete_confirm),
	];
}

/**
 * @param array<int, \Evo\Models\File> $files
 */
function admin_gallery_list_board(array $files, string $delete_confirm, bool $selectable = false): string
{
	if (!$files) {
		return admin_gallery_empty(__('admin/gallery.empty_filtered'), 'fa-filter');
	}

	$rows = [];

	foreach ($files as $file) {
		$rows[] = admin_gallery_list_row($file, $delete_confirm, $selectable);
	}

	return admin_modules_table(admin_gallery_columns(), $rows, [
		'layout' => 'gallery',
		'class' => 'admin-gallery-table-wrap file-list',
		'caption' => __('admin/gallery.main_title'),
		'icon' => 'fa-solid fa-images',
		'accent' => 'primary',
	]);
}

/**
 * @param array<int, \Evo\Models\File> $files
 */
function admin_gallery_content(array $files, string $delete_confirm, bool $embed = false): string
{
	$content = admin_gallery_list_board($files, $delete_confirm, $embed);

	return '<div id="gallery-content" class="admin-gallery-content gallery"><div id="content">' . $content . '</div></div>';
}

/**
 * @param array<int, \Evo\Models\File> $files
 */
function admin_gallery_board(array $files, string $delete_confirm, int $not_found): string
{
	$filter = trim((string) App::GET('filter', ''));
	$html = '<div class="admin-gallery-form">';
	$html .= admin_gallery_toolbar($not_found, true, $filter);
	$html .= admin_gallery_content($files, $delete_confirm, true);
	$html .= '</div>';

	return $html;
}

/**
 * @param array<int, \Evo\Models\File> $files
 */
function admin_gallery_embed(array $files): string
{
	$delete_confirm = html_encode(__('gallery.dialog_confirm_delete'));

	return admin_gallery_board($files, $delete_confirm, 0)
		. admin_gallery_scripts(true, true, true);
}

function admin_gallery_scripts(bool $mod_view = true, bool $admin_panel = true, bool $embed = false): string
{
	$delete_confirm = addslashes(__('gallery.dialog_confirm_delete'));

	if ($mod_view && $admin_panel) {
		$gallery_path = '/admin/';
	} elseif ($mod_view) {
		$gallery_path = '/admin/';
	} else {
		$gallery_path = '/';
	}

	$embed_query = $embed ? '&embed=1' : '';

	return <<<HTML
<script>
(function () {
	var gallery_url = site_url + '{$gallery_path}?page=gallery{$embed_query}';
	var gallery_pos = 0;

	$('.gallery').on('change keyup', '.file-list input', function () {
		var post = { csrf: csrf };
		post[$(this).attr('name')] = $(this).val();
		$.post('', post);
	});

	$('.gallery').on('click', '.gallery-container', function (e) {
		if ($(e.target).closest('.admin-gallery-list-preview__link').length) {
			return;
		}

		if ($(this).hasClass('active')) {
			$(this).removeClass('active');
		} else {
			$(this).addClass('active');
			$(this).attr('data-pos', gallery_pos++);
		}

		if ($('.gallery .active').length === 0) {
			$('#insertgal, #insertfile, #insertthumb, #deletefiles, #gallery-thumbsize').addClass('d-none');
		} else if (!$.fancybox || !$.fancybox.isOpen) {
			$('#deletefiles').removeClass('d-none');
		} else if ($('.gallery .active').length > 1) {
			$('#insertgal, #deletefiles, #gallery-thumbsize').removeClass('d-none');
			$('#insertfile, #insertthumb').addClass('d-none');
		} else {
			$('#insertfile, #insertthumb, #deletefiles, #gallery-thumbsize').removeClass('d-none');
			$('#insertgal').addClass('d-none');
		}
	});

	$('#deletefiles').click(function () {
		var files = [], captions = [];

		$('.gallery .active').each(function () {
			files.push($(this).attr('data-id'));
			captions.push($(this).attr('data-caption'));
		});

		if (files.length && confirm('{$delete_confirm}\\n' + captions.join("\\n"))) {
			$('#gallery-content').load(gallery_url + ' #gallery-content > *', { 'delete[]': files, csrf: csrf });
		}
	});

	$('#insertthumb, #insertfile').click(function () {
		var e = $('.gallery .active').first();
		if (e.length && window._editor) {
			window._editor.insertFiles([{
				link: e.attr('data-href'),
				name: e.attr('data-caption'),
				type: '',
				size: e.attr('data-size'),
				thumb: ($(this).attr('id') === 'insertthumb') ? $('#gallery-thumbsize').val() : 0,
				id: e.attr('data-id')
			}]);
		}
		if ($.fancybox) { $.fancybox.close(); }
	});

	$('#insertgal').click(function () {
		if (!window._editor) { return; }
		var files = [];
		$('.gallery .active').sort(function (a, b) {
			return parseInt($(a).attr('data-pos'), 10) - parseInt($(b).attr('data-pos'), 10);
		}).each(function () {
			var e = $(this);
			files.push({
				link: e.attr('data-href'),
				name: e.attr('data-caption'),
				type: 'thumb',
				size: e.attr('data-size'),
				thumb: $('#gallery-thumbsize').val(),
				id: e.attr('data-id')
			});
		});
		window._editor.insertFiles(files);
		if ($.fancybox) { $.fancybox.close(); }
	});

	$('#uploadfile').click(function () {
		if (typeof ajaxupload !== 'function') { return false; }
		ajaxupload(function () {
			$('#gallery-content').load(gallery_url + ' #gallery-content > *');
		});
		return false;
	});

	if (!$('textarea').length) {
		$('#gallery-thumbsize').addClass('d-none');
	}
})();
</script>
HTML;
}

/* ── Admin menu ── */

function admin_menu_nav(array $tabs, string $active): string
{
	return admin_tabs($tabs, [
		'active' => $active,
		'type' => 'link',
		'page' => 'menu',
		'aria_label' => __('admin/menu.title'),
	]);
}

function admin_menu_tab_open(string $id, bool $active): string
{
	return admin_tab_pane_open($id, $active, 'admin-menu-board__pane');
}

function admin_menu_tab_close(): string
{
	return '</div>';
}

function admin_menu_empty(string $message, string $icon = 'fa-list'): string
{
	return admin_settings_empty($message, $icon);
}

/**
 * @param array<int, array<int, array<string, mixed>>> $tree
 * @return array<int|string, string>
 */
function admin_menu_build_parent_list(array $tree, int $parent_id = 0, int $level = 0, ?array &$list = null): array
{
	if ($list === null) {
		$list = [0 => ''];
	}

	if (empty($tree[$parent_id])) {
		return $list;
	}

	foreach ($tree[$parent_id] as $menu) {
		$list[$menu['id']] = str_repeat('    ', $level) . ($menu['name'] ?? '');
		admin_menu_build_parent_list($tree, (int) $menu['id'], $level + 1, $list);
	}

	return $list;
}

/**
 * @param array<int, array<string, mixed>> $items
 * @param array<int, array<int, array<string, mixed>>> $tree
 * @return array<int, array{icon: string, value: string, label: string, variant: string}>
 */
function admin_menu_build_stats(array $items, array $tree): array
{
	$root = count($tree[0] ?? []);
	$nested = max(0, count($items) - $root);
	$restricted = 0;

	foreach ($items as $item) {
		if ((int) ($item['visibility'] ?? 0) > 0) {
			$restricted++;
		}
	}

	return [
		['icon' => 'fa-solid fa-list', 'value' => (string) count($items), 'label' => __('admin/menu.stats_total'), 'variant' => 'primary'],
		['icon' => 'fa-solid fa-sitemap', 'value' => (string) $root, 'label' => __('admin/menu.stats_root'), 'variant' => 'success'],
		['icon' => 'fa-solid fa-diagram-project', 'value' => (string) $nested, 'label' => __('admin/menu.stats_nested'), 'variant' => 'info'],
		['icon' => 'fa-solid fa-lock', 'value' => (string) $restricted, 'label' => __('admin/menu.stats_restricted'), 'variant' => 'warning'],
	];
}

function admin_menu_internal_pages(): array
{
	$user_pages = Db::QueryAll('select p.page_id, title from {pages} as p join {pages_revs} as r ON r.page_id = p.page_id AND r.revision = p.revisions order by pub_date desc, title asc');
	$cat_pages = [];

	foreach (Db::QueryAll('SELECT DISTINCT category from {pages} WHERE category <> ""') ?: [] as $cat) {
		$cat_pages[strtr('category/' . $cat['category'], ' ', '-')] = $cat['category'];
	}

	return [
		'' => '---',
		'Pages' => new HtmlSelectGroup(array_column($user_pages ?: [], 'title', 'page_id')),
		'Categories' => new HtmlSelectGroup($cat_pages),
		'Internes' => new HtmlSelectGroup(array_combine(INTERNAL_PAGES, array_map('ucwords', INTERNAL_PAGES))),
	];
}

/**
 * @param array<int, array<int, array<string, mixed>>> $tree
 */
function admin_menu_table_rows(int $id, int $level, array &$tree, string $delete_confirm): string
{
	$html = '';

	if (empty($tree[$id])) {
		return $html;
	}

	foreach ($tree[$id] as $menu) {
		$name = str_repeat('<span class="admin-menu-tree__arrow"><i class="fa-solid fa-arrow-right" aria-hidden="true"></i></span> ', $level) . html_encode($menu['name'] ?? '');
		$icon = fa_icon_html($menu['icon'] ?? 'circle', 'solid', ['fa-fw']);
		$order = (int) ($menu['priority'] ?? 0);

		if (is_null($menu['page_name'])) {
			$link = html_encode(strpos((string) $menu['link'], '/') ? $menu['link'] : App::getURL($menu['link']));
			$label = html_encode(Format::truncate((string) $menu['link'], 40));
		} else {
			$link = App::getURL($menu['link']);
			$label = html_encode(Format::truncate((string) $menu['page_name'], 40));
		}

		$address = '<a href="' . html_encode($link) . '">' . $label . '</a>';
		$actions = admin_modules_actions_group(
			admin_modules_action_button('edit_menu', (string) $menu['id'], 'fa-solid fa-pencil', __('admin/menu.btn_title_edit'), 'btn-outline-primary')
			. admin_modules_action_button('del_menu', (string) $menu['id'], 'fa-regular fa-trash-can', __('admin/menu.btn_title_delete'), 'btn-outline-danger', 'onclick="return confirm(\'' . $delete_confirm . '\');"')
		);

		$row_class = $level > 0 ? ' admin-menu-row--nested' : '';

		$html .= '<tr id="' . (int) $menu['id'] . '" class="' . trim($row_class) . '">';
		$html .= '<td data-label="' . html_encode(__('admin/menu.table_name')) . '">' . $name . '</td>';
		$html .= '<td data-label="' . html_encode(__('admin/menu.table_ico')) . '">' . $icon . '</td>';
		$html .= '<td class="admin-menu-order-cell" data-label="' . html_encode(__('admin/menu.table_order')) . '">' . $order . '</td>';
		$html .= '<td data-label="' . html_encode(__('admin/menu.table_addr')) . '">' . $address . '</td>';
		$html .= '<td data-label="' . html_encode(__('admin/modules.table_action')) . '">' . $actions . '</td>';
		$html .= '</tr>';

		if (!empty($tree[$menu['id']])) {
			$html .= admin_menu_table_rows((int) $menu['id'], $level + 1, $tree, $delete_confirm);
		}
	}

	return $html;
}

/**
 * @param array<int, array<int, array<string, mixed>>> $tree
 */
function admin_menu_table(array $tree, string $delete_confirm): string
{
	$html = '<div class="admin-reports-table-wrap admin-menu-table-wrap">';
	$html .= '<div class="admin-modules-table__toolbar">';
	$html .= '<div class="admin-modules-table__caption">';
	$html .= '<span class="admin-modules-table__caption-icon admin-modules-table__caption-icon--primary"><i class="fa-solid fa-list" aria-hidden="true"></i></span>';
	$html .= '<span class="admin-modules-table__caption-text">' . html_encode(__('admin/menu.list_title')) . '</span>';
	$html .= '</div>';
	$html .= '<span class="admin-modules-table__count">' . admin_menu_count_items($tree) . '</span>';
	$html .= '</div>';
	$html .= '<div class="admin-menu-filters">';
	$html .= '<span class="admin-menu-filters__hint"><i class="fa-solid fa-grip-vertical me-1" aria-hidden="true"></i>' . html_encode(__('admin/menu.drag_hint_short')) . '</span>';
	$html .= '<div class="admin-menu-filters__actions">';
	$html .= '<a href="?page=menu&amp;tab=form" class="btn btn-primary btn-sm"><i class="fa-solid fa-circle-plus me-1" aria-hidden="true"></i>' . html_encode(__('admin/menu.btn_new')) . '</a>';
	$html .= '</div></div>';
	$html .= '<div class="table-responsive admin-modules-table-scroll">';
	$html .= '<table class="table sortable admin-modules-table admin-menu-table mb-0" id="menu-editor">';
	$html .= '<thead><tr>';
	$html .= '<th scope="col">' . html_encode(__('admin/menu.table_name')) . '</th>';
	$html .= '<th scope="col">' . html_encode(__('admin/menu.table_ico')) . '</th>';
	$html .= '<th scope="col">' . html_encode(__('admin/menu.table_order')) . '</th>';
	$html .= '<th scope="col">' . html_encode(__('admin/menu.table_addr')) . '</th>';
	$html .= '<th scope="col">' . html_encode(__('admin/modules.table_action')) . '</th>';
	$html .= '</tr></thead><tbody>';
	$html .= admin_menu_table_rows(0, 0, $tree, $delete_confirm);
	$html .= '</tbody></table></div></div>';

	return $html;
}

function admin_menu_count_items(array $tree, int $parent_id = 0): int
{
	$count = 0;

	foreach ($tree[$parent_id] ?? [] as $menu) {
		$count++;
		$count += admin_menu_count_items($tree, (int) $menu['id']);
	}

	return $count;
}

/**
 * @param array<string, mixed> $cur_elem
 * @param array<int|string, string> $parent_list
 */
function admin_menu_form_board(array $cur_elem, array $parent_list, array $pages): string
{
	$is_edit = !empty($cur_elem['id']);
	$title = $is_edit ? __('admin/menu.cur_elem_edit', ['%cur%' => $cur_elem['id']]) : __('admin/menu.cur_elem_add');
	$link_value = $cur_elem['page_name'] ? '' : (string) ($cur_elem['link'] ?? '');
	$visibility = (int) ($cur_elem['visibility'] ?? 0);

	$name_field = admin_form_field_stack(
		__('admin/menu.table_name'),
		'<input class="form-control" name="name" id="admin-menu-name" type="text" value="' . html_encode((string) ($cur_elem['name'] ?? '')) . '" required>',
		['for' => 'admin-menu-name']
	);
	$icon_field = admin_form_field_stack(
		__('admin/menu.table_ico'),
		'<div class="admin-menu-icon-picker">' . Widgets::iconSelect('icon', (string) ($cur_elem['icon'] ?? '')) . '</div>'
	);
	$identity_fields = '<div class="admin-menu-form-grid admin-menu-form-grid--identity">' . $name_field . $icon_field . '</div>';

	$placement_fields = '<div class="admin-menu-form-grid admin-menu-form-grid--placement">';
	$placement_fields .= admin_form_field_stack(__('admin/menu.table_parent'), Widgets::select('parent', $parent_list, $cur_elem['parent'] ?? 0, false));
	$placement_fields .= admin_form_field_stack(__('admin/menu.table_order'), Widgets::select('priority', array_keys(array_fill(0, 100, '')), $cur_elem['priority'] ?? 0));
	$placement_fields .= '</div>';

	$link_fields = '<div class="admin-menu-destination-grid">';
	$link_fields .= admin_form_field_stack(
		__('admin/menu.table_link'),
		'<input class="form-control" name="link" id="admin-menu-link" type="text" value="' . html_encode($link_value) . '">',
		['for' => 'admin-menu-link']
	);
	$link_fields .= '<div class="admin-menu-destination-grid__separator"><span>' . html_encode(__('admin/menu.table_or')) . '</span></div>';
	$link_fields .= admin_form_field_stack(__('admin/menu.table_addr'), Widgets::select('internal_page', $pages, $cur_elem['link'] ?? ''));
	$link_fields .= '</div>';

	$visibility_select = '<select class="form-select" name="visibility">';
	$visibility_select .= '<option value="0"' . ($visibility === 0 ? ' selected' : '') . '>' . html_encode(__('admin/menu.table_everyone')) . '</option>';
	$visibility_select .= '<option value="1"' . ($visibility === 1 ? ' selected' : '') . '>' . html_encode(__('admin/menu.table_members_only')) . '</option>';
	$visibility_select .= '<option value="2"' . ($visibility === 2 ? ' selected' : '') . '>' . html_encode(__('admin/menu.table_guess_only')) . '</option>';
	$visibility_select .= '</select>';
	$access_fields = '<div class="admin-menu-form-grid admin-menu-form-grid--access">' . admin_form_field_stack(__('admin/menu.table_viewable'), $visibility_select) . '</div>';

	$html = '<div class="admin-menu-form admin-settings">';
	$html .= '<form class="form-horizontal admin-settings-grouped-form admin-menu-form-panel__form" method="post" action="?page=menu&amp;tab=form">';
	$html .= admin_csrf_field();
	$html .= '<input type="hidden" name="add_menu" value="' . html_encode((string) ($cur_elem['id'] ?: 0)) . '">';
	$html .= '<section class="admin-settings-section admin-settings-section--grouped admin-menu-form-panel">';
	$html .= '<header class="admin-menu-form-panel__header">';
	$html .= '<div class="admin-menu-form-panel__heading">';
	$html .= '<span class="admin-menu-form-panel__icon"><i class="fa-solid ' . ($is_edit ? 'fa-pencil' : 'fa-circle-plus') . '" aria-hidden="true"></i></span>';
	$html .= '<div><h3 class="admin-menu-form-panel__title">' . html_encode($title) . '</h3>';
	$html .= '<p class="admin-menu-form-panel__desc">' . html_encode(__('admin/menu.form_identity_desc')) . '</p></div>';
	$html .= '</div>';
	$html .= '</header>';
	$html .= '<div class="admin-settings-section__body admin-settings-section__body--grouped admin-menu-form-panel__body">';

	$html .= admin_settings_subsection(__('admin/menu.form_identity'), $identity_fields, ['icon' => 'fa-id-card', 'description' => __('admin/menu.form_identity_desc')]);
	$html .= '<hr class="admin-settings-subsection__divider">';
	$html .= admin_settings_subsection(__('admin/menu.form_placement'), $placement_fields, ['icon' => 'fa-sitemap', 'description' => __('admin/menu.form_placement_desc')]);
	$html .= '<hr class="admin-settings-subsection__divider">';
	$html .= admin_settings_subsection(__('admin/menu.form_link'), $link_fields, ['icon' => 'fa-link', 'description' => __('admin/menu.form_link_desc')]);
	$html .= '<hr class="admin-settings-subsection__divider">';
	$html .= admin_settings_subsection(__('admin/menu.form_access'), $access_fields, ['icon' => 'fa-eye', 'description' => __('admin/menu.form_access_desc')]);

	$html .= '</div><footer class="admin-settings-section__footer admin-menu-form-panel__footer">';
	$html .= '<div class="admin-menu-form-panel__actions text-center">';
	$html .= '<button class="btn btn-primary" type="submit"><i class="fa-solid fa-floppy-disk me-1" aria-hidden="true"></i>' . html_encode(__('admin/menu.btn_save')) . '</button> ';
	$html .= '<a class="btn btn-outline-secondary" href="?page=menu&amp;tab=list">' . html_encode(__('admin/menu.btn_cancel')) . '</a>';
	$html .= '</div></footer></section></form></div>';

	return $html;
}

/* ── Admin pages list ── */

/**
 * @param array<string, int> $counts
 * @return array<int, array{icon: string, value: string, label: string, variant: string}>
 */
function admin_pages_build_stats(array $counts): array
{
	$total = array_sum($counts);

	return [
		['icon' => 'fa-solid fa-file-lines', 'value' => (string) $total, 'label' => __('admin/pages.stats_total'), 'variant' => 'primary'],
		['icon' => 'fa-solid fa-circle-check', 'value' => (string) ($counts['published'] ?? 0), 'label' => __('admin/pages.stats_published'), 'variant' => 'success'],
		['icon' => 'fa-solid fa-pencil', 'value' => (string) ($counts['draft'] ?? 0), 'label' => __('admin/pages.stats_draft'), 'variant' => 'warning'],
		['icon' => 'fa-solid fa-box-archive', 'value' => (string) ($counts['archived'] ?? 0), 'label' => __('admin/pages.stats_archived'), 'variant' => 'info'],
	];
}

function admin_pages_empty(string $message, string $icon = 'fa-file-lines'): string
{
	return admin_settings_empty($message, $icon);
}

function admin_pages_status_meta(string $status): array
{
	static $map = [
		'draft' => ['icon' => 'fa-pencil', 'accent' => 'warning'],
		'published' => ['icon' => 'fa-circle-check', 'accent' => 'success'],
		'archived' => ['icon' => 'fa-box-archive', 'accent' => 'secondary'],
		'revision' => ['icon' => 'fa-clock-rotate-left', 'accent' => 'info'],
		'autosave' => ['icon' => 'fa-floppy-disk', 'accent' => 'secondary'],
	];

	return $map[$status] ?? ['icon' => 'fa-file', 'accent' => 'secondary'];
}

/**
 * @param array<string, string> $status_labels
 * @param array<int, string> $selected_statuses
 */
function admin_pages_filters(array $status_labels, array $selected_statuses, string $filter): string
{
	$items = [];

	foreach ($status_labels as $status => $label) {
		$meta = admin_pages_status_meta($status);
		$items[$status] = ['label' => $label, 'icon' => $meta['icon']];
	}

	$html = '<div class="admin-pages-toolbar">';
	$html .= admin_filter_chips($items, $selected_statuses, 'statuses', __('admin/pages.filter_label'));
	$html .= '<div class="admin-pages-toolbar__search">';
	$html .= '<label class="admin-pages-toolbar__search-field">';
	$html .= '<i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>';
	$html .= '<input type="search" name="filter" class="form-control" value="' . html_encode($filter) . '" placeholder="' . html_encode(__('admin/pages.btn_search')) . '">';
	$html .= '</label>';

	if ($filter !== '') {
		$params = ['page' => 'pages'];

		foreach ($selected_statuses as $status) {
			$params['statuses'][] = $status;
		}

		$html .= '<a class="btn btn-link btn-sm admin-pages-toolbar__reset" href="?' . html_encode(http_build_query($params)) . '">' . html_encode(__('admin/pages.btn_search_reset')) . '</a>';
	}

	$html .= '</div>';
	$html .= '<a href="?page=page_edit" class="btn btn-primary btn-sm admin-pages-toolbar__add" title="' . html_encode(__('admin/pages.btn_add_title')) . '">';
	$html .= '<i class="fa-solid fa-circle-plus me-1" aria-hidden="true"></i>' . html_encode(__('admin/pages.btn_add'));
	$html .= '</a></div>';

	return $html;
}

/**
 * @param array<int, array<string, mixed>> $pages
 * @param array<string, string> $status_labels
 */
function admin_pages_table(array $pages, array $status_labels, string $delete_confirm): string
{
	if (!$pages) {
		return admin_pages_empty(__('admin/pages.empty_filtered'), 'fa-filter');
	}

	$rows = [];

	foreach ($pages as $page) {
		$pending = (int) ($page['pub_rev'] ?? 0) !== (int) ($page['revision'] ?? 0);
		$rev_args = $pending ? ['rev' => $page['revision']] : [];
		$title = html_encode($page['title'] ?: __('admin/pages.table_noname'));
		$edit_url = '?page=page_edit&amp;id=' . (int) $page['id'];
		$view_url = App::getURL($page['slug'] ?: $page['page_id'], $rev_args);

		$item = '<div class="admin-modules-item">';
		$item .= '<span class="admin-modules-item__content">';
		$item .= '<a href="' . html_encode($edit_url) . '" class="admin-modules-item__link">' . $title . '</a>';
		$item .= '<a class="admin-pages-item__permalink" href="' . html_encode($view_url) . '" title="' . html_encode(__('admin/pages.btn_view')) . '"><i class="fa-solid fa-up-right-from-square" aria-hidden="true"></i><span>' . html_encode(__('admin/pages.btn_view')) . '</span></a>';

		if ($pending) {
			$item .= '<small class="admin-pages-item__revision">' . html_encode(__('admin/pages.table_draft')) . '</small>';
		}

		$item .= '</span></div>';

		$status = (string) ($page['status'] ?? '');
		$meta = admin_pages_status_meta($status);
		$status_cell = '<span class="admin-modules-chip admin-modules-chip--' . html_encode($meta['accent']) . '"><i class="fa-solid ' . html_encode($meta['icon']) . ' me-1" aria-hidden="true"></i>' . html_encode($status_labels[$status] ?? $status) . '</span>';

		$actions = admin_modules_actions_group(
			admin_modules_action_link('?page=page_edit&id=' . (int) $page['id'], 'fa-solid fa-pencil', __('admin/pages.btn_edit'), 'btn-outline-primary')
			. '<button type="submit" name="id" value="' . (int) $page['id'] . '" class="btn btn-outline-danger" title="' . html_encode(__('admin/pages.btn_delete')) . '" onclick="return confirm(\'' . $delete_confirm . '\');"><i class="fa-regular fa-trash-can" aria-hidden="true"></i></button>'
		);

		$rows[] = [
			$item,
			$status_cell,
			'<span class="admin-modules-chip admin-pages-metric-chip">' . html_encode((string) ($page['comments'] ?? 0)) . '</span>',
			'<span class="admin-modules-chip admin-pages-metric-chip">' . html_encode((string) ($page['views'] ?? 0)) . '</span>',
			$actions,
		];
	}

	return admin_modules_table([
		html_encode(__('admin/pages.table_page')),
		html_encode(__('admin/pages.table_status')),
		html_encode(__('admin/pages.table_comments')),
		html_encode(__('admin/pages.table_view')),
		html_encode(__('admin/pages.table_management')),
	], $rows, [
		'caption' => __('admin/pages.list_title'),
		'icon' => 'fa-solid fa-file-lines',
		'accent' => 'primary',
		'layout' => 'pages',
		'class' => 'admin-pages-table-wrap',
		'row_class' => static function ($row, $index) use ($pages) {
			$page = $pages[$index] ?? [];

			return (int) ($page['pub_rev'] ?? 0) !== (int) ($page['revision'] ?? 0) ? 'admin-pages-row--pending-revision' : '';
		},
	]);
}

/* ── Admin page edit ── */

/**
 * @param array<string, mixed> $page
 * @return array<int, array{icon: string, value: string, label: string, variant: string}>
 */
function admin_page_edit_nav(array $tabs, string $active): string
{
	return admin_tabs($tabs, [
		'active' => $active,
		'type' => 'bootstrap',
		'aria_label' => __('admin/page_edit.nav_edit'),
	]);
}

function admin_page_edit_tab_open(string $id, bool $active): string
{
	return admin_tab_pane_open($id, $active, 'admin-page-edit-board__pane');
}

function admin_page_edit_tab_close(): string
{
	return '</div>';
}

/**
 * @param array<string, mixed> $page
 */
function admin_page_edit_build_stats(array $page): array
{
	$is_new = empty($page['page_id']);
	$is_published = !empty($page['pub_rev']) && (int) $page['pub_rev'] === (int) $page['revision'];

	return [
		['icon' => 'fa fa-code-branch', 'value' => (string) (int) ($page['revisions'] ?? 0), 'label' => __('admin/page_edit.stats_revisions'), 'variant' => 'primary'],
		['icon' => 'fa fa-eye', 'value' => (string) (int) ($page['views'] ?? 0), 'label' => __('admin/page_edit.stats_views'), 'variant' => 'info'],
		['icon' => 'fa fa-comments', 'value' => (string) (int) ($page['comments'] ?? 0), 'label' => __('admin/page_edit.stats_comments'), 'variant' => 'warning'],
		[
			'icon' => 'fa fa-file-alt',
			'value' => $is_new ? '&mdash;' : '#' . (int) ($page['revision'] ?? 0),
			'label' => $is_new ? __('admin/page_edit.stats_new') : ($is_published ? __('admin/pages.status_published') : __('admin/pages.status_draft')),
			'variant' => $is_new ? 'secondary' : ($is_published ? 'success' : 'warning'),
		],
	];
}

/**
 * @param array<string, mixed> $page
 */
function admin_page_edit_general_fields(array $page): string
{
	$type_options = '';

	foreach (PAGE_TYPES as $id => $type) {
		$selected = ((string) $id === (string) ($page['type'] ?? '')) ? ' selected' : '';
		$type_options .= '<option value="' . html_encode((string) $id) . '"' . $selected . '>' . html_encode($type) . '</option>';
	}

	$pub_hint = '';

	if (!empty($page['pub_date'])) {
		$pub_hint = ' <small>(' . html_encode(Format::today((int) $page['pub_date'])) . ')</small>';
	}

	$draft_selected = empty($page['pub_rev']) || (int) $page['pub_rev'] !== (int) $page['revision'];

	$html = '<div class="row g-3 admin-page-edit-grid">';
	$html .= '<div class="col-lg-8">';
	$html .= '<label class="form-label" for="page-edit-title">' . html_encode(__('admin/page_edit.title')) . '</label>';
	$html .= '<input class="form-control" id="page-edit-title" name="title" type="text" placeholder="' . html_encode(__('admin/page_edit.title_ph')) . '" value="' . html_encode((string) ($page['title'] ?? '')) . '">';
	$html .= '</div>';
	$html .= '<div class="col-lg-4">';
	$html .= '<label class="form-label" for="page-edit-type">' . html_encode(__('admin/page_edit.type')) . '</label>';
	$html .= '<select name="type" id="page-edit-type" class="form-control">' . $type_options . '</select>';
	$html .= '</div>';
	$html .= '<div class="col-lg-8">';
	$html .= '<label class="form-label" for="page-edit-slug">' . html_encode(__('admin/page_edit.url')) . '</label>';
	$html .= '<div class="input-group">';
	$html .= '<span class="input-group-text">' . html_encode(App::getURL('/')) . '</span>';
	$html .= '<input class="form-control" id="page-edit-slug" name="slug" type="text" placeholder="Slug" value="' . html_encode((string) ($page['slug'] ?? '')) . '">';
	$html .= '</div></div>';
	$html .= '<div class="col-lg-4">';
	$html .= '<label class="form-label" for="page-edit-status">' . html_encode(__('admin/page_edit.visibility')) . '</label>';
	$html .= '<select name="status" id="page-edit-status" class="form-control">';
	$html .= '<option value="published">' . html_encode(__('admin/page_edit.status_published')) . $pub_hint . '</option>';
	$html .= '<option value="draft"' . ($draft_selected ? ' selected' : '') . '>' . html_encode(__('admin/page_edit.status_draft')) . '</option>';
	$html .= '</select></div></div>';

	return $html;
}

/**
 * @param array<string, mixed> $page
 * @param array<int|string, string> $thumbnails
 */
function admin_page_edit_extra_fields(array $page, array $thumbnails): string
{
	$sticky_options = '<option value="0">' . html_encode(__('admin/page_edit.option_dont_sticky')) . '</option>';

	foreach (range(1, 100) as $sticky) {
		$selected = ((int) ($page['sticky'] ?? 0) === $sticky) ? ' selected' : '';
		$sticky_options .= '<option value="' . $sticky . '"' . $selected . '>' . html_encode(__('admin/page_edit.position') . ' ' . $sticky) . '</option>';
	}

	$html = '<div class="admin-page-edit-extra d-none">';
	$html .= '<div class="row g-3 admin-page-edit-grid">';
	$html .= '<div class="col-md-6 col-xl-3">';
	$html .= '<label class="form-label" for="page-edit-category">' . html_encode(__('admin/page_edit.category')) . '</label>';
	$html .= '<input type="text" id="page-edit-category" name="category" value="' . html_encode((string) ($page['category'] ?? '')) . '" class="form-control" data-autocomplete="categorylist" data-autocomplete-instant>';
	$html .= '</div>';
	$html .= '<div class="col-md-6 col-xl-3">';
	$html .= '<label class="form-label" for="page-edit-tags">' . html_encode(__('admin/page_edit.tags')) . '</label>';
	$html .= '<input type="text" id="page-edit-tags" name="category" disabled class="form-control" placeholder="Not implemented">';
	$html .= '</div>';
	$html .= '<div class="col-md-6 col-xl-3">';
	$html .= '<label class="form-label" for="page-edit-redirect">' . html_encode(__('admin/page_edit.redirect')) . '</label>';
	$html .= '<input type="text" id="page-edit-redirect" name="redirect" value="' . html_encode((string) ($page['redirect'] ?? '')) . '" class="form-control">';
	$html .= '</div>';
	$html .= '<div class="col-md-6 col-xl-3">';
	$html .= '<label class="form-label" for="page-edit-pub-date">' . html_encode(__('admin/page_edit.date_on')) . '</label>';
	$html .= '<input type="text" id="page-edit-pub-date" name="pub_date_text" value="' . html_encode(!empty($page['pub_date']) ? date('Y-m-d H:i', (int) $page['pub_date']) : '') . '" class="form-control">';
	$html .= '</div>';
	$html .= '<div class="col-md-6 col-xl-3">';
	$html .= '<label class="form-label" for="page-edit-image" title="' . html_encode(__('admin/page_edit.option_thumbnails_title')) . '">' . html_encode(__('admin/page_edit.option_thumbnail')) . '</label>';
	$html .= '<div id="page-edit-image">' . Widgets::select('image', ['' => __('admin/page_edit.option_thumbnail_auto')] + $thumbnails, $page['image'] ?? '') . '</div>';
	$html .= '</div>';
	$html .= '<div class="col-md-6 col-xl-3">';
	$html .= '<label class="form-label" for="page-edit-comments">' . html_encode(__('admin/page_edit.nav_comments')) . '</label>';
	$html .= '<select name="allow_comments" id="page-edit-comments" class="form-control">';
	$html .= '<option value="1">' . html_encode(__('admin/general.yes')) . '</option>';
	$html .= '<option value="0"' . ((int) ($page['allow_comments'] ?? 1) === 0 ? ' selected' : '') . '>' . html_encode(__('admin/general.no')) . '</option>';
	$html .= '<option value="2"' . ((int) ($page['allow_comments'] ?? 1) === 2 ? ' selected' : '') . '>' . html_encode(__('admin/page_edit.option_closing')) . '</option>';
	$html .= '</select></div>';
	$html .= '<div class="col-md-6 col-xl-3">';
	$html .= '<label class="form-label" for="page-edit-toc">' . html_encode(__('admin/page_edit.option_summary')) . '</label>';
	$html .= '<select name="display_toc" id="page-edit-toc" class="form-control">';
	$html .= '<option value="1">' . html_encode(__('admin/general.yes')) . '</option>';
	$html .= '<option value="0"' . (empty($page['display_toc']) ? ' selected' : '') . '>' . html_encode(__('admin/general.no')) . '</option>';
	$html .= '</select></div>';
	$html .= '<div class="col-md-6 col-xl-3">';
	$html .= '<label class="form-label" for="page-edit-sticky">' . html_encode(__('admin/page_edit.option_sticky')) . '</label>';
	$html .= '<select name="sticky" id="page-edit-sticky" class="form-control" title="' . html_encode(__('admin/page_edit.option_sticky_help')) . '">' . $sticky_options . '</select>';
	$html .= '</div></div></div>';

	return $html;
}

/**
 * @param array<string, mixed> $page
 */
function admin_page_edit_content_fields(array $page): string
{
	$html = '<div class="admin-page-edit-editor">';
	$html .= '<div class="admin-page-edit-editor__toolbar">';
	$html .= '<label class="form-label mb-0" for="editor">' . html_encode(__('admin/page_edit.form_content')) . '</label>';
	$html .= '<div class="admin-page-edit-editor__format">' . Widgets::select('format', ['wysiwyg' => 'WYSIWYG', 'markdown' => 'Markdown+'], $page['format'] ?? '', true, '') . '</div>';
	$html .= '</div>';
	$html .= '<textarea class="form-control admin-page-edit-editor__textarea" id="editor" name="content" placeholder="' . html_encode(__('admin/page_edit.content_ph')) . '">' . html_encode((string) ($page['content'] ?? '')) . '</textarea>';
	$html .= '<p class="admin-page-edit-autosave" id="AutoSaveStatus" aria-live="polite"></p>';
	$html .= '</div>';

	return $html;
}

/**
 * @param array<string, mixed> $page
 * @param array<int|string, string> $thumbnails
 */
function admin_page_edit_form_board(array $page, array $thumbnails, string $delete_confirm, string $copy_confirm): string
{
	$html = '<div class="admin-page-edit-form">';
	$html .= '<div class="admin-settings-section admin-settings-section--grouped admin-page-edit-form__sections">';

	$html .= '<div class="admin-settings-subsection">';
	$html .= '<header class="admin-settings-subsection__header">';
	$html .= '<span class="admin-settings-subsection__icon"><i class="fas fa-sliders-h" aria-hidden="true"></i></span>';
	$html .= '<div class="admin-settings-subsection__heading">';
	$html .= '<h3 class="admin-settings-subsection__title">' . html_encode(__('admin/page_edit.form_general')) . '</h3>';
	$html .= '<p class="admin-settings-subsection__desc">' . html_encode(__('admin/page_edit.form_general_desc')) . '</p>';
	$html .= '</div></header>';
	$html .= '<div class="admin-settings-subsection__body">';
	$html .= admin_page_edit_general_fields($page);
	$html .= '<div class="admin-page-edit-extra-toggle-wrap">';
	$html .= '<button type="button" class="btn btn-sm btn-outline-secondary admin-page-edit-extra-toggle" id="admin-page-edit-extra-toggle">';
	$html .= '<i class="fas fa-cog me-1" aria-hidden="true"></i>' . html_encode(__('admin/page_edit.more_options'));
	$html .= '</button></div>';
	$html .= admin_page_edit_extra_fields($page, $thumbnails);
	$html .= '</div></div>';

	$html .= '<hr class="admin-settings-subsection__divider">';

	$html .= '<div class="admin-settings-subsection">';
	$html .= '<header class="admin-settings-subsection__header">';
	$html .= '<span class="admin-settings-subsection__icon"><i class="fas fa-align-left" aria-hidden="true"></i></span>';
	$html .= '<div class="admin-settings-subsection__heading">';
	$html .= '<h3 class="admin-settings-subsection__title">' . html_encode(__('admin/page_edit.form_content')) . '</h3>';
	$html .= '<p class="admin-settings-subsection__desc">' . html_encode(__('admin/page_edit.form_content_desc')) . '</p>';
	$html .= '</div></header>';
	$html .= '<div class="admin-settings-subsection__body admin-page-edit-form__editor">';
	$html .= admin_page_edit_content_fields($page);
	$html .= '</div></div>';

	$html .= '<footer class="admin-settings-section__footer admin-page-edit-form__footer">';
	$html .= '<div class="admin-page-edit-form__actions">';
	$html .= '<button class="btn btn-primary" type="submit">';
	$html .= '<i class="fas fa-save me-1" aria-hidden="true"></i>' . html_encode(__('admin/general.btn_save'));
	$html .= '</button>';

	if (!empty($page['page_id'])) {
		$html .= ' <button class="btn btn-outline-danger" name="delete" value="delete" type="submit" onclick="return confirm(\'' . $delete_confirm . '\');">';
		$html .= '<i class="fas fa-trash-alt me-1" aria-hidden="true"></i>' . html_encode(__('admin/general.btn_delete'));
		$html .= '</button>';
		$html .= ' <button class="btn btn-outline-info" name="copy" value="copy" type="submit" onclick="return confirm(\'' . $copy_confirm . '\');">';
		$html .= '<i class="fas fa-copy me-1" aria-hidden="true"></i>' . html_encode(__('admin/page_edit.make_copy'));
		$html .= '</button>';
	}

	$html .= ' <a class="btn btn-outline-secondary" href="?page=pages">' . html_encode(__('admin/menu.btn_cancel')) . '</a>';
	$html .= '</div></footer></div></div>';

	return $html;
}

/**
 * @return array<int, string>
 */
function admin_page_edit_history_columns(): array
{
	return [
		html_encode(__('admin/page_edit.table_compare')),
		'#',
		html_encode(__('admin/page_edit.table_date')),
		html_encode(__('admin/page_edit.table_eta')),
		html_encode(__('admin/page_edit.table_author')),
		html_encode(__('admin/page_edit.table_size')),
		html_encode(__('admin/page_edit.table_attch')),
		html_encode(__('admin/modules.table_action')),
	];
}

/**
 * @param array<string, mixed> $row
 * @param array<string, mixed> $page
 * @return array{class?: string, cells: array<int, string>}
 */
function admin_page_edit_history_row(array $row, array $page, int $index, int $count): array
{
	$compare = '<input type="radio" name="rev1" value="' . (int) $row['revision'] . '"' . ($index + 1 === $count ? ' disabled' : '') . '> ';
	$compare .= '<input type="radio" name="rev2" value="' . (int) $row['revision'] . '"' . ($index === 0 ? ' disabled' : '') . '>';

	$attachments = implode('<br>', (array) @unserialize($row['attached_files'] ?? ''));
	$actions = admin_modules_action_link(
		'?page=page_edit&id=' . (int) $row['id'],
		'fa fa-pencil-alt',
		__('admin/page_edit.open_editor'),
		'btn-outline-primary'
	);
	$actions .= admin_modules_action_link(
		App::getURL('pageview', ['id' => (int) $row['page_id'], 'rev' => (int) $row['revision']]),
		'fa fa-eye',
		__('admin/general.see'),
		'btn-outline-secondary'
	);

	$result = [
		'cells' => [
			'<span class="admin-page-edit-compare-inputs">' . $compare . '</span>',
			(string) (int) $row['revision'],
			html_encode(Format::today((int) $row['posted'])),
			html_encode((string) ($row['status'] ?? '')),
			html_encode((string) ($row['username'] ?? '')),
			html_encode((string) ($row['size'] ?? '')),
			$attachments !== '' ? $attachments : '&mdash;',
			admin_modules_table_actions_cell($actions),
		],
	];

	if ((int) ($page['pub_rev'] ?? 0) === (int) $row['revision']) {
		$result['class'] = 'admin-page-edit-history-row--published';
	} elseif ((int) ($page['revision'] ?? 0) === (int) $row['revision']) {
		$result['class'] = 'admin-page-edit-history-row--current';
	}

	return $result;
}

/**
 * @param array<string, mixed> $page
 */
function admin_page_edit_history_board(array $page): string
{
	$rows_data = Db::QueryAll(
		'SELECT r.*, p.*, a.username, LENGTH(r.content) as size
		 FROM {pages} as p
		 JOIN {pages_revs} as r ON r.page_id = p.page_id
		 LEFT JOIN {users} as a ON author = a.id
		 WHERE p.page_id = ?
		 ORDER by revision DESC',
		$page['page_id']
	) ?: [];

	if (!$rows_data) {
		return admin_settings_empty(__('admin/page_edit.history_empty'), 'fa-history');
	}

	$rows = [];
	$count = count($rows_data);

	foreach ($rows_data as $index => $row) {
		$entry = admin_page_edit_history_row($row, $page, $index, $count);
		$row_html = $entry['cells'];

		if (!empty($entry['class'])) {
			$row_html['_class'] = $entry['class'];
		}

		$rows[] = $row_html;
	}

	$compare_btn = '<button name="compare" class="btn btn-primary btn-sm" value="1" type="submit">';
	$compare_btn .= '<i class="fas fa-not-equal me-1" aria-hidden="true"></i>' . html_encode(__('admin/page_edit.btn_compare'));
	$compare_btn .= '</button>';

	return admin_modules_table(admin_page_edit_history_columns(), $rows, [
		'caption' => __('admin/page_edit.nav_history'),
		'icon' => 'fa fa-history',
		'accent' => 'info',
		'layout' => 'page_edit',
		'toolbar_actions' => $compare_btn,
		'class' => 'admin-page-edit-history-wrap',
	]);
}

/**
 * @param array<string, mixed> $page
 */
function admin_page_edit_diff_board(array $page, int $rev1, int $rev2): string
{
	$html = '<div id="diffbox" class="admin-page-edit-diff">';

	if ($rev1 && $rev2) {
		$rev = Db::QueryAll(
			'SELECT revision, content, posted FROM {pages_revs} WHERE page_id = ? AND (revision = ? OR revision = ?)',
			$page['page_id'],
			$rev1,
			$rev2,
			true
		);

		if (count($rev) !== 2) {
			App::setWarning(__('admin/page_edit.warning_rev_invalid'));
		} else {
			$diff = (new \FineDiff\FineDiff($rev[$rev2]['content'], $rev[$rev1]['content'], \FineDiff\FineDiff::$wordGranularity))->renderDiffToHTML();
			$d1 = '<strong><small>' . Format::today($rev[$rev1]['posted'], true) . '</small></strong>';
			$d2 = '<strong><small>' . Format::today($rev[$rev2]['posted'], true) . '</small></strong>';

			$html .= '<div class="admin-page-edit-diff__legend">';
			$html .= '<p><ins>' . html_encode(__('admin/page_edit.red')) . '</ins> : '
				. html_encode(__('admin/page_edit.present_in')) . ' ' . (int) $rev1 . ' (' . $d1 . ') '
				. html_encode(__('admin/page_edit.but_not_in')) . ' ' . (int) $rev2 . ' (' . $d2 . ')</p>';
			$html .= '<p><del>' . html_encode(__('admin/page_edit.green')) . '</del> : '
				. html_encode(__('admin/page_edit.present_in')) . ' ' . (int) $rev2 . ' (' . $d2 . ') '
				. html_encode(__('admin/page_edit.but_not_in')) . ' ' . (int) $rev1 . ' (' . $d1 . ')</p>';
			$html .= '</div>';
			$html .= '<div class="admin-page-edit-diff__pane pane diff">' . $diff . '</div>';
			$html .= '<script>document.querySelector(\'[href="#diff"]\')?.click();</script>';
		}
	} else {
		$html .= admin_settings_empty(__('admin/page_edit.diff_empty'), 'fa-not-equal');
		$html .= '<script>document.querySelector(\'[href="#diff"]\')?.classList.add(\'d-none\');</script>';
	}

	return $html . '</div>';
}

/* ── Admin avatars ── */

/**
 * Navigation par onglets de la page avatars admin.
 */
function admin_avatars_nav(string $view): string
{
	$tabs = [
		'library' => [
			'label' => __('admin/avatars.tab_library'),
			'icon' => 'fa-images',
			'href' => '?page=avatars&view=library',
		],
		'create' => [
			'label' => __('admin/avatars.tab_create'),
			'icon' => 'fa-folder-plus',
			'href' => '?page=avatars&view=create',
		],
	];

	return admin_tabs($tabs, [
		'active' => $view,
		'type' => 'link',
		'aria_label' => __('admin/avatars.main_title'),
	]);
}

function admin_avatars_tab_open(string $id, bool $active): string
{
	return admin_tab_pane_open($id, $active, 'admin-avatars-board__pane');
}

function admin_avatars_tab_close(): string
{
	return '</div>';
}

function admin_avatars_empty(string $message, string $icon = 'fa-images'): string
{
	return admin_settings_empty($message, $icon);
}

/**
 * @return array<int, array{name: string, avatars: array<int, array{path: string, url: string, name: string}>, count: int}>
 */
function admin_avatars_collect(string $dir): array
{
	$categories = [];

	if ($dirs = glob(rtrim($dir, '/\\') . '/*', GLOB_ONLYDIR)) {
		foreach ($dirs as $cat_dir) {
			$cat = basename($cat_dir);
			$avatars = [];

			if ($files = glob($cat_dir . '/*.{jpg,jpeg,png,gif}', GLOB_BRACE)) {
				foreach ($files as $avatar) {
					$avatars[] = [
						'path' => $avatar,
						'url' => App::getAsset(substr($avatar, strlen(ROOT_DIR))),
						'name' => basename($avatar),
					];
				}
			}

			$categories[] = [
				'name' => $cat,
				'avatars' => $avatars,
				'count' => count($avatars),
			];
		}
	}

	usort($categories, static fn(array $a, array $b): int => strcasecmp($a['name'], $b['name']));

	return $categories;
}

/**
 * @param array<int, array{name: string, avatars: array<int, array{path: string, url: string, name: string}>, count: int}> $categories
 * @return array<int, array{icon: string, value: string, label: string, variant: string}>
 */
function admin_avatars_build_stats(array $categories): array
{
	$total_avatars = 0;
	$empty_categories = 0;

	foreach ($categories as $category) {
		$total_avatars += $category['count'];

		if ($category['count'] === 0) {
			$empty_categories++;
		}
	}

	$category_count = count($categories);
	$average = $category_count > 0 ? (string) round($total_avatars / $category_count, 1) : '0';

	return [
		['icon' => 'fa fa-folder', 'value' => (string) $category_count, 'label' => __('admin/avatars.stats_categories'), 'variant' => 'primary'],
		['icon' => 'fa fa-user-circle', 'value' => (string) $total_avatars, 'label' => __('admin/avatars.stats_avatars'), 'variant' => 'success'],
		['icon' => 'fa fa-images', 'value' => $average, 'label' => __('admin/avatars.stats_average'), 'variant' => 'info'],
		['icon' => 'fa fa-folder-open', 'value' => (string) $empty_categories, 'label' => __('admin/avatars.stats_empty'), 'variant' => 'warning'],
	];
}

/**
 * Supprime un avatar d'une catégorie existante.
 */
function admin_avatars_delete_file(string $dir, string $cat, string $filename): bool
{
	$filename = basename(Format::safeFilename($filename));

	if ($filename === '' || $filename === 'index.html') {
		return false;
	}

	if (!preg_match('/\.(jpe?g|png|gif)$/i', $filename)) {
		return false;
	}

	$cat_dir = rtrim($dir, '/\\') . '/' . $cat;
	$path = $cat_dir . '/' . $filename;
	$real_dir = realpath($cat_dir);
	$real_path = realpath($path);

	if ($real_dir === false || $real_path === false || strpos($real_path, $real_dir) !== 0) {
		return false;
	}

	return is_file($real_path) && @unlink($real_path);
}

/**
 * Supprime une catégorie d'avatars et tous les fichiers qu'elle contient.
 */
function admin_avatars_delete_category(string $dir, string $cat): bool
{
	if (!preg_match('#^[-a-zA-Z0-9_]+$#', $cat)) {
		return false;
	}

	$root = realpath(rtrim($dir, '/\\'));
	$cat_dir = rtrim($dir, '/\\') . '/' . $cat;
	$real_cat = realpath($cat_dir);

	if ($root === false || $real_cat === false || !is_dir($real_cat) || dirname($real_cat) !== $root) {
		return false;
	}

	return rrmdir($real_cat);
}

function admin_avatars_max_size(): int
{
	return 512;
}

/**
 * Enregistre un avatar uploadé en limitant sa taille à admin_avatars_max_size().
 */
function admin_avatars_save_image(string $tmp_path, string $dest_path): bool
{
	$info = @getimagesize($tmp_path);

	if (!$info || !in_array($info[2], [IMAGETYPE_GIF, IMAGETYPE_JPEG, IMAGETYPE_PNG], true)) {
		return false;
	}

	$types = [
		IMAGETYPE_GIF => 'gif',
		IMAGETYPE_JPEG => 'jpeg',
		IMAGETYPE_PNG => 'png',
	];
	$type = $types[$info[2]];
	$max = admin_avatars_max_size();
	[$width, $height] = [$info[0], $info[1]];

	if ($width <= $max && $height <= $max) {
		if (is_uploaded_file($tmp_path)) {
			return move_uploaded_file($tmp_path, $dest_path);
		}

		return @copy($tmp_path, $dest_path);
	}

	$ratio = min($max / $width, $max / $height);
	$new_width = max(1, (int) round($width * $ratio));
	$new_height = max(1, (int) round($height * $ratio));
	$source = @call_user_func('imagecreatefrom' . $type, $tmp_path);

	if (!$source) {
		return false;
	}

	$dest = imagecreatetruecolor($new_width, $new_height);

	if ($type === 'png' || $type === 'gif') {
		imagealphablending($dest, false);
		imagesavealpha($dest, true);
	}

	imagecopyresampled($dest, $source, 0, 0, 0, 0, $new_width, $new_height, $width, $height);
	$saved = call_user_func('image' . $type, $dest, $dest_path);

	imagedestroy($source);
	imagedestroy($dest);

	if ($saved && is_uploaded_file($tmp_path)) {
		@unlink($tmp_path);
	}

	return $saved;
}

/**
 * Modal réutilisable pour prévisualiser une image au clic.
 */
function admin_image_preview_modal(array $options = []): string
{
	static $rendered = false;

	if ($rendered) {
		return '';
	}

	$rendered = true;
	$id = $options['id'] ?? 'admin-image-preview-modal';
	$max = (int) ($options['max_size'] ?? admin_avatars_max_size());
	$title = html_encode($options['title'] ?? __('admin/general.image_preview_title'));

	return '<div id="' . html_encode($id) . '" class="modal fade admin-image-preview-modal" tabindex="-1" aria-hidden="true">'
		. '<div class="modal-dialog modal-dialog-centered admin-image-preview-modal__dialog">'
		. '<div class="modal-content border-0 shadow">'
		. '<div class="modal-header py-2">'
		. '<h6 class="modal-title admin-image-preview-modal__title text-truncate">' . $title . '</h6>'
		. '<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="' . html_encode(__('messages/form.cancel')) . '"></button>'
		. '</div>'
		. '<div class="modal-body admin-image-preview-modal__body p-2 text-center">'
		. '<img class="admin-image-preview-modal__img admin-image-preview-modal__img--loading" src="data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7" alt="">'
		. '</div></div></div></div>';
}

function admin_avatars_grid(array $avatars, string $delete_confirm): string
{
	if (!$avatars) {
		return admin_panel_empty(__('admin/avatars.category_empty'), 'fa-image', [
			'class' => 'admin-avatars-category__empty',
		]);
	}

	$view_label = html_encode(__('admin/avatars.view_avatar'));
	$html = '<div class="admin-avatars-grid">';

	foreach ($avatars as $avatar) {
		$html .= '<figure class="admin-avatars-card">';
		$html .= '<button type="submit" class="admin-avatars-card__delete" name="delete_avatar" value="' . html_encode($avatar['name']) . '" title="' . html_encode(__('admin/general.btn_delete')) . '" onclick="return confirm(\'' . $delete_confirm . '\');">';
		$html .= '<i class="far fa-trash-alt" aria-hidden="true"></i>';
		$html .= '<span class="visually-hidden">' . html_encode(__('admin/general.btn_delete')) . '</span>';
		$html .= '</button>';
		$html .= '<button type="button" class="admin-avatars-card__preview admin-avatars-card__preview--clickable" data-admin-image-preview="' . html_encode($avatar['url']) . '" data-admin-image-title="' . html_encode($avatar['name']) . '" title="' . $view_label . '" aria-label="' . $view_label . '">';
		$html .= '<img src="' . html_encode($avatar['url']) . '" alt="' . html_encode($avatar['name']) . '" loading="lazy">';
		$html .= '</button>';
		$html .= '<figcaption class="admin-avatars-card__title">' . html_encode($avatar['name']) . '</figcaption>';
		$html .= '</figure>';
	}

	return $html . '</div>';
}

function admin_avatars_accept_types(): string
{
	return '.jpg,.jpeg,.png,.gif,image/jpeg,image/png,image/gif';
}

/**
 * Génère un nom de fichier sûr quand le nom d'origine est absent ou invalide.
 */
function admin_avatars_fallback_filename(string $tmp_path, int $index = 0): string
{
	$ext = 'png';
	$info = $tmp_path !== '' ? @getimagesize($tmp_path) : false;

	if ($info) {
		$ext_map = [
			IMAGETYPE_GIF => 'gif',
			IMAGETYPE_JPEG => 'jpg',
			IMAGETYPE_PNG => 'png',
		];
		$ext = $ext_map[$info[2]] ?? 'png';
	}

	return 'avatar-' . time() . '-' . $index . '.' . $ext;
}

/**
 * Normalise la structure $_FILES pour un champ upload[].
 *
 * @return array{name: array<int, string>, type: array<int, string>, tmp_name: array<int, string>, error: array<int, int>, size: array<int, int>}|null
 */
function admin_avatars_normalize_upload(array $files): ?array
{
	if (empty($files['name'])) {
		return null;
	}

	if (!is_array($files['name'])) {
		return [
			'name' => [(string) $files['name']],
			'type' => [(string) ($files['type'] ?? '')],
			'tmp_name' => [(string) ($files['tmp_name'] ?? '')],
			'error' => [(int) ($files['error'] ?? UPLOAD_ERR_NO_FILE)],
			'size' => [(int) ($files['size'] ?? 0)],
		];
	}

	return $files;
}

/**
 * Prépare un nom de fichier d'avatar à partir du nom d'origine ou du fichier temporaire.
 */
function admin_avatars_resolve_filename(string $original_name, string $tmp_path, int $index = 0): string
{
	$original_name = trim(basename(str_replace('\\', '/', $original_name)));
	$filename = $original_name !== '' ? basename(Format::safeFilename($original_name)) : '';

	if ($filename === '' || !preg_match('/\.(jpe?g|gif|png)$/i', $filename)) {
		$filename = admin_avatars_fallback_filename($tmp_path, $index);
	}

	return $filename;
}

/**
 * Traite l'upload d'avatars dans une catégorie existante.
 */
function admin_avatars_process_uploads(string $dir, string $cat): void
{
	if (empty($_FILES['upload'])) {
		return;
	}

	$files = admin_avatars_normalize_upload($_FILES['upload']);

	if (!$files) {
		return;
	}

	foreach ($files['name'] as $index => $name) {
		$tmp_path = (string) ($files['tmp_name'][$index] ?? '');
		$display_name = trim((string) $name) !== '' ? (string) $name : admin_avatars_fallback_filename($tmp_path, (int) $index);
		$filename = admin_avatars_resolve_filename((string) $name, $tmp_path, (int) $index);
		$path = $dir . $cat . '/' . $filename;

		if ((int) ($files['error'][$index] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
			App::setWarning(__('admin/avatars.alert_upload_error', ['%name%' => $display_name]), true);
		}
		elseif (!preg_match('/\.(jpg|gif|png)$/i', $filename) || !in_array(@getimagesize($tmp_path)[2], [1, 2, 3], true)) {
			App::setWarning(__('admin/avatars.alert_invalid_format', ['%name%' => $display_name]), true);
		}
		elseif (file_exists($path)) {
			App::setWarning(__('admin/avatars.alert_file_exist', ['%path%' => $path]), true);
		}
		elseif (admin_avatars_save_image($tmp_path, $path)) {
			chmod($path, 0755);
			App::setSuccess(__('admin/avatars.alert_avatar_added', ['%name%' => $display_name]), true);
		}
		else {
			App::setWarning(__('admin/avatars.alert_upload_error', ['%name%' => $display_name]), true);
		}
	}
}

/**
 * Corps d'une catégorie d'avatars (grille + zone de dépôt).
 *
 * @param array{name: string, avatars: array<int, array{path: string, url: string, name: string}>, count: int} $category
 */
function admin_avatars_category_body(array $category, string $delete_confirm): string
{
	$html = admin_avatars_grid($category['avatars'], $delete_confirm);
	$html .= admin_file_dropzone([
		'title' => __('admin/avatars.drop_title_short'),
		'hint' => __('admin/avatars.drop_hint_short'),
		'browse' => __('admin/avatars.btn_upload'),
		'accept' => admin_avatars_accept_types(),
		'auto_submit' => true,
		'compact' => true,
		'icon' => 'fa-cloud-upload-alt',
		'class' => 'admin-avatars-category__dropzone',
	]);

	return $html;
}

/**
 * @param array{name: string, avatars: array<int, array{path: string, url: string, name: string}>, count: int} $category
 */
function admin_avatars_category_panel(array $category, string $delete_confirm, bool $expanded = false): string
{
	$cat = $category['name'];
	$collapse_id = admin_collapsible_slug('avatar-cat-', $cat);
	$delete_category_confirm = html_encode(__('admin/avatars.alert_delete_category', [
		'%cat%' => $cat,
		'%count%' => (string) $category['count'],
	]));
	$heading = '<span class="admin-modules-table__caption admin-avatars-category__heading">';
	$heading .= '<span class="admin-modules-table__caption-icon admin-modules-table__caption-icon--info">';
	$heading .= '<i class="fa fa-folder" aria-hidden="true"></i></span>';
	$heading .= '<div class="admin-avatars-category__titles">';
	$heading .= '<h3 class="admin-avatars-category__title">' . html_encode($cat) . '</h3>';
	$heading .= '<p class="admin-avatars-category__meta">' . html_encode(__('admin/avatars.category_count', ['%count%' => (string) $category['count']])) . '</p>';
	$heading .= '</div></span>';

	$html = '<article class="admin-avatars-category admin-collapsible" data-avatars-category="' . html_encode($cat) . '">';
	$html .= '<form method="post" enctype="multipart/form-data" class="admin-avatars-category__form">';
	$html .= '<input type="hidden" name="categorie" value="' . html_encode($cat) . '">';
	$html .= '<header class="admin-modules-table__toolbar admin-avatars-category__header admin-collapsible__header">';
	$html .= admin_collapsible_toggle($collapse_id, $heading, $expanded, [
		'class' => 'admin-avatars-category__toggle',
		'label' => __('admin/avatars.btn_toggle_category'),
	]);
	$html .= '<div class="admin-modules-table__toolbar-actions admin-avatars-category__actions">';
	$html .= '<button type="submit" class="btn btn-sm btn-outline-danger" name="delete_category" value="1" onclick="return confirm(\'' . $delete_category_confirm . '\');">';
	$html .= '<i class="far fa-trash-alt me-1" aria-hidden="true"></i>' . html_encode(__('admin/avatars.btn_delete_category'));
	$html .= '</button>';
	$html .= '</div></header>';
	$html .= admin_collapsible_body_open($collapse_id, $expanded, 'admin-avatars-category__body');
	$html .= admin_avatars_category_body($category, $delete_confirm);
	$html .= admin_collapsible_body_close();
	$html .= '</form></article>';

	return $html;
}

/**
 * Réponse JSON pour l'upload ou la suppression AJAX d'avatars.
 *
 * @param array<int, array{name: string, avatars: array<int, array{path: string, url: string, name: string}>, count: int}> $categories
 * @param array<int, array{icon: string, value: string, label: string, variant: string}> $stats
 */
function admin_avatars_json_response(array $categories, array $stats, string $category_name, string $delete_confirm): void
{
	$category = null;

	foreach ($categories as $item) {
		if ($item['name'] === $category_name) {
			$category = $item;
			break;
		}
	}

	if (!$category) {
		http_response_code(404);
	}

	header('Content-Type: application/json; charset=utf-8');
	echo json_encode([
		'ok' => $category !== null,
		'category' => $category_name,
		'body' => $category ? admin_avatars_category_body($category, $delete_confirm) : '',
		'meta' => $category ? __('admin/avatars.category_count', ['%count%' => (string) $category['count']]) : '',
		'stats' => admin_stat_grid($stats, ['variant' => 'kpi', 'class' => 'mb-0']),
		'alerts' => App::renderAlertsHtml(),
	], JSON_UNESCAPED_UNICODE);
	exit;
}

/**
 * @param array<int, array{name: string, avatars: array<int, array{path: string, url: string, name: string}>, count: int}> $categories
 */
function admin_avatars_library_board(array $categories, string $delete_confirm): string
{
	if (!$categories) {
		return '<div class="admin-avatars-content-wrap admin-avatars-content-wrap--empty">'
			. admin_avatars_empty(__('admin/avatars.empty_library'))
			. '</div>';
	}

	$html = '<div class="admin-avatars-content-wrap">';

	foreach ($categories as $category) {
		$html .= admin_avatars_category_panel($category, $delete_confirm);
	}

	return $html . '</div>';
}

function admin_avatars_create_board(): string
{
	$html = '<div class="admin-avatars-content-wrap admin-avatars-content-wrap--create">';
	$html .= '<div class="admin-avatars-create-panel">';
	$html .= '<form method="post" enctype="multipart/form-data" role="form" class="form-horizontal admin-settings-grouped-form admin-avatars-create-form" id="admin-avatars-create-form">';
	$html .= '<section class="admin-settings-section admin-settings-section--grouped">';
	$html .= '<div class="admin-settings-section__body admin-settings-section__body--grouped">';

	$html .= '<div class="admin-settings-subsection">';
	$html .= '<header class="admin-settings-subsection__header">';
	$html .= '<span class="admin-settings-subsection__icon"><i class="fas fa-folder-plus" aria-hidden="true"></i></span>';
	$html .= '<div class="admin-settings-subsection__heading">';
	$html .= '<h3 class="admin-settings-subsection__title">' . html_encode(__('admin/avatars.create_section_category')) . '</h3>';
	$html .= '<p class="admin-settings-subsection__desc">' . html_encode(__('admin/avatars.create_intro')) . '</p>';
	$html .= '</div></header>';
	$html .= '<div class="admin-settings-subsection__body">';
	$html .= admin_form_field_row(
		__('admin/avatars.catname'),
		'<input class="form-control" id="avatar-category-name" name="categorie" type="text" pattern="[-a-zA-Z0-9_]+" required autocomplete="off" placeholder="' . html_encode(__('admin/avatars.catname_placeholder')) . '">',
		['for' => 'avatar-category-name', 'hint' => __('admin/avatars.create_hint')]
	);
	$html .= '</div></div>';

	$html .= '<hr class="admin-settings-subsection__divider">';

	$html .= '<div class="admin-settings-subsection">';
	$html .= '<header class="admin-settings-subsection__header">';
	$html .= '<span class="admin-settings-subsection__icon"><i class="fas fa-images" aria-hidden="true"></i></span>';
	$html .= '<div class="admin-settings-subsection__heading">';
	$html .= '<h3 class="admin-settings-subsection__title">' . html_encode(__('admin/avatars.create_section_upload')) . '</h3>';
	$html .= '<p class="admin-settings-subsection__desc">' . html_encode(__('admin/avatars.create_upload_intro')) . '</p>';
	$html .= '</div></header>';
	$html .= '<div class="admin-settings-subsection__body admin-avatars-create-form__dropzone-body">';
	$html .= admin_file_dropzone([
		'title' => __('admin/avatars.drop_title'),
		'hint' => __('admin/avatars.drop_hint'),
		'browse' => __('admin/avatars.drop_browse'),
		'summary' => __('admin/avatars.drop_summary'),
		'accept' => admin_avatars_accept_types(),
		'preview' => true,
		'icon' => 'fa-cloud-upload-alt',
		'class' => 'admin-avatars-create-form__dropzone',
	]);
	$html .= '</div></div>';

	$html .= '</div>';
	$html .= '<footer class="admin-settings-section__footer">';
	$html .= '<div class="text-center">';
	$html .= '<button class="btn btn-primary" name="create" value="1" type="submit">';
	$html .= '<i class="fas fa-plus me-1" aria-hidden="true"></i>' . html_encode(__('admin/avatars.btn_create'));
	$html .= '</button>';
	$html .= '<a class="btn btn-outline-secondary" href="?page=avatars&view=library">' . html_encode(__('admin/menu.btn_cancel')) . '</a>';
	$html .= '</div></footer>';
	$html .= '</section></form></div></div>';

	return $html;
}

/**
 * @param array<int, array{name: string, avatars: array<int, array{path: string, url: string, name: string}>, count: int}> $categories
 */
function admin_avatars_tab_body(array $categories, string $view, string $delete_confirm): string
{
	$html = admin_avatars_tab_open('library', $view === 'library');
	$html .= '<div id="avatars-content" class="admin-avatars-content"><div id="content">';
	$html .= admin_avatars_library_board($categories, $delete_confirm);
	$html .= '</div></div>';
	$html .= admin_avatars_tab_close();
	$html .= admin_avatars_tab_open('create', $view === 'create');
	$html .= admin_avatars_create_board();
	$html .= admin_avatars_tab_close();

	return $html;
}

function getCurrentPageInfo($type = 'both')
 {
	 $page = App::GET('page');

	 if ($page === 'modules' && ($pluginId = App::GET('plugin', ''))) {
		 $module = App::getModule($pluginId);
		 if ($module) {
			 $icon = fa_icon_classes('fa-gears');
			 $title = $module->infos->name;
			 $description = $module->infos->name . ' v' . $module->infos->version;

			 switch ($type) {
				 case 'icon':
					 return $icon;
				 case 'title':
					 return $title;
				 case 'description':
					 return $description;
				 case 'both':
					 return ['icon' => $icon, 'title' => $title];
				 case 'all':
					 return ['icon' => $icon, 'title' => $title, 'description' => $description];
				 case 'html':
				 default:
					 return fa_icon_html($icon, 'solid', ['me-3']) . html_encode($title);
			 }
		 }
	 }

	 if ($page === 'user_view' && ($user = App::getUser(App::GET('id')))) {
		 $icon = fa_icon_classes('fa-user');
		 $title = __('admin/user_view.page_title', ['%user%' => $user->username]);
		 $description = __('admin/user_view.page_description');

		 switch ($type) {
			 case 'icon':
				 return $icon;
			 case 'title':
				 return $title;
			 case 'description':
				 return $description;
			 case 'both':
				 return ['icon' => $icon, 'title' => $title];
			 case 'all':
				 return ['icon' => $icon, 'title' => $title, 'description' => $description];
			 case 'html':
			 default:
				 return fa_icon_html('fa-user', 'solid', ['me-3']) . html_encode($title);
		 }
	 }
	 
	 $pageIcons = [
		 '' => 'fa-circle-info',
		 'index' => 'fa-circle-info',
		 'settings' => 'fa-keyboard',
		 'reports' => 'fa-circle-exclamation',
		 'servers' => 'fa-server',
		 'page_edit' => 'fa-file',
		 'pages' => 'fa-file-lines',
		 'menu' => 'fa-list',
		 'gallery' => 'fa-images',
		 'avatars' => 'fa-face-grin-squint-tears',
		 'downloads' => 'fa-file-arrow-down',
		 'forums' => 'fa-list',
		 'comments' => 'fa-comments',
		 'broadcast' => 'fa-envelope',
		 'users' => 'fa-users',
		 'user_view' => 'fa-user',
		 'user_delete' => 'fa-user-slash',
		 'groups' => 'fa-layer-group',
		 'history' => 'fa-user-secret',
		 'security' => 'fa-user-slash',
		 'modules' => 'fa-gears',
		 'backup' => 'fa-file-zipper',
		 'file_editor' => 'fa-file-code',
	 ];
	 
	 $pageTitles = [
		 '' => __('admin/menu.title_info'),
		 'index' => __('admin/menu.title_info'),
		 'settings' => __('admin/menu.sub_config'),
		 'reports' => __('admin/menu.sub_report'),
		 'servers' => __('admin/menu.sub_servers'),
		 'page_edit' => __('admin/menu.sub_newpage'),
		 'pages' => __('admin/menu.sub_pages'),
		 'menu' => __('admin/menu.sub_menu'),
		 'gallery' => __('admin/menu.sub_lib_media'),
		 'avatars' => __('admin/menu.sub_lib_avatar'),
		 'downloads' => __('admin/menu.sub_download'),
		 'forums' => __('admin/menu.sub_forum'),
		 'comments' => __('admin/menu.sub_comments'),
		 'broadcast' => __('admin/menu.sub_newsletter'),
		 'users' => __('admin/menu.sub_members'),
		 'user_view' => __('admin/user_view.page_title_short'),
		 'user_delete' => __('admin/user_delete.title'),
		 'groups' => __('admin/menu.sub_groups'),
		 'history' => __('admin/menu.sub_log_admin'),
		 'security' => __('admin/menu.sub_security'),
		 'modules' => __('admin/menu.sub_modules'),
		 'backup' => __('admin/menu.sub_backup'),
		 'file_editor' => __('admin/menu.sub_files_editor'),
	 ];
	 
	 $pageDescriptions = [
		 '' => __('admin/page_meta.dashboard'),
		 'index' => __('admin/page_meta.dashboard'),
		 'settings' => __('admin/page_meta.settings'),
		 'reports' => __('admin/page_meta.reports'),
		 'servers' => __('admin/page_meta.servers'),
		 'page_edit' => __('admin/page_meta.page_edit'),
		 'pages' => __('admin/page_meta.pages'),
		 'menu' => __('admin/page_meta.menu'),
		 'gallery' => __('admin/page_meta.gallery'),
		 'avatars' => __('admin/page_meta.avatars'),
		 'downloads' => __('admin/page_meta.downloads'),
		 'forums' => __('admin/page_meta.forums'),
		 'comments' => __('admin/page_meta.comments'),
		 'broadcast' => __('admin/page_meta.broadcast'),
		 'users' => __('admin/page_meta.users'),
		 'user_view' => __('admin/page_meta.user_view'),
		 'user_delete' => __('admin/page_meta.user_delete'),
		 'groups' => __('admin/page_meta.groups'),
		 'history' => __('admin/page_meta.history'),
		 'security' => __('admin/page_meta.security'),
		 'modules' => __('admin/page_meta.modules'),
		 'backup' => __('admin/page_meta.backup'),
		 'file_editor' => __('admin/page_meta.file_editor'),
	 ];
	 
	 $iconRef = $pageIcons[$page] ?? 'fa-gauge-high';
	 $icon = fa_icon_classes($iconRef);
	 $title = $pageTitles[$page] ?? ucfirst($page ?: 'Dashboard');
	 $description = $pageDescriptions[$page] ?? __('admin/page_meta.default');
	 
	 switch ($type) {
		 case 'icon':
			 return $icon;
		 case 'title':
			 return $title;
		 case 'description':
			 return $description;
		 case 'both':
			 return ['icon' => $icon, 'title' => $title];
		 case 'all':
			 return ['icon' => $icon, 'title' => $title, 'description' => $description];
		 case 'html':
		 default:
			 return fa_icon_html($iconRef, 'solid', ['me-3']) . html_encode($title);
	 }
 } 

/**
 * Undocumented
 */
function geoip_country_code($hostname)
{
	try {
		static $reader = null;
		$reader = $reader ?? new GeoIp2\Database\Reader(ROOT_DIR.'/includes/lib-data/GeoLite2.mmdb');
		$record = $reader->country($hostname);
		return $record->country->isoCode; // $record->country->name
	} catch(Throwable $e) {
		return null;
	}
}


/**
 *  Verify if current user is granted a permission
 *  If $name is empty, the function will return true if the user is logged in, false otherwise.
 *
 *  @param string $name
 *  @param integer|null $rel_id
 *  @param boolean $redirect redirect to 403 on failure
 *  @return boolean
 */
function has_permission($name = '', $rel_id = null, $redirect = false)  // Si $name est vide alors on test si logged in.
{
	$current_user = App::getCurrentUser();
	$name = (string)$name;

	if (is_bool($rel_id)) {$redirect = $rel_id; $rel_id = null ;} // temp fix

	if (($name === '' && $current_user->id) || App::groupHasPermission($current_user->group_id, $name, $rel_id)) {
		return true;
	} elseif ($redirect == true) {
		throw new PermissionDenied('URL: ' . App::getURL($_SERVER['REQUEST_URI']));
	} else {
		return false;
	}
}


/**
 *  Return avatar URL (gravatar or local)
 *
 *  @param array|int $user user id or array containing avatar email and/or email
 *  @param integer $size the size to return. Optional
 *  @param string $url_only return url instead of img tag
 *  @return string
 */
function get_avatar($user, $size = 85, $url_only = false)
{
	if (is_scalar($user)) {
		$user = App::getUser($user) ?: [];
	}
	if ($user instanceof Evo\Model) {
		$user = $user->toArray();
	}
	return Evo\Avatars::getAvatar($user, $size, $url_only || $size === true);
}


/**
 *  Recursive remove directory. Similar to rm -rf
 *
 *  @param string $dir
 *  @param boolean $empty_only wether to delete the dir or only its contents.
 *  @return bool
 */
function rrmdir($dir, $empty_only = false)
{
	$files = glob($dir . '/*') ?: [];
	foreach($files as $file) {
		is_dir($file) ? rrmdir($file) : unlink($file);
	}
	return $empty_only ?: @rmdir($dir);
}


/**
 *  BBCode parser
 *
 *  @param string $bbcode
 *  @param boolean $safe_subset whether to allow all bbcodes or only a small safer subset
 *  @return string
 */
function bbcode2html($bbcode, $safe_subset = false)
{
	$parser = new \Evo\BBCode();
	$parser->setSafeTags(['b', 'u', 'i', 's', 'sub', 'sup', 'color', 'spoiler', 'tooltip', 'url']);
	$parser->addTag("quote='([-a-z0-9_]+)' pid='([0-9]+)' dateline='([0-9]+)'", '<blockquote><a href="' . App::getURL('forums', ['pid' => '$2']) . '">$1 a dit</a>:<br>$4</blockquote>');
	$parser->addTag('file=([0-9x]+)', function($match) { return trim(Widgets::filebox(preg_replace('#/.+$#', '', $match[2]), '', $match[1])); });
	$parser->addTag('file', function($match) { return trim(Widgets::filebox(preg_replace('#/.+$#', '', $match[1]))); });

	$bbcode = parse_user_tags($bbcode, function($type, $data, $users, $url) {
		if ($type === 'user') {
			$url = App::getURL('user', $data->id);
			return "[url=$url][tooltip={$data->group->name}]@{$data->username}[/tooltip][/url]";
		} elseif ($type === 'group' || $type === 'all' || $type === 'team') {
			$tooltip = __plural('Aucun membre|1 membre|%count% membres', count($users));
			return "[url=$url][tooltip=$tooltip]@{$data['name']}[/tooltip][/url]";
		}
	});

	return rewrite_links($parser->toHTML($bbcode, $safe_subset));
}


function markdown2html($content, $safe_mode = false, $hard_wrap = false)
{
	$content = $content ?? '';
	$content = (new \Parsedown\ParsedownExtra)
		->setSafeMode($safe_mode)
		->setBreaksEnabled($hard_wrap)
		->parse($content);

	$filebox_rel = random_hash(6);
	$filebox_regex = '/\[file(\s+".*?")?(?:\s+\.(.+?))?(?:\s+([0-9x]+?))?\]\s*(.+?)(\/.+?)?\s*\[\/file\]/i';
	$content = preg_replace_callback($filebox_regex, function($match) use($filebox_rel) {
		list(, $caption, $class, $size, $id) = $match;
		$caption = $caption && !trim($caption, '" ') ? false : trim($caption, '" ');
		return Widgets::filebox($id, $caption, $size, ['rel' => $filebox_rel], strtr($class, '.', ' '));
	}, $content);

	return rewrite_links($content);
}


function rewrite_links($html, $absolute_url = true)
{
	return preg_replace_callback('!\s(href|src|action|poster)="(/?\?p=.*?|\?/.*?|/[^/].*?)"!S', function ($m) {

		list($url, $hash) = explode('#', $m[2].'#');
		list($link, $query) = explode('?', ltrim($url, '/').'?');

		if ($link === 'index.php') $link = '';

		parse_str(html_entity_decode($query), $arr); // Maybe we shouldn't decode at all here...
		$arr = html_encode($arr);

		if (App::getConfig('url_rewriting') && $link === '' && isset($arr['p']) && !defined('EVO_ADMIN')) {
			$link = ltrim($arr['p'], '/');
			unset($arr['p']);
		}

		return ' ' . $m[1] . '="' . App::getURL($link, $arr, $hash) . '"';
	}, $html);
}


function parse_user_tags($content, $callback)
{
	return preg_replace_callback('/(?<tag>@[-a-zÀ-ú0-9_\.\\x{202F}]+)/imu', function($match) use ($callback) { // (?:[^a-z]|^)
		$target = substr(preg_replace('/\\x{202F}/u', ' ', $match['tag']), 1);
		$type = 'none';
		$data = ['id' => 0, 'name' => $target];
		$url = null;
		$users = [];

		if ($target === 'all' || $target === 'everyone') {
			$users = Db::QueryAll('select id, username from {users}');
			$url = App::getURL('users');
			$type = 'all';
		} elseif ($target === 'team') {
			$users = Db::QueryAll('select u.id, u.username from {users} as u join {permissions} as p on p.group_id = u.group_id and p.name ="user.staff" and p.value = 1');
			$url = App::getURL('users', ['team' => 1]);
			$type = 'team';
		} elseif ($data = App::getUser($target)) {
			$users = [$data];
			$url = App::getURL('user', $data->id);
			$type = 'user';
		} elseif ($data = App::getGroup($target)) {
			$users = $data->users;
			$url = App::getURL('users', ['group' => $data->id]);
			$data = $data->toArray();
			$type = 'group';
		}

		$replace = $callback($type, $data, $users, $url);

		return $replace === null ? $match[0] : $replace;
	}, $content);
}


function SendPrivateMessage($to, $subject, $message, $reply_to = 0, $type = 0, $from = null)
{
	$from = $from ?: ($type == 0 ? App::getCurrentUser(): ['id' => 0, 'username' => 'Système', 'group_id' => 1]);

	if ($from instanceof Evo\Model) {
		$from = $from->toArray();
	}

	if (ctype_digit($to)) {
		$to = Db::Get('select id, username, email, group_id from {users} where id = ?', $to);
	} else {
		$to = Db::Get('select id, username, email, group_id from {users} where username = ?', $to);
	}

	if (!$to) {
		return false;
	}

	Db::Insert('mailbox', [
		'reply'   => $reply_to,
		's_id'    => $from['id'],
		'r_id'    => $to['id'],
		'sujet'   => $subject,
		'message' => $message,
		'posted'  => time(),
		'type'    => $type,
	]);

	$variables = ['username' => $to['username'], 'mailfrom' => $from['username'], 'message' => $message];
	sendmail_template($to['email'], 'message.type.'.$type, $variables);

	if ($from['id']) { // Do not log system messages
		App::logEvent($to['id'], 'mail', "Subject: {$subject}\nMessage: {$message}");
	}

	return Db::$insert_id;
}


function sendmail_template($to, $template, array $variables = [])
{
	$variables += ['username' => '', 'sitename' => App::getConfig('name')];

	foreach($variables as $key => $value) {
		$_variables["%$key%"] = $value;
	}

	$subject = __("mail/$template.subject", $_variables);
	$message = __("mail/$template.body", $_variables);

	if (Evo\Lang::has('mail/wrapper')) {
		$message = __('mail/wrapper', [
			'%message%'  => $message,
			'%sitename%' => App::getConfig('name'),
			'%siteurl%'  => App::getConfig('url')
		] + $_variables);
	}

	return App::sendmail($to, $subject, $message);
}


function send_activation_email($username)
{
	if ($r = Db::Get('SELECT id,username,locked,activity,email,reset_key FROM {users} where locked = 2 and username = ?', $username)) {
		$url  = App::getURL('login', ['action' => 'activate','key' => $r['reset_key'], 'username' => $r['username']]);
		if (sendmail_template($r['email'], 'account.activation', ['username' => $r['username'], 'activation_url' => $url])) {
			App::logEvent($r['id'], 'user', 'Mail d\'activation envoyé.');
			return true;
		}
	}
	return false;
}


function settings_form(array $settings, $title = null, array $options = [])
{
	$form_tag = $options['form'] ?? true;
	$submit = array_key_exists('submit', $options) ? $options['submit'] : __('form.save');

	return Widgets::formBuilder($title, admin_settings_prepare($settings), $form_tag, $submit);
}


function settings_save(array $settings, array $values)
{
	$changes = [];

	foreach ($values as $field => $value)
	{
		$field = (string)str_replace('||', '.', $field); // PHP will eat the . in POST

		if (array_key_exists($field, $settings) && $value != App::getConfig($field)) {
			if (isset($settings[$field]['default']) && $value === $settings[$field]['default']) {
				// Do nothing, default doesn't have to be valid for the type
			}
			elseif (isset($settings[$field]['validate']) && !preg_match($settings[$field]['validate'], $value)) {
				App::logEvent(0, 'admin', 'Tentative modification du paramètre: '.$field.' avec valeure incorrecte.');
				continue;
			}
			elseif ($settings[$field]['type'] === 'enum') {
				$valid = false;
				foreach($settings[$field]['choices'] as $key => $choice) {
					$valid = ($value == $key || $value == $choice || isset($choice[$value]));
					$valid = $valid || ($value instanceof HtmlSelectGroup && in_array($value, $value->getArrayCopy()));
					if ($valid) break;
				}

				if (!$valid) {
					App::logEvent(0, 'admin', 'Tentative modification du paramètre: '.$field.' avec valeure incorrecte.');
					continue;
				}
			}
			elseif ($settings[$field]['type'] === 'bool' && !in_array($value, [0, 1])) {
				App::logEvent(0, 'admin', 'Tentative modification du paramètre: '.$field.' avec valeure incorrecte.');
				continue;
			}
			elseif ($settings[$field]['type'] === 'number' && !ctype_digit($value)) {
				App::logEvent(0, 'admin', 'Tentative modification du paramètre: '.$field.' avec valeure incorrecte.');
				continue;
			}

			if ($field === 'url') { rtrim($value, '/'); }
			App::setConfig($field, $value);
			if (Db::$affected_rows) {
				$changes[] = $field;
				App::logEvent(0, 'admin', 'Modification du paramètre: '.$field.'.');
			}
		}
	}
	return $changes;
}


function change_comment_state($commentID, $newState = 0)
{
	if (has_permission('mod.comment_censure') && $newState >= 0) {
		if (Db::Update('comments', ['state' => $newState], ['id' => $commentID])) {
			return true;
		}
	} elseif (has_permission('mod.comment_delete') && $newState < 0) {
		$page_id = Db::Get('select page_id from {comments} WHERE id = ?', $commentID);
		if ($page_id && Db::Delete('comments', ['id' => $commentID]) !== false) {
			Db::Exec('update {pages} set comments = (select count(*) from {comments} as c where c.page_id = {pages}.page_id) where page_id = ?', $page_id);
			App::logEvent(0, 'admin', 'Commentaire supprimé #'.$commentID);
			return true;
		}
	}
	return false;
}


function get_menu_tree($extended = false, &$items = null)
{
	$tree = [];

	if ($extended)
		$items = Db::QueryAll('SELECT m.*, r.title AS page_name, r.slug, p.redirect
							   FROM {menu} AS m
							   LEFT JOIN {pages} AS p ON p.page_id = m.link
							   LEFT JOIN {pages_revs} AS r ON r.page_id = p.page_id AND r.revision = p.revisions
							   ORDER BY priority, m.id ASC', true);
	else
		$items = Db::QueryAll('SELECT m.*, p.slug, p.redirect FROM {menu} AS m
							   LEFT JOIN {pages} AS p ON p.page_id = m.link
							   ORDER BY priority, id ASC', true);

	foreach($items as $item) {
		if (!isset($items[$item['parent']]) || $item['parent'] == $item['id'])
			$item['parent'] = 0;

		$tree[$item['parent']][$item['id']] = $item;
	}

	return $tree;
}


function human_unit_to_bytes($size, $fallback = 'B')
{
	$number = intval(trim($size));
	$unit = strtoupper(preg_replace('/^[\d\s]+/', '', $size.$fallback))[0];

	switch ($unit) {
		case 'K': return $number * 1024;
		case 'M': return $number * 1024 * 1024;
		case 'G': return $number * 1024 * 1024 * 1024;
		default: return $number;
	}
 }


function get_effective_upload_max_size($ignore_cms = false)
{
	$max_cms = human_unit_to_bytes(App::getConfig('upload_max_size', 0).'M') ?: PHP_INT_MAX;
	$max_server = min(
		human_unit_to_bytes(ini_get('post_max_size')) ?: PHP_INT_MAX,
		human_unit_to_bytes(ini_get('upload_max_filesize')) ?: PHP_INT_MAX
	);

	return $ignore_cms ? $max_server : min($max_cms, $max_server);
}


function generate_tz_list()
{
	foreach(DateTimeZone::listIdentifiers() as $tz) {
		$dt = new DateTime('now', $tz ? new DateTimeZone($tz) : null);
		$offset = $dt->getOffset();
		$desc = '(GMT' . ($offset >= 0 ? '+' : '-') . gmdate('H:i', abs($offset)) . ', '.$dt->format('H:i').')  ' . $tz;
		$times[$tz] = $desc;
	}
	asort($times);
	return ['0' => 'Default (' . date('H:i') . ')'] + $times;
}


function build_search_query($query, $columns = ['a-z0-9_-\.'])
{
	$where = [];
	$args  = [];
	$link  = ' or ';
	$joined_cols = implode('|', array_merge(preg_replace('/^[a-z0-9_-]+\./', '', $columns), $columns));

	$filter = preg_replace_callback(
		'/('.$joined_cols.'):\s*([^\s]+)/ims',
		function($m) use (&$where, &$args, &$link) {
			$operator = strpos($m[2], '*') !== false || strpos($m[2], '%') !== false ? 'LIKE' : '=';
			$link = ' and ';
			$where[] = "a.{$m[1]} $operator ?";
			$args[] = str_replace('*', '%', $m[2]);
			return '';
		},
		$query
	);

	if ($filter = trim($filter)) {
		foreach($columns as $column) {
			$args[] = '%' . $filter . '%';
			$where[] = $column . ' like ? ';
		}
	}

	return ['where' => implode($link, $where), 'args' => $args];
}


function subscribe($type, $rel_id, $user_id, $email = '')
{
	return Db::Exec('replace into {subscriptions} (type, user_id, rel_id, email) values (?, ?, ?, ?)',
		$type, $user_id, $rel_id, $email);
}


function unsubscribe($type, $rel_id, $user_id = null, $email = null)
{
	if (!$user_id && !$email) {
		return Db::Delete('subscriptions', ['type' => $type, 'rel_id' => $user_id]);
	}

	return Db::Delete('subscriptions', ['type' => $type, 'rel_id' => $user_id, 'user_id' => $rel_id])
	     + Db::Delete('subscriptions', ['type' => $type, 'rel_id' => $user_id, 'email' => $email]);
}


function notify_subscribers($type, $rel_id, array $object = [])
{
	$subscribers = Db::QueryAll('select s.user_id, u.username, COALESCE(u.email, s.email) as email from {subscriptions} as s
		left join {users} as u on u.id = s.user_id where type = ? and rel_id = ?', $type, $rel_id);

	foreach($subscribers as $subscriber) {
		if (App::getCurrentUser()->id == $subscriber['user_id']) {
			continue; // don't notify current user for actions triggered by them
		}
		sendmail_template($subscriber['email'], $type, (array)$subscriber + $object);
	}
}

class HtmlSelectGroup extends \ArrayObject {}
