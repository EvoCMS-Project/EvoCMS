<?php defined('EVO') or die('Que fais-tu là?');

use Evo\Models\User;

try {
	$action = App::REQ('action', 'login');

	if ($action === 'logout') {
		App::logout();
		App::redirect('/');
	}

	if ($action === 'login' && App::POST('login'))
	{
		if (!$user = User::find(App::POST('login'), 'username') ?: User::find(App::POST('login'), 'email')) {
			throw new Warning(__('login.account_invalid'));
		}

		if ($user->locked == 2) { // Account needs to be activated
			if (App::POST('resendkey')) {
				if (!send_activation_email($user->username)) {
					throw new Warning(__('login.resendkey_warning'));
				}
				App::setNotice(__('login.resendkey_notice'));
			} else {
				throw new Warning(
					__('login.resendkey_locked'),
					'<form method="post"><input type="hidden" name="login" value="' . html_encode($user->username). '">
					<button type="submit" name="resendkey" value="1">'. __('login.resendkey_btn') .'</button></form>'
				);
			}
		} else {
			if ($user->verifyPassword(App::POST('pass', ''))) {
				App::sessionStart($user->id, App::POST('remember') ? true : false);
				App::redirect(App::REQ('redir'));
			}
			throw new Warning(__('login.pass_compare_warning'));
		}
	}
	elseif ($action === 'forget' && App::POST('login'))
	{
		if (!$user = User::find(App::POST('login'), 'username') ?: User::find(App::POST('login'), 'email')) {
			throw new Warning(__('login.account_invalid'));
		}

		$key = $user->getActivationToken(true);

		$replace = [
			'username' => $user->username,
			'resetlink' => App::getURL('login', ['action' => 'reset', 'key' => $key, 'username' => $user->username])
		];

		if (sendmail_template($user->email, 'account.reset_password', $replace)) {
			App::logEvent($user->id, 'user', __('login.forget_log'));
			App::setSuccess(__('login.resendkey_success'));
			$action = '';
		} else {
			App::setWarning(__('login.resendkey_warning'));
		}
	}
	elseif($action === 'reset')
	{
		if (!$user = User::find(App::GET('username'), 'username')) {
			throw new Warning(__('login.account_invalid'));
		}

		if (App::GET('key') !== $user->reset_key) {
			throw new Warning(__('login.reset_link_warning'));
		}

		if (App::POST('new_password') && App::POST('new_password1')) {
			if (App::POST('new_password') != App::POST('new_password1')) {
				throw new Warning(__('login.reset_pass_warning'));
			}
			if ($user->changePassword(App::POST('new_password'))) {
				App::setSuccess(__('login.reset_success'));
			} else {
				App::setWarning(__('login.reset_fail'));
			}
			$action = 'login';
		}
	}
	elseif ($action === 'activate' && App::GET('key') && App::GET('username'))
	{
		if (!$user = User::find(App::POST('login'), 'username')) {
			throw new Warning(__('login.account_invalid'));
		}

		if ($user->reset_key === App::GET('key')) {
			$user->locked = 0;
			$user->save();
			App::logEvent($user->id, 'user', __('login.activation'));
			App::sessionStart($user->id);
			App::redirect('user');
		} else {
			return App::setWarning(__('login.reset_already'));
		}
	}
}
catch(Warning $e) {
	App::setWarning($e->getTitle());
}

App::renderTemplate('pages/login.php', [
	'action' => $action,
	'login' => App::POST('login', ''),
	'password' => App::POST('pass', ''),
]);
