<?php defined('EVO') or die('Que fais-tu là?');

has_permission('admin.broadcast', true);
set_time_limit(0);

$subject = (string) App::POST('sujet', '');
$message = (string) App::POST('message', '');
$selected_groups = App::POST('groups');
$cycle   = (int) App::POST('cycle');
$mail_id = 0;
$mail_targets = [];

if (!is_array($selected_groups)) {
	$selected_groups = [];
}

$selected_group_ids = array_map('intval', $selected_groups);

if (IS_POST && (empty($subject) || empty($message) || empty($selected_group_ids) || $cycle < 1)) {
	App::setWarning(__('admin/broadcast.alert_empty_field'));
}
elseif (IS_POST) {
	if (in_array(0, $selected_group_ids, true)) {
		$users = Db::QueryAll('select username, email from {users} where newsletter = 1');
	} else {
		$users = Db::QueryAll('select username, email from {users} where group_id in ('.implode(',', $selected_group_ids).')');
	}

	Db::Insert('newsletter', [
			'author'      => App::getCurrentUser()->id,
			'date_sent'   => time(),
			'groups'      => implode(',', $selected_group_ids),
			'subject'     => $subject,
			'message'     => $message,
			'mail_sent'   => 0,
			'mail_failed' => 0,
	]);

	$mail_id = Db::$insert_id;
	$mail_failed = $mail_sent = 0;

	$html_message = markdown2html($message);
	$text_message = strip_tags($message);

	App::logEvent(null, 'admin', __('admin/broadcast.logevent_news_sent').' #'.$mail_id.': '.$subject);

	// We should use bcc if no substitution (%username%) is needed. It will be faster and might
	// Work better if the smtp server limits mails per day

	foreach($users as $user) {
		$html = strtr($html_message, ['%username%' => $user['username']]);
		$text = strtr($text_message, ['%username%' => $user['username']]);
		$target = html_encode($user['username']) . ' &lt;' . html_encode($user['email']) . '&gt;';

		if (App::sendmail($user['email'], $subject, $text, $html)) {
			$mail_targets[] = html_encode(__('admin/broadcast.state_sent_success')) . ' ' . $target;
			$mail_sent++;
		} else {
			$mail_targets[] = html_encode(__('admin/broadcast.state_sent_error')) . ' ' . $target . ' <span class="text-danger">' . html_encode(__('admin/broadcast.state_sent_error_err')) . '</span>';
			$mail_failed++;
		}
	}

	if ($mail_failed) {
		App::setWarning(__('admin/broadcast.alert_send_fail').''.__plural('%count% membre|%count% membres', $mail_failed));
	}

	if ($mail_sent) {
		App::setSuccess(__('admin/broadcast.alert_send_success').''.__plural('%count% membre|%count% membres', $mail_sent));
	}

	Db::Update('newsletter', ['mail_sent' => $mail_sent, 'mail_failed' => $mail_failed], ['id' => $mail_id]);
}

$preset = __('mail/wrapper', [
	'%message%'  => '',
	'%sitename%' => App::getConfig('name'),
	'%siteurl%'  => App::getConfig('url')
]);

$groups = [
	[
		'id' => 0,
		'name' => 'Newsletter',
		'cnt' => Db::Get('select count(*) from {users} where newsletter = 1'),
	]
];

$other_groups = Db::QueryAll('select g.*, count(*) as cnt from {users} join {groups} as g on g.id = group_id group by group_id order by priority asc');
$groups = array_merge($groups, $other_groups);

$gmap = [];
foreach($groups as $group) {
	$gmap[$group['id']] = $group['name'];
}
$letters = Db::QueryAll('select u.username, n.* from {newsletter} as n left join {users} as u on u.id = n.author order by date_sent desc');

$broadcast_stats = admin_broadcast_build_stats($groups, $letters);
$broadcast_tab = $mail_id ? 'history' : 'compose';
$broadcast_nav = [
	'compose' => ['label' => __('admin/broadcast.tab_compose'), 'icon' => 'fa-paper-plane'],
	'history' => ['label' => __('admin/broadcast.tab_history'), 'icon' => 'fa-clock-rotate-left'],
];
?>

<div class="admin-dashboard admin-broadcast">
	<?= admin_stat_grid($broadcast_stats, ['variant' => 'kpi', 'class' => 'mb-0']) ?>

	<section class="admin-tabs-board admin-broadcast-board">
		<?= admin_broadcast_nav($broadcast_nav, $broadcast_tab) ?>

		<div class="tab-content admin-tabs-board__body admin-broadcast-board__body admin-tabs-panel">
			<?= admin_broadcast_tab_open('compose', $broadcast_tab === 'compose') ?>
			<?php if ($mail_id): ?>
				<?= admin_broadcast_result($mail_targets) ?>
			<?php else: ?>
				<?= admin_broadcast_form($subject, $message, $preset, $groups, $selected_group_ids) ?>
			<?php endif; ?>
			<?= admin_broadcast_tab_close() ?>

			<?= admin_broadcast_tab_open('history', $broadcast_tab === 'history') ?>
			<?= admin_broadcast_history($letters, $gmap, $mail_id) ?>
			<?= admin_broadcast_tab_close() ?>
		</div>
	</section>
</div>

<?php include ROOT_DIR . '/includes/Editors/editors.php'; ?>
<script>
(function () {
	$('.alert').removeClass('auto-dismiss');

	var form = document.getElementById('admin-broadcast-form');
	var recipientsBox = document.getElementById('admin-broadcast-recipients');

	function syncRecipientsBox() {
		if (!recipientsBox) {
			return;
		}

		var value = recipientsBox.querySelector('.admin-broadcast-selectbox__value');
		var selected = Array.prototype.slice.call(recipientsBox.querySelectorAll('.admin-broadcast-selectbox__input:checked'));
		var labels = selected.map(function (input) {
			return input.closest('.admin-broadcast-selectbox__option').querySelector('.admin-broadcast-selectbox__label').textContent.trim();
		});

		value.textContent = labels.length ? labels.join(', ') : value.dataset.placeholder;
	}

	if (form && recipientsBox) {
		var toggle = recipientsBox.querySelector('.admin-broadcast-selectbox__toggle');
		var menu = recipientsBox.querySelector('.admin-broadcast-selectbox__menu');

		toggle.addEventListener('click', function () {
			var expanded = toggle.getAttribute('aria-expanded') === 'true';

			toggle.setAttribute('aria-expanded', expanded ? 'false' : 'true');
			recipientsBox.classList.toggle('is-open', !expanded);
		});

		recipientsBox.querySelectorAll('.admin-broadcast-selectbox__input').forEach(function (input) {
			input.addEventListener('change', function () {
				if (input.value === '0' && input.checked) {
					recipientsBox.querySelectorAll('.admin-broadcast-selectbox__input:not([value="0"])').forEach(function (other) {
						other.checked = false;
					});
				} else if (input.checked) {
					var newsletter = recipientsBox.querySelector('.admin-broadcast-selectbox__input[value="0"]');

					if (newsletter) {
						newsletter.checked = false;
					}
				}

				syncRecipientsBox();
			});
		});

		document.addEventListener('click', function (event) {
			if (!recipientsBox.contains(event.target)) {
				toggle.setAttribute('aria-expanded', 'false');
				recipientsBox.classList.remove('is-open');
			}
		});

		syncRecipientsBox();
	}

	var history = document.getElementById('admin-broadcast-history');

	if (history) {
		history.querySelectorAll('.admin-broadcast-history__item').forEach(function (item) {
			item.addEventListener('click', function () {
				history.querySelectorAll('.admin-broadcast-history__item').forEach(function (entry) {
					entry.classList.remove('active');
				});
				history.querySelectorAll('.admin-broadcast-preview').forEach(function (preview) {
					preview.hidden = true;
				});

				item.classList.add('active');
				document.getElementById(item.dataset.target).hidden = false;
			});
		});
	}

	if (document.getElementById('editor')) {
		load_editor('editor', '<?= App::getConfig('editor') ?>');
	}
})();
</script>
