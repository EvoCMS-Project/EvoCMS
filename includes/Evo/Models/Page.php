<?php
namespace Evo\Models;

class Page extends \Evo\Model
{
	public const TABLE     = 'pages';
	public const HAS_ONE   = ['poster' => User::class];
	public const HAS_MANY  = ['revisions' => null];
	public const SERIALIZE = [];
}
