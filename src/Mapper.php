<?php

declare(strict_types=1);

namespace Attreid\OrmStructure;

use Attreid\OrmStructure\Structure\Table;
use JetBrains\PhpStorm\Pure;
use Nette\Caching\Cache;
use Nette\Utils\Arrays;
use Nette\Utils\Strings;
use Nextras\Dbal\Connection;
use Nextras\Dbal\Drivers\Exception\QueryException;
use Nextras\Dbal\IConnection;
use Nextras\Dbal\QueryBuilder\QueryBuilder;
use Nextras\Dbal\Result\Result;
use Nextras\Orm\Entity\Reflection\EntityMetadata;
use Nextras\Orm\Exception\InvalidStateException;
use Nextras\Orm\Mapper\Dbal\Conventions\Conventions;
use Nextras\Orm\Mapper\Dbal\Conventions\IConventions;
use Nextras\Orm\Mapper\Dbal\Conventions\Inflector\CamelCaseInflector;
use Nextras\Orm\Mapper\Dbal\Conventions\Inflector\IInflector;
use Nextras\Orm\Mapper\Dbal\DbalMapperCoordinator;

abstract class Mapper extends \Nextras\Orm\Mapper\Mapper
{
	public array $onCreateTable = [];

	private Table $table;
	private MapperManager $manager;
	private bool $isCached = false;

	public function __construct(IConnection $connection, DbalMapperCoordinator $mapperCoordinator, Cache $cache, MapperManager $manager)
	{
		parent::__construct($connection, $mapperCoordinator, $cache);
		$this->manager = $manager;
		$this->table = $this->checkTable();
	}

	public function getTableName(): string
	{
		if ($this->manager->useCamelCase) {
			if (!$this->tableName) {
				$this->tableName = str_replace('Mapper', '', lcfirst((new \ReflectionClass($this))->getShortName()));
			}
		} else {
			parent::getTableName();
		}

		return $this->getTablePrefix() . $this->tableName;
	}

	public function getStructure(): Table
	{
		return $this->table;
	}

	/** @throws QueryException */
	protected function execute(QueryBuilder $builder): ?Result
	{
		return $this->connection->queryArgs($builder->getQuerySql(), $builder->getQueryParameters());
	}

	protected function prepareFulltext(string $text): string
	{
		$text = Strings::replace($text, '/\(|\)|@|\*|-|\+|<|>/', '');
		return "*$text*";
	}

	public function getTablePrefix(): string
	{
		return '';
	}

	private function checkTable(): Table
	{
		$key = $this->getTableName() . 'Structure';
		$result = $this->cache->load($key);
		if ($result === null) {
			$result = $this->cache->save($key, function () {
				$table = $this->manager->tableFactory->create($this->getTableName(), $this->getTablePrefix());
				$this->createTable($table);
				if ($this->manager->autoManageDb) {
					$isNew = $table->check();
					if ($isNew) {
						Arrays::invoke($this->onCreateTable);
					}
				}
				return $table;
			});
		} else {
			$this->isCached = true;
		}
		return $result;
	}

	public function getConventions(): IConventions
	{
		if ($this->isCached) {
			return parent::getConventions();
		} else {
			return $this->createCleanConventions();
		}
	}

	private function createCleanConventions(): Conventions
	{
		return new class (
			$this->createInflector(),
			$this->connection,
			$this->getTableName(),
			$this->getRepository()->getEntityMetadata(),
			$this->cache
		) extends Conventions {
			public function __construct(IInflector $inflector, IConnection $connection, string $storageName, EntityMetadata $entityMetadata, Cache $cache)
			{
				$this->inflector = $inflector;
				$this->platform = $connection->getPlatform();
				$this->entityMetadata = $entityMetadata;
				$this->storageName = $storageName;
				$this->storageNameWithSchema = str_contains($storageName, '.');
				$this->storageTable = $this->findStorageTable($this->storageName);

				$this->mappings = $this->getDefaultMappings();
				$this->modifiers = $this->getDefaultModifiers();
			}

			private function findStorageTable(string $tableName): \Nextras\Dbal\Platforms\Data\Table
			{
				if ($this->storageNameWithSchema) {
					[$schema, $tableName] = explode('.', $tableName);
				} else {
					$schema = null;
				}

				$tables = $this->platform->getTables($schema);
				foreach ($tables as $table) {
					if ($table->name === $tableName) {
						return $table;
					}
				}

				throw new InvalidStateException("Cannot find '$tableName' table reflection.");
			}
		};
	}

	abstract protected function createTable(Table $table): void;

	#[Pure] protected function createInflector(): IInflector
	{
		return new CamelCaseInflector();
	}

	/** @throws QueryException */
	protected function insert(array $data): void
	{

		if (is_array(reset($data))) {
			$this->connection->query('INSERT INTO ' . $this->getTableName() . ' %values[]', $data);
		} else {
			$this->connection->query('INSERT INTO ' . $this->getTableName() . ' %values', $data);
		}

	}

	/** @throws QueryException */
	public function changeSort(string $column, $id, $prevId, $nextId): void
	{
		$repo = $this->getRepository();
		$entity = $repo->getById($id);
		$prevEntity = $repo->getById($prevId);
		$nextEntity = $repo->getById($nextId);

		if ($nextEntity !== null && $entity->$column > $nextEntity->$column) {
			try {
				$this->connection->transactional(function (Connection $connection) use ($column, $entity, $nextEntity) {
					$connection->query('UPDATE %table SET %column = %column + 1 WHERE %column BETWEEN %i AND %i', $this->getTableName(), $column, $column, $column, $nextEntity->$column, $entity->$column);
				});
			} catch (\Exception $ex) {
				throw new $ex;
			}
			$entity->$column = $nextEntity->$column;
		} elseif ($prevEntity !== null) {
			try {
				$this->connection->transactional(function (Connection $connection) use ($column, $entity, $prevEntity) {
					$connection->query('UPDATE %table SET %column = %column - 1 WHERE %column BETWEEN %i AND %i', $this->getTableName(), $column, $column, $column, $entity->$column, $prevEntity->$column);
				});
			} catch (\Exception $ex) {
				throw new $ex;
			}
			$entity->$column = $prevEntity->$column;
		} else {
			$entity->$column = 1;
		}
		$repo->persistAndFlush($entity);
	}

	/** @throws QueryException */
	public function getMax(string $column): int
	{
		return $this->connection->query('SELECT IFNULL(MAX(%column), 0) position FROM %table', $column, $this->getTableName())->fetch()->position;
	}

	/** @throws QueryException */
	public function getMin(string $column): int
	{
		return $this->connection->query('SELECT IFNULL(MIN(%column), 0) position FROM %table', $column, $this->getTableName())->fetch()->position;
	}

	/** @throws QueryException */
	public function truncate(): void
	{
		$this->connection->query('TRUNCATE TABLE %table', $this->getTableName());
	}

}
