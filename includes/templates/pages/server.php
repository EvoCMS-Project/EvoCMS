<table class="table table-bordered table-striped">
	<thead>
		<tr>
			<th colspan="2"><?=__('server.ping', ['%server%' => $server->address, '%seconds%' => $server->query_time]) ?></th>
		</tr>
	</thead>
	<tbody>
<?php if (!empty($server->query)) { ?>
	<?php foreach($server->query as $key => $value) { ?>
		<tr><td><?=html_encode($key)?></td><td>
		<?php if ($key == 'favicon' || $key == 'albumArt') { ?>
			<img src="<?=str_replace("\n", '', $value)?>">
		<?php } elseif (is_array($value)) { ?>
		<pre>
			<?php print_r($value) ?>
		</pre>
		<?php } else { ?>
			<?=$value?>
		<?php } ?>
		</td></tr>
	<?php } ?>
<?php } elseif(!empty($server->online)) { ?>
	<tr><td colspan="2"><?=__('server.is_online') ?></td></tr>
<?php } else { ?>
	<tr><td colspan="2"><?=__('server.no_reply') ?></td></tr>
<?php } ?>
	</tbody>
</table>
