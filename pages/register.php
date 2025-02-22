<?php defined('EVO') or die('Que fais-tu là?');

$referral = App::REQ('raf') ? Db::Get('select username from {users} where raf_token = ?', App::REQ('raf')) : '';

if (!has_permission('administrator') && (App::getConfig('open_registration') == 0 || (App::getConfig('open_registration') == 2 && !$referral))) {
	App::setWarning(__('register.warning_reg_closed'));
	return;
}

$fields = [
	'username' => [
		'label' => __('register.field_username'),
		'type' => 'text',
		'value' => App::POST('username'),
		'validation' => PREG_USERNAME,
		'required' => true,
	],
	'email' => [
		'label' => __('register.field_email'),
		'type' => 'text',
		'value' => App::POST('email'),
		'validation' => PREG_EMAIL,
		'required' => true,
	],
	'password' => [
		'label' => __('register.field_password'),
		'type' => 'password',
		'value' => App::POST('password'),
		'validation' => PREG_PASSWORD,
		'required' => true,
	],
	'password_confirm' => [
		'label' => __('register.field_pass_confirm'),
		'type' => 'password',
		'value' => App::POST('password_confirm'),
	],
	[
		'label' => '',
		'type' => 'multiple',
		'validation' => PREG_DIGIT,
		'fields' => [
			'newsletter' => [
				'label' => __('register.field_sub_newsletter'),
				'type' => 'checkbox',
				'checked' => (!IS_POST || App::POST('newsletter')),
				'value' => 1,
				'validation' => PREG_DIGIT,
			],
		],
	],
];

if ($referral) {
	$fields['raf'] = [
		'label' => __('register.field_raf'),
		'type' => 'text',
		'value' => $referral,
		'attributes' => ['disabled'],
	];
}

if (IS_POST) {
	$reset = ['newsletter' => 0, 'discuss' => 0];
	$form_values = array_intersect_key(App::POST() + $reset, $fields); // We keep only valid elements in case of form forgery
	$warnings = [];

	foreach ($form_values as $field => $value) {
		$f = $fields[$field];
		if (!isset($f['validation'])) {
			continue;
		}
		if (
			((is_array($f['validation'])  && !in_array($value, $f['validation'])) || // If value not within array
			(is_string($f['validation']) && !preg_match($f['validation'], $value))) // OR if not acceptable string
			&& !($f['required'] !== true && $value === '') // AND if the parameter is not both empty and optional
		) {
			$warnings[] = __('register.field_invalid').''.$field;
		} elseif ($f['required'] === true && $value === '') {
			$warnings[] = __('register.field_require').''.$field;
		}
	}

	if ($ban = App::checkBanlist(App::POST())) {
		$warnings[] = __('register.warning_banlist').'' . html_encode($ban['reason']);
	}

	$user_exists = Db::Get('select username FROM {users} WHERE username = ? or email = ?', App::POST('username'), App::POST('email'));

	if ($user_exists) {
		$warnings[] = strcasecmp($user_exists, App::POST('username')) !== 0 ? __('register.warning_email_existed') : __('register.warning_uname_existed');
	}

	if ($warnings) {
		App::setWarning(implode('<br>', $warnings));
	} else {
		$q = Db::Insert('users', [
			'username'   => App::POST('username'),
			'country'    => null,
			'group_id'   => App::getConfig('default_user_group'),
			'locked'     => App::getConfig('open_registration') == 3 ? 2 : 0,
			'reset_key'  => App::getConfig('open_registration') == 3 ? random_hash(32) : null,
			'password'   => password_hash(App::POST('password'), PASSWORD_DEFAULT),
			'email'      => App::POST('email'),
			'newsletter' => App::POST('newsletter') ? 1 : 0,
			'ingame'     => App::POST('ingame') ?: null,
			'raf'        => $referral,
			'avatar'     => 'default',
			'registered' => time(),
			'registration_ip' => $_SERVER['REMOTE_ADDR'],
		]);

		if ($q !== false) {
			$uid = Db::$insert_id;

			App::logEvent($uid, 'user', __('register.succes_log'));
			App::trigger('user_created', [App::getUser($uid), 'register']);

			if (!has_permission('administrator') && App::getConfig('open_registration') == 3) {
				if (send_activation_email(App::POST('username'))) {
					return print '<div class="bs-callout bs-callout-success">'.__('register.succes_register1').'</div>';
				}
				else {
					App::logEvent(null, 'admin', __('register.mail_issue_log') .''. App::POST('email'));
					App::logEvent(null, 'user', __('register.rollback1_log') .''. App::POST('username') .''. __('register.rollback2_log'));
					Db::Delete('users', ['id' => $uid]);
					App::setWarning(__('register.warning_sendmail_fail'));
					return;
				}
			}

			if (!has_permission()) {
				App::sessionStart($uid);
			}

			return print '<div class="bs-callout bs-callout-success">'. __('register.succes_register2') .'</div>';
		} else {
			return print '<div class="bs-callout bs-callout-warning">'. __('register.warning_register1') .'</div>';
		}
	}
}
echo Widgets::formBuilder(__('register.account_title1'), $fields, true, __('register.account_title2'));
?>

<script>
$('form').submit(function() {
	if ($('#password').val() != $('#password_confirm').val()) {
		alert('Les mots de passe de sont pas identiques!');
		return false;
	}
});
</script>