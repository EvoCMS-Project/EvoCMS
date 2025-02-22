<div id="footer">
	<div class="copyright">
		© <?= date('Y') ?> <a href="<?= App::getURL('/') ?>" target="_blank"><?= html_encode(App::getConfig('name')) ?></a> <span class="powered-by"><?= __('main.powered_by') ?> <a href="http://www.evolution-network.ca" target="_blank"><strong>Evo-CMS</strong></a></span>
	</div>
	<?php if (Db::$queryLogging): ?>
		<div class="debug-info">
			<small style="cursor:pointer;" onclick="window.scrollTo(0, $('#debug').toggle().offset().top);"><?= __plural('%count% requête|%count% requêtes', Db::$num_queries);?> en <?= __plural('%count% seconde|%count% secondes', round(Db::$exec_time, 4)); ?>.</small>
			<br>&nbsp;<br>
			<div id="debug" style="display:none"><?php if (has_permission('admin.sql')) echo Widgets::SQLQueries(Db::$queries); ?></div>
		</div>
	<?php endif; ?>
	<script src="<?= App::getAsset('js/evo-cms.js') ?>"></script>

	<?php App::trigger('footer'); ?>
</div>
