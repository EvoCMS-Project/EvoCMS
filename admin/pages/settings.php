<?php defined('EVO') or die('Que fais-tu là?');

has_permission('admin.manage_settings', true);

use Evo\Models\File;

// Send test mail to designated address and report any error
if (App::POST('mail||send-test-mail')) {
	$success = App::sendmail(
		App::POST('mail||send-test-mail'),
		__('admin/settings.email_test_1'),
		__('admin/settings.email_test_2'),
		__('admin/settings.email_test_3'),
		$error
	);

	if ($success) {
		App::setNotice(__('admin/settings.notice_email_sent'));
	} else {
		App::setWarning(__('admin/settings.warning_email_sent') .': <pre>'.html_encode($error).'</pre>');
	}
}


// Prepare our list of settings and their options...
$user_pages = Db::QueryAll('select p.page_id, title  from {pages} as p join {pages_revs} as r ON r.page_id = p.page_id AND r.revision = p.revisions order by pub_date desc, title asc');
$cat_pages = [];
foreach(Db::QueryAll('SELECT DISTINCT category from {pages} WHERE category <> ""') as $cat) {
	$cat_pages[strtr("category/{$cat['category']}", ' ', '-')] = $cat['category'];
}

$pages = [
	'Pages '     => new HtmlSelectGroup(array_column($user_pages, 'title', 'page_id')),
	'Categories' => new HtmlSelectGroup($cat_pages),
	'Internes'   => new HtmlSelectGroup(array_combine(INTERNAL_PAGES, array_map('ucwords', INTERNAL_PAGES)))
];

$groups = Db::QueryAll('select name, id from {groups} where id <> 1 and id <> 4 order by priority asc');
$group_list = array_column($groups, 'name', 'id');

$max_server_upload_size = get_effective_upload_max_size(true) / 1024 / 1024;

$locales = Evo\Lang::getLocales(true, true);

$_themes = [
	'' => [Evo\EvoInfo::fromFile(ROOT_DIR . '/assets/theme.json'), '/assets/preview.jpg'],
];


foreach(App::getModules() as $plugin) {
	if (in_array('theme', $plugin->exports)) {
		$dir = basename($plugin->location);
		$_themes[$dir] = [$plugin, "/modules/$dir/preview.jpg"];
	}
}

/* types supportés:
	text : text
	number : chiffre
	bool : oui/non
	enum : array de choix 'choices'
	color : color picker
	image : image picker
*/
$site_settings = array(
	'name' 				=> array('type' => 'text', 'label' => __('admin/general.site_name')),
	'subtitle' 	     	=> array('type' => 'text', 'label' => __('admin/general.site_subtitle')),
	'description' 		=> array('type' => 'text', 'label' => __('admin/general.site_desc'), 'help' => __('admin/general.site_desc_tips')),
	'url' 				=> array('type' => 'text', 'label' => __('admin/general.site_url')),
	'url_https' 		=> array('type' => 'bool', 'label' => __('admin/general.site_https')),
	'url_rewriting' 	=> array('type' => 'bool', 'label' => __('admin/general.site_curl'), 'help' => __('admin/general.site_curl_tips')),
	'frontpage' 		=> array('type' => 'enum', 'label' => __('admin/general.site_home'), 'choices' => $pages),
	'language'			=> array('type' => 'enum', 'label' => __('admin/general.site_lang'), 'choices' => $locales),
	'email' 			=> array('type' => 'text', 'label' => __('admin/general.site_email_admin')),
	'timezone'          => array('type' => 'enum', 'label' => __('admin/general.site_timezone'), 'choices' => generate_tz_list()),
	'open_registration'	=> array('type' => 'enum', 'label' => __('admin/general.site_register'), 'choices' => [0 => __('admin/general.site_register_opt0'), 1 => __('admin/general.site_register_opt1'), 3 => __('admin/general.site_register_yes0'), 2 => __('admin/general.site_register_yes1')]),
	'default_user_group'=> array('type' => 'enum', 'label' => __('admin/general.site_group'), 'choices' => $group_list),
	'articles_per_page' => array('type' => 'number', 'label' => __('admin/general.site_article_page'), 'help' => __('admin/general.site_article_page_tips')),
	'editor'			=> array('type' => 'enum', 'label' => __('admin/general.site_editor'), 'choices' => ['wysiwyg' => 'WYSIWYG', 'markdown' => 'Markdown']),
);

$upload_settings = array(
	'upload_groups'		=> array('type' => 'textarea', 'label' => __('admin/general.upload_file'), 'help' => __('admin/general.upload_file_tips'), 'allow_reset' => true),
	'upload_max_size'	=> array('type' => 'number', 'label' => __('admin/general.upload_max_size') .'(MB)', 'help' => __('admin/general.upload_max_size_tips') .' '. $max_server_upload_size . 'MB', 'default' => '', 'allow_reset' => true, 'attributes' => ['placeholder' => __('admin/general.upload_serv_limit') .' : ' .  $max_server_upload_size .'MB']),
);

$mail_settings = array(
	'mail.send_method'     => array('type' => 'enum', 'label' => __('admin/general.email_mtd'), 'choices' => ['mail' => __('admin/general.email_mtd_0'), 'smtp' => __('admin/general.email_mtd_1')]),
	'mail.smtp_host'       => array('type' => 'text', 'label' => __('admin/general.email_host'), 'default' => 'localhost'),
	'mail.smtp_port'       => array('type' => 'number', 'label' => __('admin/general.email_port'), 'default' => '25'),
	'mail.smtp_encryption' => array('type' => 'enum', 'label' => __('admin/general.email_encrypt'), 'choices' => ['' => 'Auto', 'tls' => 'TLS', 'ssl' => 'SSL']),
	'mail.smtp_username'   => array('type' => 'text', 'label' => __('admin/general.email_username'), 'attributes' => ['autocomplete' => 'off']),
	'mail.smtp_password'   => array('type' => 'password', 'label' => __('admin/general.email_password'), 'attributes' => ['autocomplete' => 'new-password']),
);

$providers_settings = array(
);

$social_settings = array(
);

foreach(Evo\Avatars::getProviders(false) as $key => $provider) {
	$providers_settings["providers.avatar.$key"] = ['type' => 'bool', 'label' => __('admin/general.profil_avatar', ['%key%' => $key]), 'default' => true];
}

foreach(Evo\Social::getProviders(false) as $key => [$name, $icon, $regex]) {
	$providers_settings["providers.social.$key"] = ['type' => 'bool', 'label' => "$name <i class='fab $icon'></i>", 'default' => true];
	$social_settings["social.$key"] = ['type' => 'text', 'label' => "$name <i class='fab $icon'></i>", 'attributes' => ['placeholder' => 'URL']];
}

$theme_settings = array(
	'theme'        => array('type' => 'enum', 'label' => __('admin/general.theme_selector'), 'choices' => array_keys($_themes)),
);

if (IS_POST) {
	$settings = $site_settings
			  + $mail_settings
			  + $upload_settings
			  + $providers_settings
			  + $theme_settings
			  + $social_settings
			  + App::getTheme()->settings;
	$values = App::POST();

	$save = true;

	$upload_max_size = (int)App::POST('upload_max_size');
	if ($upload_max_size > $max_server_upload_size) {
		App::setNotice(__('admin/general.upload_max_size_cfg', ['%max_server_upload_size%' => $max_server_upload_size,'%upload_max_size%' => $max_server_upload_size]));
	}

	if (isset($values['url'])) {
		if (preg_match('#^(http:|https:|)//#i', $values['url'])) {
			if (!empty($values['url_https'])) {
			 	$values['url'] = preg_replace('#^(http:|https:|)//#i', 'https://', $values['url']);
			}
		} else {
			App::setWarning(__('admin/general.upload_invalid_url'));
			$save = false;
		}
	}

	foreach($_FILES as $field => $file) {
		$field = str_replace('||', '.', $field);
		if (isset($settings[$field]) && !empty($file['tmp_name'])) {
			try {
				$file = File::create($file, 'settings');
				$values[$field] = $file->path;
			} catch (Exception $e) {
				App::setWarning("Error: {$e->getMessage()}");
				$save = false;
			}
		}
	}

	if ($save) {
		if ($changes = settings_save($settings, $values)) {
			if (in_array('theme', $changes)) {
				App::setTheme($values['theme']);
			}
			App::setSuccess(__('admin/settings-alert.success'));
		} else {
			App::setNotice(__('admin/settings-alert.nochange'));
		}
	}
}

$tab = 'config';
if (IS_POST) {
	if (App::POST('theme')) {
		$tab = 'theme';
	} elseif (preg_match('/^(theme|modules)\|/', key(App::POST()))) {
		$tab = 'themeconfig';
	} elseif (preg_match('/^(social)\|/', key(App::POST()))) {
		$tab = 'social';
	} elseif (!App::POST('url')) {
		$tab = 'advanced';
	}
}
?>

<ul class="nav nav-tabs">
	<li class="nav-item"><a class="nav-link <?= $tab === 'config' ? 'active' : '' ?>" href="#config" data-toggle="tab"><?= __('admin/general.tab_site') ?></a></li>
	<li class="nav-item"><a class="nav-link <?= $tab === 'advanced' ? 'active' : '' ?>" href="#advanced" data-toggle="tab"><?= __('admin/general.tab_advanced') ?></a></li>
	<li class="nav-item"><a class="nav-link <?= $tab === 'social' ? 'active' : '' ?>" href="#social" data-toggle="tab"><?= __('admin/general.tab_social') ?></a></li>
	<li class="nav-item"><a class="nav-link <?= $tab === 'theme' ? 'active' : '' ?>" href="#theme" data-toggle="tab"><?= __('admin/general.tab_theme') ?></a></li>
	<?php if (App::getTheme()->settings) { ?>
	<li class="nav-item"><a class="nav-link <?= $tab === 'themeconfig' ? 'active' : '' ?>" href="#themeconfig" data-toggle="tab"><?= __('admin/general.tab_tconfig') ?></a></li>
	<?php } ?>
</ul>

<div class="tab-content panel">
	<div class="tab-pane fade <?= $tab === 'config' ? 'show active' : '' ?>" id="config" style="padding: 2em;">
		<?= settings_form($site_settings) ?>
	</div>

	<div class="tab-pane fade <?= $tab === 'social' ? 'show active' : '' ?>" id="social" style="padding: 2em;">
		<?= settings_form($social_settings) ?>
	</div>

	<div class="tab-pane fade <?= $tab === 'advanced' ? 'show active' : '' ?>" id="advanced" style="padding: 2em;">
		<?= settings_form($upload_settings, __('admin/general.adv_cfg_upl')) ?>
		<div>&nbsp;</div>
		<?= settings_form($mail_settings, __('admin/general.adv_cfg_email')) ?>
		<form method="post">
			<input type="text" name="mail||send-test-mail" value="<?= App::getCurrentUser()->email ?>" placeholder="adresse@destination.com">
			<button type="submit">Envoi mail test</button>
		</form>
		<div>&nbsp;</div>
		<?= settings_form($providers_settings, __('admin/general.adv_cfg_profil')) ?>
	</div>

	<div class="tab-pane fade <?= $tab === 'theme' ? 'show active' : '' ?>" id="theme" style="padding: 2em;">
		<form method="post" class="form-horizontal" enctype="multipart/form-data">
			<table class="table">
			<?php
				foreach($_themes as $dir => [$theme, $preview]) {
					echo '<tr>';
					echo '<td style="width:200px"><img alt="preview" src="'.App::getLocalURL($preview).'" style="max-width:100%"></td>';
					echo '<td><h4>'.html_encode($theme->name).' <small>'.$theme->version.'</small></h4>'.html_encode($theme->description).'</td>';
					if ($dir === App::getConfig('theme')) {
						echo __('admin/general.theme_enabled');
					} else {
						echo '<td><button class="btn btn-sm btn-primary" name="theme" value="'.$dir.'">'.__('admin/general.theme_active_btn').'</button></td>';
					}
					echo '</tr>';
				}
			?>
			</table>
		</form>
		<div class="text-center"><?= __('admin/general.theme_tips') ?></div>
	</div>

	<div class="tab-pane fade <?= $tab === 'themeconfig' ? 'show active' : '' ?>" id="themeconfig" style="padding: 2em;">
		<form method="post" class="form-horizontal" enctype="multipart/form-data">
			<legend><?= __('admin/general.theme_title1') ?></legend>
			<?= settings_form(App::getTheme()->settings) ?>
		</form>
	</div>
</div>
