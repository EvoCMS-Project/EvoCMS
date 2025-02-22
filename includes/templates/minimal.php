<!DOCTYPE html>
<html>
	<head>
		<?= App::renderTemplate('head.php', $variables) ?>
		<link href="<?= App::getAsset('css/bootstrap.min.css') ?>" rel="stylesheet">
		<style>
			body {
				max-width: 800px;
				margin: 0 auto;
				margin-top: 2em;
			}
		</style>
	</head>

	<body>
		<?= $_content ?>
	</body>
</html>