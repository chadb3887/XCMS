<?php
/**
*
* @ This file is created by http://DeZender.Net
* @ deZender (PHP7 Decoder for ionCube Encoder)
*
* @ Version			:	5.0.1.0
* @ Author			:	DeZender
* @ Release on		:	22.04.2022
* @ Official site	:	http://DeZender.Net
*
*/

class Database
{
	public $result = null;
	public $last_query = null;
	public $dbh = null;
	public $connected = false;

	public function __construct($migrate = false)
	{
		$this->dbh = false;
		$this->db_connect($migrate);
	}

	public function close_mysql()
	{
		if ($this->connected) {
			$this->connected = false;
			$this->dbh = NULL;
		}

		return true;
	}

	public function __destruct()
	{
		$this->close_mysql();
	}

	public function ping()
	{
		try {
			$this->dbh->query('SELECT 1');
		}
		catch (Exception $e) {
			return false;
		}

		return true;
	}

	public function db_connect($migrate = false)
	{
		try {
			$this->dbh = Xcms\Functions::connect($migrate);

			if (!$this->dbh) {
				if ($migrate) {
					return false;
				}

				exit(json_encode(['error' => 'MySQL: Cannot connect to database! Please check credentials.']));
			}
		}
		catch (PDOException $e) {
			exit(json_encode(['error' => 'MySQL: ' . $e->getMessage()]));
		}

		$this->dbh->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
		$this->connected = true;
		return true;
	}

	public function db_explicit_connect($rHost, $rPort, $rDatabase, $rUsername, $rPassword)
	{
		try {
			$this->dbh = new PDO('mysql:host=' . $rHost . ';port=' . $rPort . ';dbname=' . $rDatabase, $rUsername, $rPassword);

			if (!$this->dbh) {
				return false;
			}
		}
		catch (PDOException $e) {
			return false;
		}

		$this->dbh->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
		$this->connected = true;
		return true;
	}

	public function debugString($stmt)
	{
		ob_start();
		$stmt->debugDumpParams();
		$r = ob_get_contents();
		ob_end_clean();
		return $r;
	}

	public function query($query, $buffered = false)
	{
		if ($this->dbh) {
			$numargs = func_num_args();
			$arg_list = func_get_args();
			$next_arg_list = [];

			for ($i = 1; $i < $numargs; $i++) {
				if (is_null($arg_list[$i]) || (strtolower($arg_list[$i]) == 'null')) {
					$next_arg_list[] = NULL;
					continue;
				}

				$next_arg_list[] = $arg_list[$i];
			}

			if ($buffered === true) {
				$this->dbh->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, false);
			}

			try {
				$this->result = $this->dbh->prepare($query);
				$this->result->execute($next_arg_list);
			}
			catch (Exception $e) {
				$actual_query = trim(explode("\n", explode('Sent SQL:', $this->debugString($this->result))[1])[0]);

				if (strlen($actual_query) == 0) {
					$actual_query = $query;
				}

				if (class_exists('XCMS')) {
					XCMS::saveLog('pdo', $e->getMessage(), $actual_query);
				}

				return false;
			}

			return true;
		}

		return false;
	}

	public function simple_query($query)
	{
		try {
			$this->result = $this->dbh->query($query);
		}
		catch (Exception $e) {
			if (class_exists('XCMS')) {
				XCMS::saveLog('pdo', $e->getMessage(), $query);
			}

			return false;
		}

		return true;
	}

	public function get_rows($use_id = false, $column_as_id = '', $unique_row = true, $sub_row_id = '')
	{
		if ($this->dbh && $this->result) {
			$rows = [];

			if (0 < $this->result->rowCount()) {
				foreach ($this->result->fetchAll(PDO::FETCH_ASSOC) as $row) {
					if ($use_id && array_key_exists($column_as_id, $row)) {
						if (!isset($rows[$row[$column_as_id]])) {
							$rows[$row[$column_as_id]] = [];
						}

						if (!$unique_row) {
							if (!empty($sub_row_id) && array_key_exists($sub_row_id, $row)) {
								$rows[$row[$column_as_id]][$row[$sub_row_id]] = $this->clean_row($row);
							}
							else {
								$rows[$row[$column_as_id]][] = $this->clean_row($row);
							}
						}
						else {
							$rows[$row[$column_as_id]] = $this->clean_row($row);
						}
					}
					else {
						$rows[] = $this->clean_row($row);
					}
				}
			}

			$this->result = NULL;
			return $rows;
		}

		return false;
	}

	public function get_row()
	{
		if ($this->dbh && $this->result) {
			$row = [];

			if (0 < $this->result->rowCount()) {
				$row = $this->result->fetch(PDO::FETCH_ASSOC);
			}

			$this->result = NULL;
			return $this->clean_row($row);
		}

		return false;
	}

	public function get_col()
	{
		if ($this->dbh && $this->result) {
			$row = false;

			if (0 < $this->result->rowCount()) {
				$row = $this->result->fetch();
				$row = $row[0];
			}

			$this->result = NULL;
			return $row;
		}

		return false;
	}

	public function escape($string)
	{
		if ($this->dbh) {
			return $this->dbh->quote($string);
		}

		return NULL;
	}

	public function num_fields()
	{
		if ($this->dbh && $this->result) {
			$mysqli_num_fields = $this->result->columnCount();
			return empty($mysqli_num_fields) ? 0 : $mysqli_num_fields;
		}

		return 0;
	}

	public function last_insert_id()
	{
		if ($this->dbh) {
			$mysql_insert_id = $this->dbh->lastInsertId();
			return empty($mysql_insert_id) ? 0 : $mysql_insert_id;
		}

		return NULL;
	}

	public function num_rows()
	{
		if ($this->dbh && $this->result) {
			$mysqli_num_rows = $this->result->rowCount();
			return empty($mysqli_num_rows) ? 0 : $mysqli_num_rows;
		}

		return 0;
	}

	static public function parseCleanValue($rValue)
	{
		if ($rValue == '') {
			return '';
		}

		$rValue = str_replace(["\r\n", "\n\r", "\r"], "\n", $rValue);
		$rValue = str_replace('<', '&lt;', str_replace('>', '&gt;', $rValue));
		$rValue = str_replace('<!--', '&#60;&#33;--', $rValue);
		$rValue = str_replace('-->', '--&#62;', $rValue);
		$rValue = str_ireplace('<script', '&#60;script', $rValue);
		$rValue = preg_replace('/&amp;#([0-9]+);/s', '&#\\1;', $rValue);
		$rValue = preg_replace('/&#(\\d+?)([^\\d;])/i', '&#\\1;\\2', $rValue);
		return trim($rValue);
	}

	public function clean_row($row)
	{
		foreach ($row as $key => $value) {
			if ($value) {
				$row[$key] = self::parseCleanValue($value);
			}
		}

		return $row;
	}
}

?>