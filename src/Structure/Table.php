<?php

namespace NAttreid\Orm\Structure;

use InvalidArgumentException;
use NAttreid\Orm\Mapper;
use Nette\DI\Container;
use Nette\SmartObject;
use Nextras\Dbal\Connection;
use Nextras\Dbal\Result\Result;
use Nextras\Dbal\Utils\FileImporter;
use Serializable;
use stdClass;

/**
 * Tabulka
 *
 * @property-read string $name
 * @property-read Connection $connection
 * @property-read Column[] $columns
 * @property-read PrimaryKey $primaryKey
 *
 * @author Attreid <attreid@gmail.com>
 */
class Table implements Serializable
{
	use SmartObject;

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

	/** @var Column[] */
	private $columns = [];

	/** @var PrimaryKey */
	private $primaryKey;

	/** @var Index[] */
	private $keys = [];

	/** @var Constrait[] */
	private $constraints = [];

	/** @var int */
	private $autoIncrement = null;

	/** @var string */
	private $addition = null;

	/** @var Table[] */
	private $relationTables = [];

	/** @var string */
	private $prefix;

	/** @var string */
	private $defaultDataFile;

	public function __construct($name, $prefix, Connection $connection, Container $container = null, ITableFactory $tableFactory = null)
	{
		$this->name = $name;
		$this->prefix = $prefix;
		$this->connection = $connection;
		$this->container = $container;
		$this->tableFactory = $tableFactory;
	}

	/**
	 * @return string
	 */
	protected function getName()
	{
		return $this->name;
	}

	/**
	 * @return Connection
	 */
	protected function getConnection()
	{
		return $this->connection;
	}

	/**
	 * @return Column[]
	 */
	protected function getColumns()
	{
		return $this->columns;
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
	 * Nastavi soubor pro nahrani dat pri vytvareni tabulky
	 * @param string $file
	 */
	public function setDefaultDataFile($file)
	{
		$this->defaultDataFile = $file;
	}

	/**
	 * Vytvori spojovou tabulku
	 * @param string $tableName
	 * @return self
	 */
	public function createRelationTable($tableName)
	{
		$table = $this->getTableData($tableName);

		$name = $this->name . '_x_' . $table->name;

		return $this->relationTables[] = $this->tableFactory->create($name, $this->prefix);
	}

	/**
	 * Proveri zda tabulka existuje a podle toho ji bud vytvori nebo upravi (pokud je treba)
	 * @return boolean pokud je vytvorena vrati true
	 */
	public function check()
	{
		$isNew = false;
		$this->connection->query('SET foreign_key_checks = 0');
		$exist = $this->connection->query("SHOW TABLES LIKE %s", $this->name)->fetch();
		if (!$exist) {
			$this->create();
			$isNew = true;
			if ($this->defaultDataFile !== null) {
				FileImporter::executeFile($this->connection, $this->defaultDataFile);
			}
		} else {
			$this->modify();
		}
		foreach ($this->relationTables as $table) {
			$table->check();
		}
		$this->connection->query('SET foreign_key_checks = 1');
		return $isNew;
	}

	/**
	 * Vytvori tabulku
	 */
	private function create()
	{
		$query = "CREATE TABLE IF NOT EXISTS %table (\n"
			. implode(",\n", $this->columns) . ",\n"
			. ($this->primaryKey !== null ? $this->primaryKey . (empty($this->keys) ? '' : ",\n") : '')
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
				if (!$col[$name]->equals($column)) {
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

		if (!$this->primaryKey->equals($primKey)) {
			if (!empty($primKey)) {
				$drop[] = 'PRIMARY KEY';
			}
			if (!empty($this->primaryKey)) {
				$add[] = $this->primaryKey;
			}
		}

		// klice
		$keys = $this->keys;
		foreach ($this->getKeys() as $key) {
			$name = $key->name;

			if (isset($keys[$name])) {
				if ($keys[$name]->equals($key)) {
					unset($keys[$name]);
					continue;
				}
				$drop[] = "INDEX [$name]";
			}
		}
		if (!empty($keys)) {
			$add = array_merge($add, $keys);
		}

		// foreign key
		$constraints = $this->constraints;
		foreach ($this->getConstraits() as $constrait) {
			$name = $constrait->CONSTRAINT_NAME;
			if (isset($constraints[$name])) {
				if ($constraints[$name]->equals($constrait)) {
					unset($constraints[$name]);
					continue;
				}
			}
			$drop[] = "FOREIGN KEY [$name]";
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
	 * @param string $value
	 * @return string
	 */
	public function escapeString($value)
	{
		$this->connection->reconnect();
		return $this->connection->getDriver()->convertStringToSql((string)$value);
	}

	/**
	 * Vrati primarni klic
	 * @return PrimaryKey
	 */
	protected function getPrimaryKey()
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
		$table = $this->getTableData($mapperClass);

		$constrait = new Constrait($name, $this, $table, $onDelete, $onUpdate);

		$this->constraints[$constrait->name] = $constrait;
		return $constrait->column;
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
	 * Nastavi fulltext
	 * @param string ...$key
	 * @return $this
	 */
	public function addFulltext(...$name)
	{
		$key = new Index(...$name);
		$key->setFulltext();
		$this->keys[$key->name] = $key;
		return $this;
	}

	/**
	 * Nastavi hodnotu sloupce na unikatni
	 * @param  string ...$key
	 * @return self
	 */
	public function addUnique(...$name)
	{
		$key = new Index(...$name);
		$key->setUnique();
		$this->keys[$key->name] = $key;
		return $this;
	}

	/**
	 * Nastavi klic
	 * @param  string ...$name
	 * @return self
	 */
	public function addKey(...$name)
	{
		$key = new Index(...$name);
		$this->keys[$key->name] = $key;
		return $this;
	}

	/**
	 * Nastavi primarni klic
	 * @param  string ...$key
	 * @return self
	 */
	public function setPrimaryKey(...$key)
	{
		$this->primaryKey = new PrimaryKey($this, ...$key);
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
	 * @param string|Table $table
	 * @return Table
	 * @throws InvalidArgumentException
	 */
	private function getTableData($table)
	{
		if ($table instanceof Table) {
			return $table;
		} elseif (is_subclass_of($table, Mapper::class)) {
			/* @var $mapper Mapper */
			$mapper = $this->container->getByType($table);
			return $mapper->getStructure();
		} else {
			throw new InvalidArgumentException;
		}
	}

	/**
	 * Pripravy cizi klice
	 * @return Result|null
	 */
	private function getConstraits()
	{
		return $this->connection->query("
			SELECT 
				col.CONSTRAINT_NAME,
				col.COLUMN_NAME,
				col.REFERENCED_TABLE_NAME,
				col.REFERENCED_COLUMN_NAME,
				ref.UPDATE_RULE,
				ref.DELETE_RULE
			FROM information_schema.REFERENTIAL_CONSTRAINTS ref
			JOIN information_schema.KEY_COLUMN_USAGE col ON ref.CONSTRAINT_NAME = col.CONSTRAINT_NAME
			WHERE ref.TABLE_NAME = %s",
			$this->name);
	}

	/**
	 * Pripravy klice
	 * @return array
	 */
	private function getKeys()
	{
		$result = [];
		$rows = $this->connection->query("
			SELECT 
				INDEX_NAME,
				COLUMN_NAME,
				INDEX_TYPE,
				NON_UNIQUE,
				SEQ_IN_INDEX
			FROM information_schema.STATISTICS 
			WHERE TABLE_NAME = %s AND INDEX_NAME != %s",
			$this->name, 'PRIMARY');
		foreach ($rows as $row) {
			$name = $row->INDEX_NAME;
			if (isset($result[$name])) {
				$obj = $result[$name];
			} else {
				$obj = new stdClass;
			}
			$obj->name = $name;
			$obj->columns[$row->SEQ_IN_INDEX] = $row->COLUMN_NAME;
			$obj->type = $row->INDEX_TYPE;
			$obj->unique = !$row->NON_UNIQUE;

			$result[$name] = $obj;
		}
		return $result;
	}

	public function serialize()
	{
		$unserialized = [
			'name' => $this->name,
			'engine' => $this->engine,
			'charset' => $this->charset,
			'collate' => $this->collate,
			'columns' => serialize($this->columns),
			'primaryKey' => serialize($this->primaryKey),
			'keys' => serialize($this->keys),
			'constraints' => serialize($this->constraints),
			'autoIncrement' => $this->autoIncrement,
			'addition' => $this->addition,
			'relationTables' => serialize($this->relationTables),
			'prefix' => $this->prefix,
			'defaultDataFile' => $this->defaultDataFile
		];
		return serialize($unserialized);
	}

	public function unserialize($serialized)
	{
		$unserialized = unserialize($serialized);

		$this->name = $unserialized['name'];
		$this->engine = $unserialized['engine'];
		$this->charset = $unserialized['charset'];
		$this->collate = $unserialized['collate'];
		$this->columns = unserialize($unserialized['columns']);
		$this->primaryKey = unserialize($unserialized['primaryKey']);
		$this->keys = unserialize($unserialized['keys']);
		$this->constraints = unserialize($unserialized['constraints']);
		$this->autoIncrement = $unserialized['autoIncrement'];
		$this->addition = $unserialized['addition'];
		$this->relationTables = unserialize($unserialized['relationTables']);
		$this->prefix = $unserialized['prefix'];
		$this->defaultDataFile = $unserialized['defaultDataFile'];
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
