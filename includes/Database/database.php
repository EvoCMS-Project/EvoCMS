<?php
/*
 * Db Singleton abstraction.
 */

abstract class Database
{
	const PRIMARY = 1;
	const AI = 2;

	public static $db = null;

	public static $queryLogging = true;
	public static $queries = array();
	public static $num_queries = 0;
	public static $exec_time = 0;
	public static $errno = 0;
	public static $error = null;
	public static $affected_rows = 0;
	public static $insert_id = 0;

	public static $host, $user, $password, $database, $prefix;

	abstract public static function Connect($host, $user, $password, $database = '', $prefix = '');

	abstract public static function CreateTable($table, $fields, $if_not_exists = false, $drop_if_exists = false);

	abstract public static function DropTable($table, $if_exists = true);

	abstract public static function DropColumn($table, $col_name);

	abstract public static function AddIndex($table, $type, $fields);

	abstract public static function AddColumn($table_name, $col_name, $col_type, $primary = false, $auto_increment = false, $default = null);

	abstract public static function GetColumns($table, $names_only = false): array;

	abstract public static function TableExists($table);

	abstract public static function GetTables($full_schema = false): array;

	abstract public static function Truncate($table);

	abstract public static function Import($input, $format = 'sql');

	abstract public static function Export($output = null, $format = 'sql');


	public static function AvailableDrivers()
	{
		$pdo = PDO::getAvailableDrivers();
		$files = array_map('basename', glob(__DIR__.'/db.*.php'));
		$files = str_replace(['db.', '.php'], '', $files);

		return array_intersect($pdo, $files);
	}


	public static function DriverName()
	{
		return self::$db->getAttribute(PDO::ATTR_DRIVER_NAME);
	}


	public static function ServerVersion()
	{
		return self::$db->getAttribute(PDO::ATTR_SERVER_VERSION);
	}


	public static function GetTableName($table)
	{
		if ($table[0] === '"' || $table[0] === '`' || strpos($table, '.') !== false) {
			return $table;
		}
		$table = self::$prefix . trim($table, '{}');

		return $table;
	}


	public static function AddColumnIfNotExists($table_name, $col_name, $col_type, $primary = false, $auto_increment = false, $default = null)
	{
		$columns = (array)static::GetColumns($table_name, true);

		if (!in_array($col_name, $columns)) {
			return static::AddColumn($table_name, $col_name, $col_type, $primary, $auto_increment, $default);
		}

		return true;
	}


	public static function DropColumnIfExists($table_name, $col_name)
	{
		$columns = (array)static::GetColumns($table_name, true);

		if (in_array($col_name, $columns)) {
			return static::DropColumn($table_name, $col_name);
		}

		return true;
	}


	public static function escapeValue($value, $quote = true)
	{
		/* if (ctype_digit($value)) $value = (int) $value; */
		if (ctype_digit((string)$value)) {
			$value = (int) $value;
		}

		switch (gettype($value)) {
			case 'NULL': return 'NULL';
			case 'float':
			case 'double':
			case 'integer': return $value;
			default: return $quote ? self::$db->quote($value) : substr(self::$db->quote($value), 1, -1);
		}
	}


	public static function escapeField($value)
	{
		return '`' . str_replace('`', '``', $value) . '`';
	}


	public static function escape($string)
	{
		return substr(self::$db->quote($string), 1, -1);
	}


	/**
	 * Execute an SQL statement and returns a PDO statement on success
	 *
	 * @param string $q SQL query
	 * @param mixed ...$args placeholder replacements
	 * @return PDOStatement
	 */
	public static function Query($query, ...$args): PDOStatement
	{
		try {
			$query = preg_replace('!([^a-z0-9])\{([_a-z0-9]+)\}([^a-z0-9]|$)!i', '$1' . self::$prefix . '$2$3', $query);

			if ($args) {
				if (is_array($args[0])) $args = $args[0]; // Db::Query("SQL", [':named' => params])
				array_unshift($args, ''); // Remove 0 index, PDO is 1-based
				unset($args[0]);
			}

			$start = microtime(true);

			$q = Db::$db->prepare($query);
			foreach($args as $i => $arg) {
				if (ctype_digit((string)$arg))
					$q->bindValue($i, (int)$arg, PDO::PARAM_INT);
				elseif($arg === null)
					$q->bindValue($i, $arg, PDO::PARAM_NULL);
				else
					$q->bindValue($i, $arg);
			}
			$q->execute();

			return $q;
		}
		finally {
			$error = empty($q) ? self::$db->errorInfo() : $q->errorInfo();

			self::$errno = $error[1];
			self::$error = $error[2];
			self::$affected_rows = empty($q) ? 0 : $q->rowCount();
			self::$insert_id = empty($q) ? 0 : self::$db->lastInsertId();
			self::$exec_time += microtime(true) - $start;
			self::$num_queries++;

			if (self::$queryLogging) {
				self::$queries[self::$num_queries] = array(
					'query' => $query,
					'params' => &$args,
					'time' => microtime(true) - $start,
					'errno' => self::$errno,
					'error' => self::$error,
					'affected_rows' => self::$affected_rows,
					'fetch' => 0,
					'insert_id' => self::$insert_id,
				);

				foreach(debug_backtrace(false) as $trace) {
					if (isset($trace['file']) && $trace['file'] != __FILE__) {
						self::$queries[self::$num_queries]['trace'] = $trace;
						break;
					}
				}
			}
		}
	}


	/**
	 * This function returns an array of all rows returned by the SQL query
	 *
	 * @param string $query SQL query
	 * @param mixed ...$args placeholder replacements
	 * @param bool $use_first_col_as_key
	 * @return array
	 */
	public static function QueryAll($query, ...$args): array
	{
		$use_first_col_as_key = is_bool(end($args)) ?  array_pop($args) : false;

		$return = [];
		$query = self::Query($query, ...$args);

		if ($use_first_col_as_key) { //return FETCH_GROUP
			while($row = $query->fetch(PDO::FETCH_ASSOC)) $return[reset($row)] = $row;
		} else {
			$return = $query->fetchAll(PDO::FETCH_ASSOC);
		}

		if (self::$queryLogging) {
			self::$queries[self::$num_queries]['fetch'] = count($return);
		}

		return $return;
	}


	/**
	 * Alias for QueryAll
	 */
	public static function GetAll($query, ...$args): array
	{
		return self::QueryAll($query, ...$args);
	}


	/**
	 * This function returns one column if only one column is present in the result. Otherwise it returns the whole row.
	 *
	 * @param string $query SQL query
	 * @param mixed ...$args placeholder replacements
	 * @return mixed
	 */
	public static function Get($query, ...$args)
	{
		$query = self::Query($query, ...$args);

		if ($row = $query->fetch(PDO::FETCH_ASSOC)) {
			if (self::$queryLogging) {
				self::$queries[self::$num_queries]['fetch'] = 1;
			}
			if (count($row) === 1) {
				return reset($row);
			}
		}
		return $row;
	}


	/**
	 * Execute an SQL statement and returns the number of affected rows
	 *
	 * @param string $query SQL query
	 * @param mixed ...$args placeholder replacements
	 * @return int|false
	 */
	public static function Exec($query, ...$args)
	{
		if (self::Query($query, ...$args) && self::$errno == 0) {
			return self::$affected_rows;
		}
		return false;
	}


	/**
	 * Inserts one or more rows in a table
	 *
	 * @param string $table
	 * @param array $rows
	 * @param bool $replace
	 * @return int|false
	 */
	public static function Insert($table, array $rows, $replace = false)
	{
		$table = trim($table, '{}');

		if (empty($rows))
			return false;

		if (!is_array(reset($rows)))
			$rows = array($rows);

		$head = array_keys(current($rows));
		sort($head);

		/* $fields = array_map('self::escapeField', $head); */
		$fields = array_map(function($field) { return self::escapeField($field); }, $head);

		$values = array();

		foreach($rows as $i => $row) {
			ksort($row); // Let's not be too strict, as long as all columns are there
			if (array_keys($row) !== $head) { // We need to make sure all rows contain the same columns
				throw new Exception("INSERT ERROR: Unmatching columns on row $i, make sure each row contains the same columns");
			}
			$inserts[] = '(' . rtrim(str_repeat('?,', count($row)), ',') . ')';
			$values = array_merge($values, array_values($row));
			// $inserts[] = '(' . implode(',', array_map('self::escapeValue', $row)) . ')';
		}

		$command = $replace ? 'REPLACE INTO ' : 'INSERT INTO ';
		$command .= '{' . $table . '} (' . implode(',', $fields) . ') VALUES ';
		$command .= implode(',', $inserts);

		return self::Exec($command, $values);
	}


	/**
	 * Updates one or more rows in a table
	 *
	 * @param string $table
	 * @param array $fields
	 * @param array|string $where
	 * @param mixed ...$param
	 * @return int|false
	 */
	public static function Update($table, array $fields, $where = ['id' => 0], ...$params)
	{
		$table = trim($table, '{}');
		$set = $cond = [];

		foreach($fields as $field => $value) {
			$set[] = self::escapeField($field) . ' = ?';
			$params[] = $value;
		}

		if (is_array($where)) {
			foreach($where as $field => $value) {
				$cond[] = self::escapeField($field) . ' = ?';
				$params[] = $value;
			}
			$where = implode(' AND ', $cond);
		}

		return self::Exec("UPDATE {" . $table . '} SET ' . implode(', ', $set) . " WHERE $where", ...$params);
	}


	/**
	 * Delete one or more rows in a table
	 *
	 * @param string $table
	 * @param array|string $where
	 * @param mixed ...$param
	 * @return int|false
	 */
	public static function Delete($table, $where = ['id' => 0], ...$params)
	{
		$table = trim($table, '{}');
		$cond = [];

		if (is_array($where)) {
			foreach($where as $field => $value) {
				$cond[] = self::escapeField($field) . ' = ?';
				$params[] = $value;
			}
			$where = implode(' AND ', $cond);
		}

		return self::Exec("DELETE FROM {" . $table . "} WHERE $where", ...$params);
	}
}
