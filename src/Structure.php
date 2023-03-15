<?php

declare(strict_types=1);

namespace Attreid\OrmStructure;

use Attreid\OrmStructure\Interfaces\TableFactory;
use Attreid\OrmStructure\Interfaces\ViewFactory;
use Attreid\OrmStructure\Structure\Table;
use Attreid\OrmStructure\Structure\View;
use Nette\Caching\Cache;
use Nette\Caching\Storage;
use Nette\InvalidArgumentException;

final class Structure
{
	private Cache $cache;

	/** @var Mapper[] */
	private array $mappers = [];

	/** @var Table[] */
	private array $tables = [];

	public function __construct(
		private readonly bool         $autoManageDb,
		private readonly TableFactory $tableFactory,
		private readonly ViewFactory  $viewFactory,
		Storage                       $storage)
	{
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
			if ($mapper instanceof TableMapper) {
				$table = $this->tableFactory->create($mapper->getTableName());
				$mapper->createTable($table);
				$table->check();
				$this->tables[$mapper::class] = $table;
			} else {
				throw new InvalidArgumentException("Mapper '$mapperClass' is not instance of TableMapper");
			}
		}
		return $table;
	}

	private function getView(string $mapperClass): View
	{
		$view = $this->tables[$mapperClass] ?? null;
		if ($view === null) {
			$mapper = $this->mappers[$mapperClass];
			if ($mapper instanceof ViewMapper) {
				$view = $this->viewFactory->create($mapper->getTableName());
				$mapper->createDefinition($view->getQueryBuilder());
				$view->check();
				$this->tables[$mapper::class] = $view;
			} else {
				throw new InvalidArgumentException("Mapper '$mapperClass' is not instance of TableMapper");
			}
		}
		return $view;
	}

	public function check(): void
	{
		$key = 'loaded';
		if (!$this->cache->load($key)) {
			foreach ($this->mappers as $mapperClass => $mapper) {
				if ($mapper instanceof TableMapper) {
					$this->getTable($mapperClass);
				}
			}
			foreach ($this->mappers as $mapperClass => $mapper) {
				if ($mapper instanceof ViewMapper) {
					$this->getView($mapperClass);
				}
			}
			$this->cache->save($key, true);
		}
	}
}
