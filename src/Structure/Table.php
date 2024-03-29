<?php

declare(strict_types=1);

namespace Attreid\OrmStructure\Structure;

use Attreid\OrmStructure\Structure;
use Attreid\OrmStructure\TableMapper;
use InvalidArgumentException;
use Nette\SmartObject;
use Nextras\Dbal\Connection;
use Nextras\Dbal\Drivers\Exception\QueryException;
use Nextras\Dbal\Result\Result;
use Nextras\Dbal\Result\Row;
use Nextras\Dbal\Utils\FileImporter;

/**
 * @property-read string $name
 * @property-read string $collate
 * @property-read bool $exists
 */
final class Table
{
	use SmartObject;

	/**
	 * Add migration function (\Nextras\Dbal\Result\Row, \Nextras\Dbal\Connection)
	 * @var callable[]
	 */
	public array $migration = [];

	/** @var Column[] */
	private array $columns = [];

	/** @var Column[] */
	private array $columnsToRename = [];

	/** @var Index[] */
	private array $keys = [];

	/** @var Constraint[] */
	private array $constraints = [];

	/** @var Table[] */
	private array $relationTables = [];

	public string $manyHasManyStorageNamePattern = '%s_x_%s';
	private string $database;
	private string $engine = 'InnoDB';
	private string $charset = 'utf8mb4';
	private string $collate = 'utf8mb4_czech_ci';
	private ?PrimaryKey $primaryKey = null;
	private ?int $autoIncrement = null;
	private ?string $addition = null;
	private ?string $defaultDataFile = null;
	private ?array $addOnCreate = null;

	public function __construct(
		private readonly string     $name,
		private readonly Connection $connection,
		private readonly Structure  $structure
	)
	{
		$this->database = $connection->getConfig()['database'];
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
		return (bool)$result;
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

	public function setDefaultDataFile(string $file): self
	{
		$this->defaultDataFile = $file;
		return $this;
	}

	public function createRelationTable(string|Table $tableName): self
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

		return $this->relationTables[] = $this->structure->createTable($name);
	}

	/** @internal */
	public function addColumnToRename(string $name, Column $column): self
	{
		$this->columnsToRename[$name] = $column;
		return $this;
	}

	/** @throws QueryException */
	public function check(): void
	{
		$this->connection->query('SET foreign_key_checks = 0');
		if (!$this->exists) {
			$this->create();
			$this->importData();

			foreach ($this->relationTables as $table) {
				$table->check();
			}
		} else {
			$this->changeColumns();
			$this->modifyColumnsAndKeys();
			$this->modifyTable();
		}
		$this->connection->query('SET foreign_key_checks = 1');
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
		foreach ($this->columnsToRename as $name => $column) {
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

		if (
			($this->primaryKey === null && !empty($primKey)) ||
			(!$this->primaryKey?->equals($primKey))
		) {
			$dropKeys[] = 'PRIMARY KEY';
		}
		if (!$this->primaryKey?->equals($primKey)) {
			$add[] = $this->primaryKey->getDefinition();
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
		return $this->connection->getDriver()->convertStringToSql($value);
	}

	public function add(string $addition): void
	{
		$this->addition = $addition;
	}

	public function addColumn(string $name): Column
	{
		$this->columns[$name] = $column = new Column($name, $this);
		return $column;
	}

	public function addPrimaryKey(string $name): Column
	{
		$column = $this->addColumn($name);
		$this->setPrimaryKey($name);
		return $column;
	}

	/**
	 * @param ?bool $onDelete false => RESTRICT, true => CASCADE, null => SET null
	 * @param ?bool $onUpdate false => RESTRICT, true => CASCADE, null => SET null
	 */
	public function addForeignKey(string $name, string|Table $mapperClass, ?bool $onDelete = true, ?bool $onUpdate = false, string $identifier = null): Column
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
		if ($identifier !== null) {
			$constraint->setIdentifier($identifier);
		}

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

	public function addOnCreate(array $data): void
	{
		$this->addOnCreate = $data;
	}

	private function getTableData(string|Table $table): self
	{
		if ($table instanceof Table) {
			return $table;
		} elseif (is_subclass_of($table, TableMapper::class)) {
			return $this->structure->getTable($table);
		} else {
			throw new InvalidArgumentException("Table '$table' not exists");
		}
	}

	/** @throws QueryException */
	private function getTableSchema(): ?Row
	{
		return $this->connection->query('
			SELECT 
				[tab.ENGINE],
				[col.COLLATION_NAME],
				[col.CHARACTER_SET_NAME]
			FROM [information_schema.TABLES] tab
			JOIN [information_schema.COLLATION_CHARACTER_SET_APPLICABILITY] col ON [tab.TABLE_COLLATION] = [col.COLLATION_NAME]
			WHERE 
				[tab.TABLE_SCHEMA] = %s AND
				[tab.TABLE_NAME] = %s',
			$this->database,
			$this->name
		)->fetch();
	}

	/** @throws QueryException */
	private function getConstraints(): ?Result
	{
		return $this->connection->query('
			SELECT 
				[col.CONSTRAINT_NAME],
				[col.COLUMN_NAME],
				[col.REFERENCED_TABLE_NAME],
				[col.REFERENCED_COLUMN_NAME],
				[ref.UPDATE_RULE],
				[ref.DELETE_RULE]
			FROM [information_schema.REFERENTIAL_CONSTRAINTS] ref
			JOIN [information_schema.KEY_COLUMN_USAGE] col ON [ref.CONSTRAINT_NAME] = [col.CONSTRAINT_NAME] AND [ref.CONSTRAINT_SCHEMA] = [col.CONSTRAINT_SCHEMA]
			WHERE 
				[ref.UNIQUE_CONSTRAINT_SCHEMA] = %s AND
				[ref.TABLE_NAME] = %s',
			$this->database,
			$this->name
		);
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
		return $row->num > 0;
	}

	public function __serialize(): array
	{
		return [
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
			'defaultDataFile' => $this->defaultDataFile
		];
	}

	public function __unserialize(array $data): void
	{
		$this->name = $data['name'];
		$this->engine = $data['engine'];
		$this->charset = $data['charset'];
		$this->collate = $data['collate'];
		$this->columns = unserialize($data['columns']);
		$this->primaryKey = unserialize($data['primaryKey']);
		$this->keys = unserialize($data['keys']);
		$this->constraints = unserialize($data['constraints']);
		$this->autoIncrement = $data['autoIncrement'];
		$this->addition = $data['addition'];
		$this->relationTables = unserialize($data['relationTables']);
		$this->defaultDataFile = $data['defaultDataFile'];
	}

	private function importData(): void
	{
		if ($this->defaultDataFile !== null) {
			FileImporter::executeFile($this->connection, $this->defaultDataFile);
		}
		if ($this->addOnCreate !== null) {
			if (is_array(reset($this->addOnCreate))) {
				$this->connection->query('INSERT INTO ' . $this->name . ' %values[]', $this->addOnCreate);
			} else {
				$this->connection->query('INSERT INTO ' . $this->name . ' %values', $this->addOnCreate);
			}
		}
	}
}