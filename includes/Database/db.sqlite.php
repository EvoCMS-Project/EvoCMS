<?php
/*
 * Db Singleton abstraction.
 */

class Db extends Database
{
	public static function Connect($host, $user, $password, $database = '', $prefix = '')
	{
		if ($database[0] != '/')
			$database = ROOT_DIR . '/' . basename($database);

		self::$database = $database;
		self::$prefix = $prefix;

		self::$db = new PDO('sqlite:' . $database);
		self::$db->sqliteCreateFunction('LOCATE', 'strpos', 2);
		self::$db->sqliteCreateFunction('IF', function($a, $b, $c) {return $a ? $b : $c;}, 3);
		self::$db->sqliteCreateFunction('CONCAT', function() {return implode(func_get_args());}, 3);
		self::$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
		self::$db->setAttribute(PDO::ATTR_TIMEOUT, 30);
		self::$db->exec('PRAGMA temp_store=MEMORY;');
		self::$db->exec('PRAGMA journal_mode=WAL;');
	}


	public static function CreateTable($table, $fields, $if_not_exists = false, $drop_if_exists = false)
	{
		if ($drop_if_exists)
			self::DropTable($table, true);

		$table = self::GetTableName($table);
		$rows = [];

		foreach($fields as $name => $field) {
			is_array($field) or $field = array($field);

			$type = self::ParseColType($field[0]);

			if ($field[0] == 'increment' || $field[0] == 'primary') {
				$field[2] = Database::AI;
				$field[1] = null;
			}

			$row = '`' . $name . '` ' . $type . ' ';

			if (isset($field[2])) {
				$row .=  'PRIMARY KEY';
			}

			if (isset($field[1])) {
				$row .= ' NOT NULL ';
				$row .= ' DEFAULT ' . self::escapeValue($field[1]) . ' ';
			} elseif (count($field) == 1) {
				$row .= ' NOT NULL ';
			}

			$rows[] = $row;
		}
		$create  = $if_not_exists ? 'CREATE TABLE IF NOT EXISTS ' : 'CREATE TABLE ';
		$create .= '`' . $table . '` (' . implode(',', $rows) . ')';
		return self::Exec($create);
	}


	public static function DropTable($table, $if_exists = true)
	{
		$table = self::GetTableName($table);
		$drop = $if_exists ? 'DROP TABLE IF EXISTS ': 'DROP TABLE ';
		return self::Exec($drop . '`' . $table . '`');
	}


	public static function AddIndex($table, $type, $fields)
	{
		$table = self::GetTableName($table);
		$type = strtoupper($type);

		if (count($fields) < 1)
			return false;

		switch($type) {
			case 'PRIMARY KEY':
			case 'UNIQUE':
				$type = 'UNIQUE INDEX'; break;
		}

		$index_name = $table.'_'.implode('_', $fields);

		foreach ($fields as &$field) $field = '`' . $field . '`';

		return self::Exec('CREATE '.$type.' '. $index_name .' ON `' . $table . '` (' . implode(',', $fields) . ')');
	}


	public static function DropColumn($table, $col_name)
	{
		//$table = self::GetTableName($table);
		//return self::Exec("ALTER TABLE $table DROP COLUMN $col_name");
	}


	public static function AddColumn($table, $col_name, $col_type, $primary = false, $auto_increment = false, $default = null)
	{
		$add = 'ALTER TABLE `' . self::GetTableName($table) . '` ADD COLUMN `' . $col_name . '` ' . self::ParseColType($col_type) . ' ';

		if ($primary)
			$add .= ' PRIMARY KEY';

		if ($auto_increment)
			$add .= ' AUTO_INCREMENT';

		if ($default !== null)
			$add .= ' DEFAULT "'.self::escape($default) . '" ';

		return self::Exec($add);
	}


	public static function GetColumns($table, $names_only = false): array
	{
		$columns = self::QueryAll('PRAGMA table_info('.self::GetTableName($table).')');
		if ($names_only) {
			return array_column($columns, 'name');
		}

		foreach($columns as $column) {
			if ($column['type'] === 'numeric') {
				$column['type'] = 'float';
			}
			$return[] = [
				'name' => $column['name'],
				'type' => strtolower($column['type']),
				'default' => $column['dflt_value'],
				'null' => !$column['notnull'],
				'key' => $column['pk'],
				'extra' => null,
			];
		}
		return $return;
	}


	public static function TableExists($table)
	{
		return self::QueryAll('SELECT name FROM sqlite_master WHERE type="table" and name = ?', self::GetTableName($table));
	}


	public static function GetTables($full_schema = false): array
	{
		$tables = self::QueryAll('SELECT name, * FROM sqlite_master WHERE type="table"', true);
		return $full_schema ? $tables : array_keys($tables);
	}


	public static function Truncate($table)
	{
		$table = self::GetTableName($table);
		return  self::Exec("delete from {$table}") +
				self::Exec("delete from sqlite_sequence where name = ?", $table);
	}


	public static function ParseColType($type)
	{
		list($type, $length) = explode('|', $type.'|255');

		switch($type) {
			case 'string':
				return 'VARCHAR('.$length.')'; // for mysql import/export compatibility
			case 'text':
				return 'TEXT';
			case 'int':
			case 'integer':
			case 'tinyint':
				return 'INTEGER';
			case 'float':
			case 'double':
				return 'REAL';
			case 'primary':
			case 'increment':
				return 'INTEGER';
		}

		return $type;
	}


	public static function Import($input = null, $format = 'sql')
	{

	}


	public static function Export($output = null, $format = 'sql')
	{
		if (class_exists('\\Mysqldump\\Mysqldump')) {
			$dumpSettings = array(
				'compress' => 'Buffer',
				'no-data' => false,
				'add-drop-database' => false,
				'add-drop-table' => false,
				'single-transaction' => false,
				'lock-tables' => false,
				'add-locks' => true,
				'extended-insert' => true,
				'disable-foreign-keys-check' => false
			);

			$dumper = new \Mysqldump\Mysqldump(self::$database, self::$user, self::$password, self::$host, 'sqlite', $dumpSettings);
			return $dumper->start();
		} else {
			// run sqlite self::$database .dump
		}
	}
}