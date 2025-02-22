<?php
namespace Evo\Models;

class Subscription extends \Evo\Model
{
	public const TABLE     = 'subscriptions';
	public const HAS_ONE   = [];
	public const HAS_MANY  = [];
	public const SERIALIZE = [];


	public function notify()
	{

	}

	public static function subscribe($type, $rel_id, $user_id)
	{

	}

	public static function unsubscribe($type, $rel_id, $user_id)
	{

	}

	public static function notifySubscribers($subscription_id, $message)
	{

	}
}
