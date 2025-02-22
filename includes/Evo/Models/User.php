<?php
namespace Evo\Models;
use \App;

class User extends \Evo\Model
{
	public const TABLE = 'users';
	public const HAS_ONE   = ['group' => ['group_id', Group::class]];
	public const HAS_MANY  = ['files' => ['poster', File::class]];
	public const SERIALIZE = ['social'];


	public function changePassword(string $newPassword)
	{
		$this->password = password_hash($newPassword, PASSWORD_DEFAULT);
		App::logEvent($this->id, 'user', __('login.reset_log'));
		return $this->save();
	}

	public function verifyPassword(string $password)
	{
		return password_verify($password, $this->password);
	}

	public function getInviteToken(bool $renew = false)
	{
		if (!$this->raf_token || $renew) {
			$this->raf_token = random_hash(28);
			$this->save();
		}
		return $this->raf_token;
	}

	public function getActivationToken(bool $renew = false)
	{
		if (!$this->reset_key || $renew) {
			$token = random_hash(28);
			// $this->reset_key = sha1($token);
			$this->reset_key = $token;
			$this->save();
		}
		return $this->reset_key;
	}

	public function getLink($arg = null): ?string
	{
		return App::getURL('profile', $this->username);
	}
}
