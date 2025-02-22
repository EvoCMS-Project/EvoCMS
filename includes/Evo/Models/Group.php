<?php
namespace Evo\Models;
use \Db;

class Group extends \Evo\Model
{
	public const TABLE = 'groups';
	public const HAS_ONE   = [];
	public const HAS_MANY  = ['users' => ['group_id', User::class]];
	public const SERIALIZE = [];

	public function getPermissions(): array
	{
		foreach (Db::QueryAll('select * from {permissions} where group_id = ? and value <> 0', $this->id) as $p) {
			if ($p['related_id'] >= 0) {
				$permissions[$p['name']][$p['related_id']] = $p['value'];
			} else {
				$permissions[$p['name']] = $p['value'];
			}
		}
		return $permissions ?? [];
	}
}
