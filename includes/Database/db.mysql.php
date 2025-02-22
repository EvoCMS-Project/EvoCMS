<?php
/*
 * Db Singleton abstraction.
 */

class Db extends Database
{
	public static function Connect($host, $user, $password, $database = '', $prefix = '')
	{
		self::$host = $host;
		self::$user = $user;
		self::$password = $password;
		self::$database = $database;
		self::$prefix = $prefix;

		self::$db = new PDO('mysql:host='.$host.';charset=utf8mb4;dbname='.$database, $user, $password);
		self::$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
		//self::$db->setAttribute(PDO::ATTR_EMULATE_PREPARES, false); // Disabled to be consistent with SQLite (for now)
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
				if ($field[2] == Database::PRIMARY)
					$row .=  'PRIMARY KEY';
				elseif($field[2] == Database::AI)
					$row .= 'PRIMARY KEY AUTO_INCREMENT';
			}

			if (isset($field[1])) {
				$row .= ' NOT NULL ';
				$row .= ' DEFAULT ' . self::escapeValue($field[1]) . ' ';
			} elseif (count($field) == 1) {
				$row .= ' NOT NULL';
			}

			$rows[] = $row;
		}

		$create  = $if_not_exists ? 'CREATE TABLE IF NOT EXISTS ' : 'CREATE TABLE ';
		$create .= '`' . $table . '` (' . implode(',', $rows) . ') CHARACTER SET=utf8mb4';

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
		$type = strtoupper($type);
//		if ($type !== 'UNIQUE' && $type !== 'PRIMARY' && $type !== 'PRIMARY')
//			$type= 'INDEX';

		if (count($fields) < 1)
			return false;

		foreach ($fields as &$field) $field = '`' . $field . '`';

		return self::Exec('ALTER TABLE `' . self::GetTableName($table) . '` ADD ' . $type . '(' . implode(',', $fields) . ')');
	}


	public static function DropColumn($table, $col_name)
	{
		$table = self::GetTableName($table);
		return self::Exec("ALTER TABLE $table DROP COLUMN $col_name");
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

		return Db::Exec($add);
	}


	public static function GetColumns($table, $names_only = false): array
	{
		$columns = self::QueryAll('SHOW COLUMNS FROM `'.self::GetTableName($table).'`');
		if ($names_only) {
			return array_column($columns, 'Field');
		}
		$types = ['varchar' => 'string', 'int' => 'integer', 'tinyint' => 'integer'];

		foreach($columns as $column) {
			foreach($types as $k => $v) {
				if (stripos($column['Type'], $k) !== false) {
					$column['Type'] = $v;
					break;
				}
			}
			$return[] = [
				'name' => $column['Field'],
				'type' => strtolower($column['Type']),
				'default' => $column['Default'],
				'null' => $column['Null'] == 'YES',
				'key' => $column['Key'],
				'extra' => $column['Extra'],
			];
		}
		return $return;
	}


	public static function TableExists($table)
	{
		return self::QueryAll('SHOW TABLES LIKE "' . self::GetTableName($table) . '"');
	}


	public static function GetTables($full_schema = false): array
	{
		return self::QueryAll('SHOW TABLES');
	}


	public static function Truncate($table)
	{
		return self::Exec("TRUNCATE {$table}");
	}


	public static function ParseColType($type)
	{
		list($type, $length) = explode('|', $type.'|255');

		switch($type) {
			case 'string':
				return 'varchar('.$length.')';
			case 'text':
				return 'text';
			case 'int':
			case 'integer':
				return 'int(11)';
			case 'tinyint':
				return 'tinyint';
			case 'float':
			case 'double':
				return 'float';
			case 'primary':
			case 'increment':
				return 'int(11)';
			default:
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

			$dumper = new \Mysqldump\Mysqldump(self::$database, self::$user, self::$password, self::$host, 'mysql', $dumpSettings);
			return $dumper->start();
		} else {
			// run mysqldump self::$database
		}
	}
}