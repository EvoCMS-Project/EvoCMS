<?php defined('EVO') or die('Que fais-tu là?');

has_permission('admin.manage_settings', true);

use Evo\Models\File;

// Send test mail to designated address and report any error
if (App::POST('mail||send-test-mail')) {
	$success = App::sendmail(
		App::POST('mail||send-test-mail'),
		'Ceci est un test.',
		"Ceci est un message de test.\nThis is a test email.",
		"Ceci est un message de <strong>test</strong>.\nThis is a <strong>test</strong> email.",
		$error
	);

	if ($success) {
		App::setNotice('Envoi de mail de test réussi!');
	} else {
		App::setWarning('Envoi de mail de test échoué: <pre>'.html_encode($error).'</pre>');
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
	'name' 				=> array('type' => 'text', 'label' => 'Nom du site'),
	'subtitle' 	     	=> array('type' => 'text', 'label' => 'Sous titre'),
	'description' 		=> array('type' => 'text', 'label' => 'Description', 'help' => 'Soyez clair et descriptif, peut influencer votre rendement SEO'),
	'url' 				=> array('type' => 'text', 'label' => 'Adresse du site'),
	'url_https' 		=> array('type' => 'bool', 'label' => 'Forcer SSL'),
	'url_rewriting' 	=> array('type' => 'bool', 'label' => 'Rewriting URL', 'help' => 'Votre serveur doit supporter la réécriture. Le CMS supporte Apache automatiquement. Pour nginx voir nginx.conf.'),
	'frontpage' 		=> array('type' => 'enum', 'label' => 'Page d\'accueil', 'choices' => $pages),
	'language'			=> array('type' => 'enum', 'label' => 'Langue du CMS', 'choices' => $locales),
	'email' 			=> array('type' => 'text', 'label' => 'Email Administrateur'),
	'timezone'          => array('type' => 'enum', 'label' => 'Fuseau horaire', 'choices' => generate_tz_list()),
	'open_registration'	=> array('type' => 'enum', 'label' => 'Permettre les inscriptions', 'choices' => [0 => 'Non', 1 => 'Oui', 3 => 'Oui, avec activation par email', 2 => 'Oui, par invitation']),
	'default_user_group'=> array('type' => 'enum', 'label' => 'Groupe par défaut', 'choices' => $group_list),
	'articles_per_page' => array('type' => 'number', 'label' => 'Articles par page', 'help' => 'Nombre d\'articles affichés par page dans le blog'),
	'editor'			=> array('type' => 'enum', 'label' => 'Éditeur', 'choices' => ['wysiwyg' => 'WYSIWYG', 'markdown' => 'Markdown']),
);

$upload_settings = array(
	'upload_groups'		=> array('type' => 'textarea', 'label' => 'Fichiers acceptés', 'help' => 'Un groupe par ligne au format:<br>groupe ext ext...<br>où ext est une extension ou un mime-type.', 'allow_reset' => true),
	'upload_max_size'	=> array('type' => 'number', 'label' => 'Taille max. fichier (MB)', 'help' => 'La taille maximal d\'un fichier en upload. Votre configuration PHP limite à ' . $max_server_upload_size . 'MB', 'default' => '', 'allow_reset' => true, 'attributes' => ['placeholder' => 'Limite serveur: ' .  $max_server_upload_size .'MB']),
);

$mail_settings = array(
	'mail.send_method'     => array('type' => 'enum', 'label' => 'Méthode d\'envoi', 'choices' => ['mail' => 'Serveur Local (sendmail)', 'smtp' => 'Serveur Personalisé (SMTP)']),
	'mail.smtp_host'       => array('type' => 'text', 'label' => 'SMTP Server Host', 'default' => 'localhost'),
	'mail.smtp_port'       => array('type' => 'number', 'label' => 'SMTP Server Port', 'default' => '25'),
	'mail.smtp_encryption' => array('type' => 'enum', 'label' => 'SMTP Encryption', 'choices' => ['' => 'Auto', 'tls' => 'TLS', 'ssl' => 'SSL']),
	'mail.smtp_username'   => array('type' => 'text', 'label' => 'SMTP Server Username', 'attributes' => ['autocomplete' => 'off']),
	'mail.smtp_password'   => array('type' => 'password', 'label' => 'SMTP Server Password', 'attributes' => ['autocomplete' => 'new-password']),
);

$providers_settings = array(
);

$social_settings = array(
);

foreach(Evo\Avatars::getProviders(false) as $key => $provider) {
	$providers_settings["providers.avatar.$key"] = ['type' => 'bool', 'label' => "Avatar type '$key'", 'default' => true];
}

foreach(Evo\Social::getProviders(false) as $key => [$name, $icon, $regex]) {
	$providers_settings["providers.social.$key"] = ['type' => 'bool', 'label' => "Lien $name <i class='fab $icon'></i>", 'default' => true];
	$social_settings["social.$key"] = ['type' => 'text', 'label' => "$name <i class='fab $icon'></i>", 'attributes' => ['placeholder' => 'URL']];
}

$theme_settings = array(
	'theme'        => array('type' => 'enum', 'label' => 'Thème', 'choices' => array_keys($_themes)),
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
		App::setNotice("La configuration de votre serveur impose une limite d'upload de $max_server_upload_size MB. Votre limite CMS de $upload_max_size MB ne sera pas prise en compte. Veuillez vous référer aux directives php.ini post_max_size et upload_max_filesize.");
	}

	if (isset($values['url'])) {
		if (preg_match('#^(http:|https:|)//#i', $values['url'])) {
			if (!empty($values['url_https'])) {
			 	$values['url'] = preg_replace('#^(http:|https:|)//#i', 'https://', $values['url']);
			}
		} else {
			App::setWarning('Adresse du site invalide!');
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
			App::setSuccess('Configuration mise à jour!');
		} else {
			App::setNotice('Aucun changement!');
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
	<li class="nav-item"><a class="nav-link <?= $tab === 'config' ? 'active' : '' ?>" href="#config" data-toggle="tab">Configuration du site</a></li>
	<li class="nav-item"><a class="nav-link <?= $tab === 'advanced' ? 'active' : '' ?>" href="#advanced" data-toggle="tab">Configuration avancée</a></li>
	<li class="nav-item"><a class="nav-link <?= $tab === 'social' ? 'active' : '' ?>" href="#social" data-toggle="tab">Social</a></li>
	<li class="nav-item"><a class="nav-link <?= $tab === 'theme' ? 'active' : '' ?>" href="#theme" data-toggle="tab">Sélection du thème</a></li>
	<?php if (App::getTheme()->settings) { ?>
	<li class="nav-item"><a class="nav-link <?= $tab === 'themeconfig' ? 'active' : '' ?>" href="#themeconfig" data-toggle="tab">Configuration du thème</a></li>
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
		<?= settings_form($upload_settings, 'Configuration upload') ?>
		<div>&nbsp;</div>
		<?= settings_form($mail_settings, 'Configuration e-mail') ?>
		<form method="post">
			<input type="text" name="mail||send-test-mail" value="<?= App::getCurrentUser()->email ?>" placeholder="adresse@destination.com">
			<button type="submit">Envoi mail test</button>
		</form>
		<div>&nbsp;</div>
		<?= settings_form($providers_settings, 'Options profil membre') ?>
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
						echo '<td>Thème actif!</td>';
					} else {
						echo '<td><button class="btn btn-sm btn-primary" name="theme" value="'.$dir.'">Activer</button></td>';
					}
					echo '</tr>';
				}
			?>
			</table>
		</form>
		<div class="text-center">Vous pouvez installer des themes supplémentaires dans la section Modules!</div>
	</div>

	<div class="tab-pane fade <?= $tab === 'themeconfig' ? 'show active' : '' ?>" id="themeconfig" style="padding: 2em;">
		<form method="post" class="form-horizontal" enctype="multipart/form-data">
			<legend>Préférences du thème</legend>
			<?= settings_form(App::getTheme()->settings) ?>
		</form>
	</div>
</div>
