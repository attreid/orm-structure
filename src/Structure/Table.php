<?php

declare(strict_types=1);

namespace NAttreid\Orm\Structure;

use InvalidArgumentException;
use NAttreid\Orm\Mapper;
use Nette\DI\Container;
use Nette\SmartObject;
use Nextras\Dbal\Connection;
use Nextras\Dbal\QueryException;
use Nextras\Dbal\Result\Result;
use Nextras\Dbal\Result\Row;
use Nextras\Dbal\Utils\FileImporter;
use Serializable;

/**
 * @property-read string $name
 * @property-read string $collate
 * @property-read bool $exists
 */
final class Table implements Serializable
{
	use SmartObject;

	/**
	 * Add migration function function (\Nextras\Dbal\Result\Row, \Nextras\Dbal\Connection)
	 * @var callable[]
	 */
	public array $migration = [];

	/** @var Column[] */
	private array $columns = [];

	/** @var Column[] */
	private array $oldColumns = [];

	/** @var Index[] */
	private array $keys = [];

	/** @var Constraint[] */
	private array $constraints = [];

	/** @var Table[] */
	private array $relationTables = [];

	public string $manyHasManyStorageNamePattern = '%s_x_%s';
	private string $database;
	private string $name;
	private Connection $connection;
	private Container $container;
	private ITableFactory $tableFactory;
	private string $engine = 'InnoDB';
	private string $charset = 'utf8';
	private string $collate = 'utf8_czech_ci';
	private PrimaryKey $primaryKey;
	private ?int $autoIncrement = null;
	private ?string $addition = null;
	private string $prefix;
	private ?string $defaultDataFile = null;

	public function __construct(string $name, string $prefix, Connection $connection, Container $container, ITableFactory $tableFactory)
	{
		$this->database = $connection->getConfig()['database'];
		$this->name = $name;
		$this->prefix = $prefix;
		$this->connection = $connection;
		$this->container = $container;
		$this->tableFactory = $tableFactory;
	}

	protected function getName(): string
	{
		return $this->name;
	}

	protected function getCollate(): string
	{
		return $this->collate;
	}

	/** @throws QueryException */
	protected function isExists(): bool
	{
		return $this->exists($this->name);
	}

	/** @throws QueryException */
	public function exists(string $table): bool
	{
		$result = $this->connection->query("SHOW TABLES LIKE %s", $table)->fetch();
		return $result ? true : false;
	}

	public function setEngine(string $engine): self
	{
		$this->engine = $engine;
		return $this;
	}

	public function setCharset(string $charset): self
	{
		$this->charset = $charset;
		return $this;
	}

	public function setCollate(string $collate): self
	{
		$this->collate = $collate;
		return $this;
	}

	public function setDefaultDataFile(string $file)
	{
		$this->defaultDataFile = $file;
	}

	public function createRelationTable($tableName): self
	{
		try {
			$relationName = $this->getTableData($tableName)->name;
		} catch (InvalidArgumentException $ex) {
			$relationName = $tableName;
		}

		$name = sprintf(
			$this->manyHasManyStorageNamePattern,
			$this->name,
			preg_replace('#^(.*\.)?(.*)$#', '$2', $relationName)
		);

		return $this->relationTables[] = $this->tableFactory->create($name, $this->prefix);
	}

	/** @internal */
	public function addColumnToRename(string $name, Column $column): self
	{
		$this->oldColumns[$name] = $column;
		return $this;
	}

	/** @throws QueryException */
	public function check(): bool
	{
		$isNew = false;
		$this->connection->query('SET foreign_key_checks = 0');
		if (!$this->exists) {
			$this->create();
			$isNew = true;
			if ($this->defaultDataFile !== null) {
				FileImporter::executeFile($this->connection, $this->defaultDataFile);
			}

			foreach ($this->relationTables as $table) {
				$table->check();
			}
		} else {
			$this->changeColumns();
			$this->modifyColumnsAndKeys();
			$this->modifyTable();
		}
		$this->connection->query('SET foreign_key_checks = 1');
		return $isNew;
	}

	/** @throws QueryException */
	private function create(): void
	{
		$query = "CREATE TABLE IF NOT EXISTS %table (\n"
			. implode(",\n", array_map(function ($column) {
				return $column->getDefinition();
			}, $this->columns)) . ",\n"
			. ($this->primaryKey !== null ? $this->primaryKey->getDefinition() . (empty($this->keys) ? '' : ",\n") : '')
			. implode(",\n", array_map(function ($key) {
				return $key->getDefinition();
			}, $this->keys)) . (empty($this->constraints) ? '' : ",\n")
			. implode(",\n", array_map(function ($constraint) {
				return $constraint->getDefinition();
			}, $this->constraints))
			. "\n) ENGINE=$this->engine" . (empty($this->autoIncrement) ? '' : " AUTO_INCREMENT=$this->autoIncrement") . " DEFAULT CHARSET=$this->charset COLLATE=$this->collate"
			. (empty($this->addition) ? '' : "/*$this->addition*/");

		$this->connection->query($query, $this->name);
	}

	/** @throws QueryException */
	private function modifyTable(): void
	{
		$table = $this->getTableSchema();
		if ($table->ENGINE !== $this->engine) {
			$this->connection->query("ALTER TABLE %table ENGINE " . $this->engine, $this->name);
		}
		if ($table->CHARACTER_SET_NAME !== $this->charset) {
			$this->connection->query("ALTER TABLE %table DEFAULT CHARSET " . $this->charset, $this->name);
		}
		if ($table->COLLATION_NAME !== $this->collate) {
			$this->connection->query("ALTER TABLE %table COLLATE " . $this->collate, $this->name);
		}
	}

	/** @throws QueryException */
	private function changeColumns(): void
	{
		$change = [];
		foreach ($this->oldColumns as $name => $column) {
			if ($this->columnExists($name)) {
				$change[] = "[$name] {$column->getDefinition()}";
			}
		}
		if (!empty($change)) {
			$this->connection->query("ALTER TABLE %table CHANGE " . implode(', CHANGE ', $change), $this->name);
		}
	}

	/** @throws QueryException */
	private function modifyColumnsAndKeys(): void
	{
		$dropKeys = $dropColumns = $modify = $add = $primKey = [];

		// columns
		$columns = $this->columns;
		foreach ($this->connection->query('SHOW FULL COLUMNS FROM %table', $this->name) as $column) {
			$name = $column->Field;

			if (isset($columns[$name])) {
				if (!$columns[$name]->equals($column)) {
					$modify[] = $columns[$name]->getDefinition();
				}
				unset($columns[$name]);
			} else {
				$dropColumns[] = "[$name]";
			}
		}
		if (!empty($columns)) {
			$add[] = '(' . implode(",\n", array_map(function ($column) {
					return $column->getDefinition();
				}, $columns)) . ')';
		}

		// primary keys
		foreach ($this->connection->query('SHOW INDEX FROM %table WHERE Key_name = %s', $this->name, 'PRIMARY') as $index) {
			$primKey[] = $index->Column_name;
		}

		if (!$this->primaryKey->equals($primKey)) {
			if (!empty($primKey)) {
				$dropKeys[] = 'PRIMARY KEY';
			}
			if (!empty($this->primaryKey)) {
				$add[] = $this->primaryKey->getDefinition();
			}
		}

		// keys
		$keys = $this->keys;
		foreach ($this->getKeys() as $key) {
			$name = $key->name;

			if (isset($keys[$name])) {
				if ($keys[$name]->equals($key)) {
					unset($keys[$name]);
					continue;
				}
			}
			$dropKeys[] = "INDEX [$name]";
		}
		if (!empty($keys)) {
			$add = array_merge($add, array_map(function ($key) {
				return $key->getDefinition();
			}, $keys));
		}

		// foreign key
		$constraints = $this->constraints;
		foreach ($this->getConstraints() as $constraint) {
			$name = $constraint->CONSTRAINT_NAME;
			if (isset($constraints[$name])) {
				if ($constraints[$name]->equals($constraint)) {
					unset($constraints[$name]);
					continue;
				}
			}
			$dropKeys[] = "FOREIGN KEY [$name]";
		}
		if (!empty($constraints)) {
			$add = array_merge($add, array_map(function ($constraint) {
				return $constraint->getDefinition();
			}, $constraints));
		}

		// drop key
		if (!empty($dropKeys)) {
			$this->connection->query("ALTER TABLE %table DROP " . implode(', DROP ', $dropKeys), $this->name);
		}

		// modify
		if (!empty($modify)) {
			$this->connection->query("ALTER TABLE %table MODIFY " . implode(', MODIFY ', $modify), $this->name);
		}

		// add
		if (!empty($add)) {
			$this->connection->query('SET foreign_key_checks = 1');
			$this->connection->query("ALTER TABLE %table ADD " . implode(', ADD ', $add), $this->name);
			$this->connection->query('SET foreign_key_checks = 0');
		}

		// check relation tables
		foreach ($this->relationTables as $table) {
			$table->check();
		}

		// migration
		foreach ($this->migration as $func) {
			$result = $this->connection->query('SELECT * FROM %table', $this->name);
			foreach ($result as $row) {
				$func($row, $this->connection);
			}
		}

		// drop columns
		if (!empty($dropColumns)) {
			$this->connection->query("ALTER TABLE %table DROP " . implode(', DROP ', $dropColumns), $this->name);
		}
	}

	public function escapeString(string $value): string
	{
		$this->connection->reconnect();
		return $this->connection->getDriver()->convertStringToSql((string)$value);
	}

	public function add(string $addition): void
	{
		$this->addition = $addition;
	}

	public function addColumn(string $name): Column
	{
		$this->columns[$name] = $column = new Column($name);
		$column->setTable($this);
		return $column;
	}

	public function addPrimaryKey(string $name): Column
	{
		$column = $this->addColumn($name);
		$this->setPrimaryKey($name);
		return $column;
	}

	/**
	 * @param string|Table $mapperClass
	 * @param ?bool $onDelete false => RESTRICT, true => CASCADE, null => SET null
	 * @param ?bool $onUpdate false => RESTRICT, true => CASCADE, null => SET null
	 */
	public function addForeignKey(string $name, $mapperClass, ?bool $onDelete = true, ?bool $onUpdate = false): Column
	{
		$referenceTable = $this->getTableData($mapperClass);

		$column = $this->addColumn($name)
			->setType($referenceTable->columns[$referenceTable->primaryKey->name]);

		if ($onDelete === null) {
			$column->setDefault(null);
		} else {
			$column->setDefault();
		}

		$this->addKey($name);

		$constraint = new Constraint($name, $this->name, $referenceTable->name, $referenceTable->primaryKey->name, $onDelete, $onUpdate);

		$this->constraints[$constraint->name] = $constraint;
		return $column;
	}

	public function removeColumn(string $name): void
	{
		unset($this->columns[$name]);
	}

	public function addFulltext(string ...$name): self
	{
		$key = new Index(...$name);
		$key->setFulltext();
		$this->keys[$key->name] = $key;
		return $this;
	}

	public function addUnique(string ...$name): self
	{
		$key = new Index(...$name);
		$key->setUnique();
		$this->keys[$key->name] = $key;
		return $this;
	}

	public function addKey(string ...$name): self
	{
		$key = new Index(...$name);
		$this->keys[$key->name] = $key;
		return $this;
	}

	public function setPrimaryKey(string ...$key): self
	{
		$column = $this->columns[$key[0]] ?? null;
		if ($column === null) {
			throw new InvalidArgumentException("Column '$key[0]'is not defined.");
		}
		$this->primaryKey = new PrimaryKey(...$key);
		return $this;
	}

	public function setAutoIncrement(int $first): self
	{
		$this->autoIncrement = $first;
		return $this;
	}

	/**
	 * @param string|Table $table
	 * @throws InvalidArgumentException
	 */
	private function getTableData($table): self
	{
		if ($table instanceof Table) {
			return $table;
		} elseif (is_subclass_of($table, Mapper::class)) {
			/* @var $mapper Mapper */
			$mapper = $this->container->getByType($table);
			return $mapper->getStructure();
		} else {
			throw new InvalidArgumentException("Table '$table' not exists");
		}
	}

	/** @throws QueryException */
	private function getTableSchema(): ?Row
	{
		return $this->connection->query("
			SELECT 
				[tab.ENGINE],
				[col.COLLATION_NAME],
				[col.CHARACTER_SET_NAME]
			FROM [information_schema.TABLES] tab
			JOIN [information_schema.COLLATION_CHARACTER_SET_APPLICABILITY] col ON [tab.TABLE_COLLATION] = [col.COLLATION_NAME]
			WHERE [tab.TABLE_SCHEMA] = %s
				AND [tab.TABLE_NAME] = %s",
			$this->database,
			$this->name)->fetch();
	}

	/** @throws QueryException */
	private function getConstraints(): ?Result
	{
		return $this->connection->query("
			SELECT 
				[col.CONSTRAINT_NAME],
				[col.COLUMN_NAME],
				[col.REFERENCED_TABLE_NAME],
				[col.REFERENCED_COLUMN_NAME],
				[ref.UPDATE_RULE],
				[ref.DELETE_RULE]
			FROM [information_schema.REFERENTIAL_CONSTRAINTS] ref
			JOIN [information_schema.KEY_COLUMN_USAGE] col ON [ref.CONSTRAINT_NAME] = [col.CONSTRAINT_NAME] AND [ref.CONSTRAINT_SCHEMA] = [col.CONSTRAINT_SCHEMA]
			WHERE [ref.UNIQUE_CONSTRAINT_SCHEMA] = %s
				AND [ref.TABLE_NAME] = %s",
			$this->database,
			$this->name);
	}

	/** @throws QueryException */
	private function getKeys(): array
	{
		/* @var $result Key[] */
		$result = [];
		$rows = $this->connection->query("
			SELECT 
				[INDEX_NAME],
				[COLUMN_NAME],
				[INDEX_TYPE],
				[NON_UNIQUE],
				[SEQ_IN_INDEX]
			FROM [information_schema.STATISTICS] 
			WHERE [TABLE_SCHEMA] = %s
				AND [TABLE_NAME] = %s 
				AND [INDEX_NAME] != %s",
			$this->database,
			$this->name,
			'PRIMARY');
		foreach ($rows as $row) {
			$name = $row->INDEX_NAME;
			if (isset($result[$name])) {
				$obj = $result[$name];
			} else {
				$obj = new Key;
			}
			$obj->name = $name;
			$obj->addColumn($row->SEQ_IN_INDEX, $row->COLUMN_NAME);
			$obj->type = $row->INDEX_TYPE;
			$obj->unique = !$row->NON_UNIQUE;

			$result[$name] = $obj;
		}
		return $result;
	}

	private function columnExists(string $name): bool
	{
		$row = $this->connection->query("
			SELECT COUNT([COLUMN_NAME]) num 
				FROM [information_schema.COLUMNS] 
				WHERE [TABLE_SCHEMA] = %s
				AND [TABLE_NAME] = %s 
				AND [COLUMN_NAME] = %s",
			$this->database,
			$this->name,
			$name)->fetch();
		return $row->num > 0 ? true : false;
	}

	public function serialize(): string
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

	public function unserialize($serialized): void
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
	public function create(string $name, string $prefix): Table;
}