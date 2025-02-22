<meta charset="utf-8">
<meta name="description" content="<?= html_encode(App::getConfig('description')) ?>">
<meta name="title" content="<?= html_encode(App::getConfig('name')) ?>">
<meta name="identifier-url" content="<?= html_encode(App::getConfig('url')) ?>">
<meta name="revisit-after" content="1">
<meta name="language" content="<?=App::getConfig('language')?>">
<meta name="robots" content="All">

<title><?= html_encode($_title ?: App::getConfig('name'))?></title>

<link href="<?= App::getAsset('css/shared.css') ?>" rel="stylesheet">
<link rel="alternate" type="application/rss+xml" href="<?= App::getURL('feed') ?>">
<?php if ($icon = App::getAsset('favicon.ico')): ?>
<link href="<?= $icon ?>" rel="shortcut icon">
<?php endif; ?>

<script src="<?= App::getAsset('js/vendor.js') ?>"></script>
<script>
	var site_url = '<?= rtrim(App::getLocalURL('/'), '/') ?>';
	var logged_in = <?= App::getCurrentUser()->id ? 1 : 0 ?>;
	var csrf = '<?= $_SESSION['csrf'] ?? '' ?>';
	var max_upload_size = <?= has_permission('user.upload') ? get_effective_upload_max_size() : 0 ?>;
</script>

<?php
App::trigger('head');

foreach(App::getTheme()->settings as $name => $setting) {
	if (isset($setting['css']) && $value = App::getConfig($name)) {
		$css_settings[] = sprintf($setting['css'], $value);
	}
}
if (!empty($css_settings)) {
	echo '<style>'.implode(' ', $css_settings).'</style>';
}
?>