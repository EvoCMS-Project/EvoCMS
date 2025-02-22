<script>
// <!--
	window._editor = null;
	var css_url = '<?= App::getAsset('css/wysiwyg.css'); ?>';

	function load_editor(selector, format, destroy) {
		let editors = {
			wysiwyg: <?php include __DIR__ . '/ckeditor.js' ?>,
			markdown: <?php include __DIR__ . '/markitup.js' ?>,
			bbcode: <?php include __DIR__ . '/ckeditor_bb.js' ?>,
		};
		destroy && window._editor && window._editor.destroy && window._editor.destroy();
		window._editor = new (editors[format] || editors['wysiwyg'])(selector);
		window._editor.display();
	}
// -->
</script>