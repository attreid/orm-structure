<?php

declare(strict_types=1);

namespace Attreid\OrmStructure;

use Attreid\OrmStructure\Interfaces\TableFactory;
use Attreid\OrmStructure\Structure\Table;
use Nette\Caching\Cache;
use Nette\Caching\Storage;
use Nette\SmartObject;

final class Structure
{
	use SmartObject;

	private bool $autoManageDb;
	private TableFactory $tableFactory;
	private Cache $cache;

	/** @var Mapper[] */
	private array $mappers = [];

	/** @var Table[] */
	private array $tables = [];

	public function __construct(bool $autoManageDb, TableFactory $tableFactory, Storage $storage)
	{
		$this->autoManageDb = $autoManageDb;
		$this->tableFactory = $tableFactory;
		$this->cache = new Cache($storage, 'ormStructure');
	}

	public function addMapper(Mapper $mapper): void
	{
		$this->mappers[$mapper::class] = $mapper;
	}

	public function createTable(string $name): Table
	{
		return $this->tableFactory->create($name);
	}

	public function run(): void
	{
		if ($this->autoManageDb) {
			$this->check();
		}
	}

	public function getTable(string $mapperClass): Table
	{
		$table = $this->tables[$mapperClass] ?? null;
		if ($table === null) {
			$mapper = $this->mappers[$mapperClass];
			$table = $this->tableFactory->create($mapper->getTableName());
			$mapper->createTable($table);
			$table->check();
			$this->tables[$mapper::class] = $table;
		}
		return $table;
	}

	public function check(): void
	{
		$key = 'loaded';
		if (!$this->cache->load($key)) {
			foreach ($this->mappers as $mapperClass => $mapper) {
				$this->getTable($mapperClass);
			}
			$this->cache->save($key, true);
		}
	}
}
