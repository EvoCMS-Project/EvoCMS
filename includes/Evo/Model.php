<?php
namespace Evo;
use \Db;

class Model
{
	public const TABLE       = '';
	public const PRIMARY_KEY = 'id';
	public const HAS_ONE     = [];
	public const HAS_MANY    = [];
	public const SERIALIZE   = [];

	protected static $cache = [];

	protected $_data = [];
	protected $_overlay = [];
	protected $_modified = [];
	protected $_isNew = true;


	public function __construct(array $data, bool $isNew = true)
	{
		if ($isNew) {
			$this->_modified = $data;
		} else {
			foreach (static::SERIALIZE as $column) {
				if (isset($data[$column])) {
					$data[$column] = @unserialize($data[$column]);
				}
			}
			self::$cache[static::class][$data[static::PRIMARY_KEY]] = $this;
		}
		$this->_data = $data;
		$this->_isNew = $isNew;
	}


	public function __get(string $name)
	{
		if (isset(static::HAS_ONE[$name]) && !isset($this->_overlay[$name])) {
			[$column, $class] = static::HAS_ONE[$name];
			$this->_overlay[$name] = $class::find($this->_data[$column] ?? null); // Using ->__get could cause a loop
		}
		if (isset(static::HAS_MANY[$name]) && !isset($this->_overlay[$name])) {
			[$column, $class] = static::HAS_MANY[$name];
			$this->_overlay[$name] = $class::select("\"$column\" = ?", $this->{static::PRIMARY_KEY});
		}
		return $this->_overlay[$name] ?? $this->_data[$name] ?? null;
	}


	public function __set(string $name, $value)
	{
		$this->_data[$name] = $value;
		$this->_modified[$name] = $value;
	}


	/**
	 * @return static[]
	 */
	public static function select(string $where = '1', ...$params)
	{
		foreach (Db::QueryAll("SELECT * FROM {".static::TABLE."} WHERE $where", ...$params) as $item) {
			$items[$item[static::PRIMARY_KEY]] = new static($item, false);
		}
		return $items ?? [];
	}


	/**
	 * @return static|null
	 */
	public static function find($value, string $column = null)
	{
		if ($column === null && isset(self::$cache[static::class][$value])) { // We can only cache primary key requests
			return self::$cache[static::class][$value];
		}
		return current(static::select(Db::escapeField($column ?? static::PRIMARY_KEY).' = ? LIMIT 1', $value)) ?: null;
	}


	public static function clearCache()
	{
		self::$cache = [];
	}


	public function save(): bool
	{
		if (!$values = $this->_modified) {
			return false;
		}

		$this->_modified = [];
		$this->beforeSave($values);

		foreach (static::SERIALIZE as $column) {
			if (array_key_exists($column, $values)) {
				$values[$column] = serialize($values[$column]);
			}
		}

		if ($this->_isNew) {
			Db::Insert(static::TABLE, $values);
			if (empty($this->{static::PRIMARY_KEY})) {
				$this->{static::PRIMARY_KEY} = Db::$insert_id;
			}
			// self::$cache[static::class][$this->{static::PRIMARY_KEY}] = $this;
			$this->_isNew = false;
			return true;
		} else {
			return Db::Update(static::TABLE, $values, [static::PRIMARY_KEY => $this->{static::PRIMARY_KEY}]);
		}
	}


	public function delete(): bool
	{
		if (Db::Delete(static::TABLE, [static::PRIMARY_KEY => $this->{static::PRIMARY_KEY}]) > 0) {
			unset(self::$cache[static::class][$this->{static::PRIMARY_KEY}]);
			$this->afterDelete();
			return true;
		}
		return false;
	}


	public function toArray(bool $load_rel = false, bool $recursive = false): array
	{
		$data = $this->_data;

		if ($load_rel) {
			foreach(static::HAS_ONE as $key => $rel) {
				$data[$key] = $this->$key;
			}
			foreach(static::HAS_MANY as $key => $rel) {
				$data[$key] = $this->$key;
			}
		}

		return $data;
	}


	public function getLink($arg = null): ?string
	{
		return null;
	}


	/* filters */

	protected function beforeSave(array &$modified)
	{  }


	protected function afterDelete()
	{  }
}
