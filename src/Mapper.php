<?php

declare(strict_types=1);

namespace NAttreid\Orm;

use NAttreid\Orm\Structure\Table;
use NAttreid\Utils\Arrays;
use NAttreid\Utils\Strings;
use Nette\Caching\Cache;
use Nette\DI\MissingServiceException;
use Nextras\Dbal\Connection;
use Nextras\Dbal\DriverException;
use Nextras\Dbal\IConnection;
use Nextras\Dbal\QueryBuilder\QueryBuilder;
use Nextras\Dbal\QueryException;
use Nextras\Dbal\Result\Result;
use Nextras\Orm\Entity\IEntity;
use Nextras\Orm\Mapper\Dbal\DbalMapperCoordinator;
use Nextras\Orm\Mapper\Dbal\StorageReflection\CamelCaseStorageReflection;
use Nextras\Orm\Mapper\Dbal\StorageReflection\StorageReflection;

/**
 * Mapper
 *
 * @author Attreid <attreid@gmail.com>
 */
abstract class Mapper extends \Nextras\Orm\Mapper\Mapper
{

	/** @var Table */
	private $table;

	/** @var callback[] */
	public $onCreateTable = [];

	/** @var MapperManager */
	private $manager;


	public function __construct(IConnection $connection, DbalMapperCoordinator $mapperCoordinator, Cache $cache, MapperManager $manager)
	{
		parent::__construct($connection, $mapperCoordinator, $cache);
		$this->manager = $manager;
		$this->table = $this->checkTable();
	}

	/** @inheritdoc */
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

	/**
	 * @return Table
	 */
	public function getStructure(): Table
	{
		return $this->table;
	}

	/**
	 * Vrati vysledek dotazu
	 * @param QueryBuilder $builder
	 * @return Result|null
	 * @throws QueryException
	 */
	protected function execute(QueryBuilder $builder): ?Result
	{
		return $this->connection->queryArgs($builder->getQuerySql(), $builder->getQueryParameters());
	}

	/**
	 * Upravi retezec pro pouziti v MATCH AGAINST
	 * @param string $text
	 * @return string
	 */
	protected function prepareFulltext(string $text): string
	{
		$text = Strings::replace($text, '/\(|\)|@|\*|-|\+/', '');
		return "*$text*";
	}

	/**
	 * Vrati predponu nazvu tabulky
	 * @return string
	 */
	public function getTablePrefix(): string
	{
		return '';
	}

	/**
	 * Vrati radek podle hash sloupce
	 * @param string $column
	 * @param string $hash
	 * @return IEntity|null
	 */
	public function getByHash(string $column, string $hash): ?IEntity
	{
		if ($this->manager->hasher === null) {
			throw new MissingServiceException('Hasher is missing');
		}
		return $this->toEntity($this->manager->hasher->hashSQL($this->builder(), $column, $hash));
	}

	/**
	 * @return Table
	 */
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
						$this->onCreateTable();
					}
				}
				return $table;
			});
		}
		return $result;
	}

	/**
	 * Nastavi strukturu tabulky
	 * @param Table $table
	 */
	abstract protected function createTable(Table $table): void;

	/** @inheritdoc */
	protected function createStorageReflection(): StorageReflection
	{
		return new CamelCaseStorageReflection(
			$this->connection,
			$this->getTableName(),
			$this->getRepository()->getEntityMetadata()->getPrimaryKey(),
			$this->cache
		);
	}

	/**
	 * INSERT
	 * @param array $data
	 * @throws QueryException
	 */
	protected function insert(array $data): void
	{
		if (Arrays::isMultidimensional($data)) {
			$this->connection->query('INSERT INTO ' . $this->getTableName() . ' %values[]', $data);
		} else {
			$this->connection->query('INSERT INTO ' . $this->getTableName() . ' %values', $data);
		}

	}

	/**
	 * Zmeni razeni
	 * @param string $column
	 * @param int $id
	 * @param int $prevId
	 * @param int $nextId
	 * @throws QueryException
	 * @throws DriverException
	 */
	public function changeSort(string $column, int $id, ?int $prevId, ?int $nextId): void
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

	/**
	 * Vrati nejvetsi pozici
	 * @param string $column
	 * @return int
	 * @throws QueryException
	 */
	public function getMax(string $column): int
	{
		return $this->connection->query('SELECT IFNULL(MAX(%column), 0) position FROM %table', $column, $this->getTableName())->fetch()->position;
	}

	/**
	 * Vrati nejmensi pozici
	 * @param string $column
	 * @return int
	 * @throws QueryException
	 */
	public function getMin(string $column): int
	{
		return $this->connection->query('SELECT IFNULL(MIN(%column), 0) position FROM %table', $column, $this->getTableName())->fetch()->position;
	}

	/**
	 * Smaze data v tabulce
	 * @throws QueryException
	 */
	public function truncate(): void
	{
		$this->connection->query('TRUNCATE TABLE %table', $this->getTableName());
	}

}
