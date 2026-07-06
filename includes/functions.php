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
			$icon = html_encode($item['icon'] ?? 'fa fa-chart-bar');
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
		$icon = html_encode($item['icon'] ?? 'fa fa-chart-bar');
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
 * Affiche plusieurs sous-sections dans une seule carte avec un bouton Enregistrer.
 *
 * @param array<int, array{title?: string, icon?: string, description?: string, settings?: array, empty?: string, extra?: string}> $groups
 */
function admin_settings_grouped_form(string $tab_id, array $groups, array $options = []): string
{
	$submit_label = $options['submit'] ?? __('form.save');
	$class = $options['class'] ?? '';
	$form_id = 'admin-settings-form-' . random_hash(4);

	$html = '<form method="post" role="form" class="form-horizontal admin-settings-grouped-form ' . html_encode($class) . '" enctype="multipart/form-data" id="' . html_encode($form_id) . '">';
	$html .= '<input type="hidden" name="admin_settings_tab" value="' . html_encode($tab_id) . '">';
	$html .= '<section class="admin-settings-section admin-settings-section--grouped">';
	$html .= '<div class="admin-settings-section__body admin-settings-section__body--grouped">';

	foreach ($groups as $index => $group) {
		if ($index > 0) {
			$html .= '<hr class="admin-settings-subsection__divider">';
		}

		$html .= '<div class="admin-settings-subsection">';

		if (!empty($group['title'])) {
			$html .= '<header class="admin-settings-subsection__header">';

			if (!empty($group['icon'])) {
				$html .= '<span class="admin-settings-subsection__icon"><i class="fas ' . html_encode($group['icon']) . '" aria-hidden="true"></i></span>';
			}

			$html .= '<div class="admin-settings-subsection__heading">';
			$html .= '<h3 class="admin-settings-subsection__title">' . html_encode($group['title']) . '</h3>';

			if (!empty($group['description'])) {
				$html .= '<p class="admin-settings-subsection__desc">' . $group['description'] . '</p>';
			}

			$html .= '</div></header>';
		}

		$html .= '<div class="admin-settings-subsection__body">';

		$settings = $group['settings'] ?? [];

		if (!$settings && !empty($group['empty'])) {
			$html .= admin_settings_empty($group['empty'], $group['icon'] ?? 'fa-inbox');
		} else {
			$html .= Widgets::formBuilder(null, admin_settings_prepare($settings), false, null);
		}

		if (!empty($group['extra'])) {
			$html .= $group['extra'];
		}

		$html .= '</div></div>';
	}

	$html .= '</div>';
	$html .= '<footer class="admin-settings-section__footer">';
	$html .= '<div class="text-center"><input class="btn btn-primary" type="submit" value="' . html_encode($submit_label) . '"></div>';
	$html .= '</footer></section>';
	$html .= Widgets::formBuilderScript();
	$html .= '</form>';

	return $html;
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

		$html .= '<li class="nav-item" role="presentation">';
		$html .= '<a class="' . $link_class . '" role="tab" aria-selected="' . ($is_active ? 'true' : 'false') . '"';

		if ($type === 'link') {
			$href = $tab['href'] ?? ('?page=' . rawurlencode($page) . '&tab=' . rawurlencode((string) $tab_id));
			$html .= ' href="' . html_encode($href) . '"';
		} else {
			$href = $tab['href'] ?? ('#' . $tab_id);
			$html .= ' id="' . html_encode($tab_id) . '-tab" data-bs-toggle="tab" href="' . html_encode($href) . '" aria-controls="' . html_encode($tab_id) . '"';
		}

		$html .= '>';

		if (!empty($tab['icon'])) {
			$html .= '<i class="fas ' . html_encode($tab['icon']) . ' me-2" aria-hidden="true"></i>';
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

function admin_settings_tab_open(string $id, bool $active): string
{
	return '<div class="tab-pane fade admin-settings__pane' . ($active ? ' show active' : '') . '" id="' . html_encode($id) . '" role="tabpanel" aria-labelledby="' . html_encode($id) . '-tab" tabindex="0">';
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
			$html .= '<span class="admin-settings-section__icon"><i class="fas ' . html_encode($icon) . '" aria-hidden="true"></i></span>';
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
		. '<i class="fas ' . html_encode($icon) . ' admin-settings-empty__icon" aria-hidden="true"></i>'
		. '<p class="admin-settings-empty__text">' . html_encode($message) . '</p>'
		. '</div>';
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
			$html .= '<span class="badge admin-settings-theme__badge"><i class="fa fa-check me-1" aria-hidden="true"></i>' . html_encode(__('admin/general.theme_enabled')) . '</span>';
		} else {
			$html .= '<button type="submit" class="btn btn-sm btn-primary" name="theme" value="' . html_encode($dir) . '">' . html_encode(__('admin/general.theme_active_btn')) . '</button>';
		}

		$html .= '</div></article></div>';
	}

	return $html . '</div>';
}

function admin_settings_theme_panel(array $themes, string $activeDir): string
{
	$content = '<form method="post" class="admin-settings-theme-form" enctype="multipart/form-data">'
		. admin_settings_theme_grid($themes, $activeDir)
		. '<p class="admin-settings-theme-form__hint">' . __('admin/general.theme_tips') . '</p>'
		. '</form>';

	return admin_settings_section(__('admin/general.tab_theme'), $content, ['icon' => 'fa-palette']);
}

function admin_settings_test_mail(?string $email = null): string
{
	$email = $email ?: App::getCurrentUser()->email;

	return '<form method="post" class="admin-settings-test-mail">'
		. '<label class="form-label admin-settings-test-mail__label">' . html_encode(__('admin/general.email_test')) . '</label>'
		. '<div class="input-group">'
		. '<input type="email" class="form-control" name="mail||send-test-mail" value="' . html_encode($email) . '" placeholder="email@example.com" required>'
		. '<button type="submit" class="btn btn-outline-secondary">'
		. '<i class="fa fa-paper-plane me-1" aria-hidden="true"></i>' . html_encode(__('admin/general.email_test'))
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
		$icon = html_encode($item['icon'] ?? 'fa fa-chart-bar');
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
		$icon = html_encode($item['icon'] ?? 'fa fa-chart-bar');
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
		$icon = html_encode($item['icon'] ?? 'fa fa-chart-bar');
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
		'type' => 'link',
		'page' => 'modules',
		'aria_label' => __('admin/modules.main_title'),
	]);
}

function admin_modules_empty(string $message, string $icon = 'fa-box-open'): string
{
	return admin_settings_empty($message, $icon);
}

/**
 * Affiche un tableau réutilisable pour la page modules admin.
 *
 * @param array<int, string> $columns
 * @param array<int, array<int, string>> $rows
 * @param array{caption?: string, icon?: string, class?: string, empty?: string, empty_icon?: string} $options
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
	$html = '<div class="admin-modules-table-wrap ' . html_encode($class) . '">';

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
		$html .= '<span class="admin-modules-table__count">' . count($rows) . '</span>';
		$html .= '</div>';
	}

	$html .= '<div class="table-responsive admin-modules-table-scroll">';
	$html .= '<table class="table admin-modules-table mb-0';

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

	foreach ($rows as $row) {
		$html .= '<tr>';

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
		$html .= '<i class="fas ' . html_encode($icon) . '" aria-hidden="true"></i></span>';
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
				'fas fa-cog',
				__('admin/modules.btn_settings'),
				'btn-outline-primary'
			);
		}

		$html .= admin_modules_action_button(
			'deactivate_plugin',
			$item['id'],
			'fas fa-power-off',
			__('admin/modules.btn_disabling'),
			'btn-outline-warning'
		);
	} else {
		$html .= admin_modules_action_button(
			'activate_plugin',
			$item['id'],
			'fas fa-check',
			__('admin/modules.btn_enabling'),
			'btn-success'
		);
		$html .= admin_modules_action_button(
			'delete_plugin',
			$item['id'],
			'fas fa-trash-alt',
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
			'icon' => 'fa fa-palette',
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
			'icon' => 'fa fa-puzzle-piece',
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
			'fas fa-download',
			__('admin/modules.btn_download'),
			'btn-primary'
		);
		$html .= admin_modules_action_link('#', 'fas fa-microchip', __('admin/modules.btn_install'));
	}

	if ($extended && !empty($item->preview)) {
		$html .= admin_modules_action_link(
			$item->preview,
			'far fa-images',
			__('admin/modules.btn_preview')
		);
	}

	if ($extended && !empty($item->website)) {
		$html .= admin_modules_action_link(
			$item->website,
			'fas fa-globe-americas',
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
	$icon = admin_modules_panel_icon($panel['icon'] ?? 'fa fa-puzzle-piece');

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
			'<i class="fas fa-exclamation-circle me-1" aria-hidden="true"></i> ' . __('admin/modules.import_zip_missing')
		);
	}

	$html = '<div class="admin-modules-upload">';
	$html .= '<div class="admin-modules-upload__visual">';
	$html .= '<span class="admin-modules-upload__icon"><i class="fas fa-cloud-upload-alt" aria-hidden="true"></i></span>';
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
	$html .= '<i class="fas fa-upload me-1" aria-hidden="true"></i>' . html_encode(__('admin/modules.header_form_btn_upload'));
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
	$html .= '<i class="fas fa-arrow-left me-1" aria-hidden="true"></i>' . html_encode(__('admin/modules.back_to_list'));
	$html .= '</a></div>';

	$html .= admin_modules_item_cell($info->name, [
		'description' => $info->description ?? '',
		'icon' => 'fa-cogs',
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
		'icon' => 'fa fa-cogs',
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
 * Récupère les informations de la page courante dans l'admin
 * @param string $type 'icon', 'title', 'description', 'both', 'all', ou 'html'
 * @return string|array
 */
function getCurrentPageInfo($type = 'both')
 {
	 $page = App::GET('page');

	 if ($page === 'modules' && ($pluginId = App::GET('plugin', ''))) {
		 $module = App::getModule($pluginId);
		 if ($module) {
			 $icon = 'fa-cogs';
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
					 return '<i class="fa ' . $icon . ' me-3"></i>' . html_encode($title);
			 }
		 }
	 }
	 
	 $pageIcons = [
		 '' => 'fa-info-circle',
		 'index' => 'fa-info-circle',
		 'settings' => 'fa-keyboard',
		 'reports' => 'fa-exclamation-circle',
		 'servers' => 'fa-server',
		 'page_edit' => 'fa-file',
		 'pages' => 'fa-file-alt',
		 'menu' => 'fa-list',
		 'gallery' => 'fa-images',
		 'avatars' => 'fa-grin-squint-tears',
		 'downloads' => 'fa-file-download',
		 'forums' => 'fa-list',
		 'comments' => 'fa-comments',
		 'broadcast' => 'fa-envelope',
		 'users' => 'fa-users',
		 'groups' => 'fa-layer-group',
		 'history' => 'fa-user-secret',
		 'security' => 'fa-user-slash',
		 'modules' => 'fa-cogs',
		 'backup' => 'fa-file-archive',
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
		 'groups' => __('admin/menu.sub_groups'),
		 'history' => __('admin/menu.sub_log_admin'),
		 'security' => __('admin/menu.sub_security'),
		 'modules' => __('admin/menu.sub_modules'),
		 'backup' => __('admin/menu.sub_backup'),
		 'file_editor' => __('admin/menu.sub_files_editor'),
	 ];
	 
	 $pageDescriptions = [
		 '' => 'Tableau de bord principal avec les statistiques du site',
		 'index' => 'Tableau de bord principal avec les statistiques du site',
		 'settings' => 'Configuration générale du site, paramètres et préférences',
		 'reports' => 'Gestion des signalements et modération du contenu',
		 'servers' => 'Configuration et gestion des serveurs de jeu',
		 'page_edit' => 'Création et édition de nouvelles pages et articles',
		 'pages' => 'Gestion complète des pages, articles et contenu du site',
		 'menu' => 'Éditeur de menu pour personnaliser la navigation',
		 'gallery' => 'Bibliothèque multimédia pour gérer les images et fichiers',
		 'avatars' => 'Gestion des avatars et images de profil des utilisateurs',
		 'downloads' => 'Section de téléchargements et fichiers partagés',
		 'forums' => 'Configuration et modération des forums de discussion',
		 'comments' => 'Modération des commentaires et interactions utilisateurs',
		 'broadcast' => 'Envoi de newsletters et communications de masse',
		 'users' => 'Gestion des membres, profils et comptes utilisateurs',
		 'groups' => 'Configuration des groupes et permissions utilisateurs',
		 'history' => 'Historique des actions et logs d\'administration',
		 'security' => 'Sécurité, bannissements et protection du site',
		 'modules' => 'Gestion des modules et extensions du CMS',
		 'backup' => 'Sauvegarde et restauration des données du site',
		 'file_editor' => 'Éditeur de fichiers pour modifications directes',
	 ];
	 
	 $icon = $pageIcons[$page] ?? 'fa-tachometer-alt';
	 $title = $pageTitles[$page] ?? ucfirst($page ?: 'Dashboard');
	 $description = $pageDescriptions[$page] ?? 'Page d\'administration';
	 
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
			 return '<i class="fa ' . $icon . ' me-3"></i>' . $title;
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
