<div class="bs-callout bs-callout-danger">
	<h4><?= __('errors/banned.title', ['%from%' => Format::today($ban['created'], true), '%to%' => Format::today($ban['expires'], true)]) ?></h4>
	<?= __('errors/banned.message') ?>
	<p><strong><?= html_encode($ban['reason']) ?></strong></p>
</div>
