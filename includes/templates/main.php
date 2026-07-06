<!DOCTYPE html>
<html>

<head>
	<?php App::renderTemplate('head.php', $variables); ?>
	<link href="<?= App::getAsset('css/bootstrap.min.css') ?>" rel="stylesheet">
	<link href="<?= App::getAsset('css/website.css') ?>" rel="stylesheet">
</head>

<body class="tpl-main website <?= trim($_body_class) ?>">
	<div id="header">
		<div class="logo">
			<a href="<?= App::getURL('/') ?>">
			<?= '<img src="'.App::getAsset(App::getConfig('theme.logo') ?: '/img/logo.png'). '" alt="logo">'; ?>
			</a>
		</div>
		<div class="links">
			<?php foreach(Evo\Social::getProviders(false) as $social => [$name, $icon, $regex]): ?>
				<?php if ($link = App::getConfig("social.$social")): ?>
				<a href="<?= html_encode($link) ?>"><i class="fa-brands social-link social-<?= $social ?> <?= $icon ?>" title="<?= $name ?>"></i></a>
				<?php endif; ?>
			<?php endforeach; ?>
			<a href="<?= App::getURL('feed')?>"><i class="fa-solid social-link social-rss fa-rss" title="<?= __("main.rss") ?>"></i></a>
		</div>
	</div>
	<div class="clearfix"></div>
	<div id="wrapper">
<!-- DEBUT SIDEBAR -->
		<div id="sidebar">
			<div id="zone_login">
			<?php if (has_permission()) { ?>
				<div class="avatar_container"><?= get_avatar(App::getCurrentUser()) ?></div>
				<div class="welcome">Bienvenue <?= html_encode(App::getCurrentUser()->username) ?></div>
				<?php App::renderTemplate('userdropdown.php', $variables); ?>
			<?php } else { ?>
				<div class="text-center">
					<div class="btn-group">
						<a href="<?= App::getURL('login') ?>" class="btn btn-primary"><i class="fa-solid fa-user"></i> <?= __('main.login') ?></a>
						<a href="<?= App::getURL('register') ?>" class="btn btn-success"><i class="fa-solid fa-pencil"></i> <?= __('main.join') ?></a>
					</div>
				</div>
			<?php } ?>
			</div>
			<div id="menu">
				<?= Widgets::menu(); ?>
			</div>
			<div id="notifications"></div>
		</div>
<!-- FIN SIDEBAR -->


<!-- DEBUT CONTENU -->
		<div id="page">
			<div class="alerts">
			<?php
				if (!empty($_success)) {
					echo '<div class="alert alert-success alert-dismissable alert-dismissible auto-dismiss" role="alert">' . alert_close_button() . $_success . '</div>';
				}
				if (!empty($_warning)) {
					echo '<div class="alert alert-danger alert-dismissable alert-dismissible" role="alert">' . alert_close_button() . $_warning . '</div>';
				}
				if (!empty($_notice)) {
					echo '<div class="alert alert-warning alert-dismissable alert-dismissible" role="alert">' . alert_close_button() . $_notice . '</div>';
				}
			?>
			</div>
			<?= $_content ?>
		</div>
<!-- FIN CONTENU -->


<!-- DEBUT FOOTER -->
	<?php App::renderTemplate('footer.php', $variables); ?>
<!-- FIN FOOTER -->

	</div><!-- wrapper -->
</body>
</html>
