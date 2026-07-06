<!DOCTYPE html>
<html>
	<head>
		<style>
			div.sql-query {
				border: 1px solid #222;
				padding: 0px 10px 10px;
			}
		</style>
	</head>
	<body class="tpl-exception">
		<h2>Une erreur de type <?= htmlspecialchars(get_class($e), ENT_COMPAT, 'utf-8') ?> s'est produite!</h2>
		<?php
		if (is_callable([$e, 'getTitle'])) {
			echo '<h4>' . htmlspecialchars((string) $e->getTitle(), ENT_COMPAT, 'utf-8') . '</h4>';
		}
		?>
		<pre style="white-space: pre-wrap"><?php
			echo substr($e->getFile(), strlen(ROOT_DIR)) . '#' . $e->getLine() . ': <strong>' . htmlspecialchars($e->getMessage(), ENT_COMPAT, 'utf-8') . "</strong>\n\n";

			if (!empty($_warning))
				echo 'warning: ' . $_warning . "\n";
			if (!empty($_notice))
				echo 'notice: '  . $_notice . "\n";
			if (!empty($_success))
				echo 'success: ' . $_success . "\n";

			$show_trace = false;

			if (class_exists('App', false)) {
				try {
					$show_trace = !empty(App::getCurrentUser()?->id);
				} catch (Throwable $ignored) {
					$show_trace = false;
				}
			}

			if ($show_trace) {
				echo $e->getTraceAsString()."\n\n";
				echo "<hr>Last PHP error to occur (May or may not be relevant):\n";
				print_r(error_get_last());
			} else {
				echo preg_replace('@(^|\n)(#[0-9]+ )(' . preg_quote(ROOT_DIR, '@') . ')?([^:]+).*@', '$1$2$4', $e->getTraceAsString());
			}
		?></pre>
	</body>
</html>
