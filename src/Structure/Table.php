<?php

namespace NAttreid\Orm\Structure;

use NAttreid\Orm\Mapper;
use Nette\DI\Container;
use Nextras\Dbal\Connection;
use Nextras\Dbal\Result\Row;
use Tracy\Debugger;

/**
 * Tabulka
 *
 * @author Attreid <attreid@gmail.com>
 */
class Table
{

	/** @var string */
	private $name;

	/** @var Connection */
	private $connection;

	/** @var Container */
	private $container;

	/** @var ITableFactory */
	private $tableFactory;

	/** @var string */
	private $engine = 'InnoDB';

	/** @var string */
	private $charset = 'utf8';

	/** @var string */
	public $collate = 'utf8_czech_ci';

	/** @var Column */
	private $columns = [];

	/** @var string */
	private $primaryKey = [];

	/** @var string */
	private $keys = [];

	/** @var string */
	private $constraints = [];

	/** @var int */
	private $autoIncrement = null;

	/** @var string */
	private $addition = null;

	/** @var Table[] */
	private $relationTables = [];

	/** @var string */
	private $prefix;

	public function __construct($name, $prefix, Connection $connection, Container $container, ITableFactory $tableFactory)
	{
		$this->name = $name;
		$this->prefix = $prefix;
		$this->connection = $connection;
		$this->container = $container;
		$this->tableFactory = $tableFactory;
	}

	/**
	 * Nastavi engine (default=InnoDB)
	 * @param string $engine
	 * @return self
	 */
	public function setEngine($engine)
	{
		$this->engine = $engine;
		return $this;
	}

	/**
	 * Nastavi charset (default=utf8)
	 * @param string $charset
	 * @return self
	 */
	public function setCharset($charset)
	{
		$this->charset = $charset;
		return $this;
	}

	/**
	 * Nastavi engine (default=utf8_czech_ci)
	 * @param string $collate
	 * @return self
	 */
	public function setCollate($collate)
	{
		$this->collate = $collate;
		return $this;
	}

	/**
	 * Vytvori spojovou tabulku
	 * @param string $table
	 * @return self
	 */
	public function createRelationTable($table)
	{
		list($tableName) = $this->getTableData($table);

		$name = $this->name . '_x_' . $tableName;

		return $this->relationTables[] = $this->tableFactory->create($name, $this->prefix);
	}

	/**
	 * Proveri zda tabulka existuje a podle toho ji bud vytvori nebo upravi (pokud je treba)
	 * @return boolean true => pokud je vytvorena, false => pokud jiz existovala
	 */
	public function check()
	{
		$this->connection->query('SET foreign_key_checks = 0');
		$exist = $this->connection->query("SHOW TABLES LIKE %s", $this->name)->fetch();
		if (!$exist) {
			$this->create();
			$result = true;
		} else {
			$this->modify();
			$result = false;
		}
		foreach ($this->relationTables as $table) {
			$table->check();
		}
		$this->connection->query('SET foreign_key_checks = 1');
		return $result;
	}

	/**
	 * Vytvori tabulku
	 */
	private function create()
	{
		$query = "CREATE TABLE IF NOT EXISTS %table (\n"
			. implode(",\n", $this->columns) . ",\n"
			. (!empty($this->primaryKey) ? $this->preparePrimaryKey() . (empty($this->keys) ? '' : ",\n") : '')
			. implode(",\n", $this->keys) . (empty($this->constraints) ? '' : ",\n")
			. implode(",\n", $this->constraints)
			. "\n) ENGINE=$this->engine" . (empty($this->autoIncrement) ? '' : " AUTO_INCREMENT=$this->autoIncrement") . " DEFAULT CHARSET=$this->charset COLLATE=$this->collate"
			. (empty($this->addition) ? '' : "/*$this->addition*/");

		$this->connection->query($query, $this->name);
	}

	/**
	 * Upravi tabulku
	 */
	private function modify()
	{
		$drop = $modify = $add = $primKey = [];

		// sloupce
		$col = $this->columns;
		foreach ($this->connection->query('SHOW FULL COLUMNS FROM %table', $this->name) as $column) {
			$name = $column->Field;

			if (isset($col[$name])) {
				if ($this->prepareColumn($column) != (string)$col[$name]) {
					$modify[] = "$col[$name]";
				}
				unset($col[$name]);
			} else {
				$drop[] = "[$name]";
			}
		}
		if (!empty($col)) {
			$add[] = '(' . implode(",\n", $col) . ')';
		}

		// primarni klic
		foreach ($this->connection->query('SHOW INDEX FROM %table WHERE Key_name = %s', $this->name, 'PRIMARY') as $index) {
			$primKey[] = $index->Column_name;
		}
		if ($primKey != $this->primaryKey) {
			if (!empty($primKey)) {
				$drop[] = 'PRIMARY KEY';
			}
			if (!empty($this->primaryKey)) {
				$add[] = $this->preparePrimaryKey();
			}
		}

		// klice
		$keys = $this->keys;
		foreach ($this->connection->query('SHOW INDEX FROM %table WHERE Seq_in_index = %i AND Key_name != %s', $this->name, 1, 'PRIMARY') as $key) {
			$name = $key->Key_name;

			if (isset($keys[$name])) {
				unset($keys[$name]);
			} else {
				$drop[] = "INDEX [$name]";
			}
		}
		if (!empty($keys)) {
			$add = array_merge($add, $keys);
		}

		// foreign key
		$constraints = $this->constraints;
		$foreignKeys = $this->connection->getPlatform()->getForeignKeys($this->name);
		foreach ($foreignKeys as $key) {
			$name = $key['name'];
			if (isset($constraints[$name])) {
				unset($constraints[$name]);
			} else {
				$drop[] = "FOREIGN KEY [$name]";
			}
		}
		if (!empty($constraints)) {
			$add = array_merge($add, $constraints);
		}

		// modify
		if (!empty($modify)) {
			$this->connection->query("ALTER TABLE %table MODIFY " . implode(', MODIFY ', $modify), $this->name);
		}

		// drop
		if (!empty($drop)) {
			$this->connection->query("ALTER TABLE %table DROP " . implode(', DROP ', $drop), $this->name);
		}

		// add
		if (!empty($add)) {
			$this->connection->query("ALTER TABLE %table ADD " . implode(', ADD ', $add), $this->name);
		}
	}

	/**
	 * Vrati primarni klic
	 * @return array
	 */
	public function getPrimaryKey()
	{
		return $this->primaryKey;
	}

	/**
	 * Pridavek za dotaz (partition atd)
	 * @param string $addition
	 */
	public function add($addition)
	{
		$this->addition = $addition;
	}

	/**
	 * Prida sloupec
	 * @param string $name
	 * @return Column
	 */
	public function addColumn($name)
	{
		return $this->columns[$name] = new Column($this, $name);
	}

	/**
	 * Prida primarni klic
	 * @param string $name
	 * @return Column
	 */
	public function addPrimaryKey($name)
	{
		$column = $this->addColumn($name);
		$this->setPrimaryKey($name);
		return $column;
	}

	/**
	 * Nastavi cizi klic
	 * @param string $name
	 * @param string|Table $mapperClass klic uz musi byt v tabulce nastaven
	 * @param mixed $onDelete false => NO ACTION, true => CASCADE, null => SET null
	 * @param mixed $onUpdate false => NO ACTION, true => CASCADE, null => SET null
	 * @return Column
	 */
	public function addForeignKey($name, $mapperClass, $onDelete = true, $onUpdate = false)
	{
		$column = $this->addColumn($name)
			->int();

		if ($onDelete === null) {
			$column->setDefault(null);
		} else {
			$column->setDefault();
		}

		$this->setKey($name);

		list($tableName, $tableKey) = $this->getTableData($mapperClass);

		$foreignName = 'fk_' . $this->name . '_' . $name . '_' . $tableName . '_' . $tableKey;

		$this->constraints[$foreignName] = "CONSTRAINT [$foreignName] FOREIGN KEY ([$name]) REFERENCES [$tableName] ([$tableKey]) ON DELETE {$this->prepareOnChange($onDelete)} ON UPDATE {$this->prepareOnChange($onUpdate)}";
		return $column;
	}

	/**
	 * Odebere sloupec
	 * @param string $name
	 */
	public function removeColumn($name)
	{
		unset($this->columns[$name]);
	}

	/**
	 * Nastavi hodnotu sloupce na unikatni
	 * @param  mixed $key [klic, ...]
	 * @return self
	 */
	public function setUnique(...$key)
	{
		if (count($key) > 0) {
			$this->keys[implode('_', $key)] = 'UNIQUE ' . $this->prepareKey($key);
		}
		return $this;
	}

	/**
	 * Nastavi klic
	 * @param  mixed $key [klic, ...]
	 * @return self
	 */
	public function setKey(...$key)
	{
		if (count($key) > 0) {
			$this->keys[implode('_', $key)] = $this->prepareKey($key);
		}
		return $this;
	}

	/**
	 * Nastavi primarni klic
	 * @param  mixed $key [klic, ...]
	 * @return self
	 */
	public function setPrimaryKey(...$key)
	{
		if (count($key) > 0) {
			$this->primaryKey = $key;
		}
		return $this;
	}

	/**
	 * Nastavi auto increment
	 * @param int $first
	 * @return self
	 */
	public function setAutoIncrement($first)
	{
		$this->autoIncrement = $first;
		return $this;
	}

	/**
	 * Vrati nazev tabulky a jeji klic
	 * @param string $table
	 * @return array[name, primaryKey]
	 * @throws \InvalidArgumentException
	 */
	private function getTableData($table)
	{
		if ($table instanceof Table) {
			return [
				$table->name,
				$table->getPrimaryKey()[0]
			];
		} elseif (is_subclass_of($table, Mapper::class)) {
			/* @var $mapper Mapper */
			$mapper = $this->container->getByType($table);
			$name = $mapper->getTableName();
			return [
				$name,
				$this->connection->query('SHOW INDEX FROM %table WHERE Key_name = %s ', $name, 'PRIMARY')->fetch()->Column_name
			];
		} else {
			throw new \InvalidArgumentException;
		}
	}

	/**
	 * Pripravi klice
	 * @param array $args
	 * @return string
	 */
	private function prepareKey($args)
	{
		$name = '';
		$key = '';
		foreach ($args as $arg) {
			if (!empty($key)) {
				$name .= '_';
				$key .= ', ';
			}
			$name .= $arg;
			$key .= "[$arg]";
		}

		return "KEY [$name] ($key)";
	}

	/**
	 * Vrati hodnotu pro zmenu
	 * @param mixed $value
	 * @return string
	 */
	private function prepareOnChange($value)
	{
		if ($value === false) {
			return 'NO ACTION';
		} elseif ($value === null) {
			return 'SET null';
		} else {
			return 'CASCADE';
		}
	}

	/**
	 * Pripravi sloupec pro porovnani
	 * @param Row $row
	 * @return string
	 */
	private function prepareColumn(Row $row)
	{
		$nullable = $row->Null === 'YES';

		if ($row->Default === null && !$nullable) {
			$default = ' NOT null';
		} elseif ($row->Default === null && $nullable) {
			$default = ' DEFAULT null';
		} else {
			$default = ($nullable ? '' : ' NOT null') . " DEFAULT '{$row->Default}'";
		}

		if (!empty($row->Collation)) {
			$collate = ' COLLATE ' . $row->Collation;
		} else {
			$collate = '';
		}

		if ($row->Extra === 'auto_increment') {
			$autoIncrement = ' AUTO_INCREMENT';
		} else {
			$autoIncrement = '';
		}

		return "`{$row->Field}` "
		. $row->Type
		. $collate
		. $default
		. $autoIncrement;
	}

	private function preparePrimaryKey()
	{
		$primaryKey = '';
		foreach ($this->primaryKey as $key) {
			if (!empty($primaryKey)) {
				$primaryKey .= ', ';
			}
			$primaryKey .= "[$key]";
		}

		return "PRIMARY KEY($primaryKey)";
	}

}

interface ITableFactory
{

	/**
	 * @param $name
	 * @param $prefix
	 * @return Table
	 */
	public function create($name, $prefix);
}
