<?php

namespace NAttreid\Orm;

use NAttreid\Orm\Structure\ITableFactory;
use NAttreid\Orm\Structure\Table;
use NAttreid\Utils\Hasher;
use Nette\Caching\Cache;
use Nette\DI\MissingServiceException;
use Nextras\Dbal\Connection;
use Nextras\Dbal\QueryBuilder\QueryBuilder;
use Nextras\Dbal\Result\Result;
use Nextras\Orm\Entity\IEntity;
use Nextras\Orm\Mapper\Dbal\StorageReflection\CamelCaseStorageReflection;

/**
 * Mapper
 *
 * @author Attreid <attreid@gmail.com>
 */
abstract class Mapper extends \Nextras\Orm\Mapper\Mapper
{

	/** @var ITableFactory */
	private $tableFactory;

	/** @var Hasher */
	private $hasher;

	/** @var Table */
	private $table;

	public function __construct(Connection $connection, Cache $cache, ITableFactory $tableFactory, Hasher $hasher = null)
	{
		parent::__construct($connection, $cache);
		$this->tableFactory = $tableFactory;
		$this->hasher = $hasher;
		$this->table = $this->checkTable();
	}

	/** @inheritdoc */
	public function getTableName()
	{
		if (!$this->tableName) {
			$this->tableName = str_replace('Mapper', '', lcfirst($this->getReflection()->getShortName()));
		}

		return $this->getTablePrefix() . $this->tableName;
	}

	/**
	 * @return Table
	 */
	public function getStructure()
	{
		return $this->table;
	}

	/**
	 * Vrati vysledek dotazu
	 * @param QueryBuilder $builder
	 * @return Result|null
	 */
	protected function execute(QueryBuilder $builder)
	{
		return $this->connection->queryArgs($builder->getQuerySql(), $builder->getQueryParameters());
	}

	/**
	 * Vrati entitu dotazu
	 * @param QueryBuilder $builder
	 * @return IEntity
	 */
	protected function fetch(QueryBuilder $builder)
	{
		return $this->toCollection($builder)->fetch();
	}

	/**
	 * Vrati predponu nazvu tabulky
	 * @return string
	 */
	public function getTablePrefix()
	{
		return '';
	}

	/**
	 * Vrati radek podle hash sloupce
	 * @param string $column
	 * @param string $hash
	 * @return IEntity
	 */
	public function getByHash($column, $hash)
	{
		if ($this->hasher === null) {
			throw new MissingServiceException('Hasher is missing');
		}
		return $this->fetch($this->hasher->hashSQL($this->builder(), $column, $hash));
	}

	/**
	 * @return Table
	 */
	private function checkTable()
	{
		$key = $this->getTableName() . 'Structure';
		$result = $this->cache->load($key);
		if ($result === null) {
			$result = $this->cache->save($key, function () {
				$table = $this->tableFactory->create($this->getTableName(), $this->getTablePrefix());
				$this->createTable($table);
				$table->check();
				return $table;
			});
		}
		return $result;
	}

	/**
	 * Nastavi strukturu tabulky
	 * @param Table $table
	 */
	abstract protected function createTable(Table $table);

	/** @inheritdoc */
	protected function createStorageReflection()
	{
		return new CamelCaseStorageReflection(
			$this->connection, $this->getTableName(), $this->getRepository()->getEntityMetadata()->getPrimaryKey(), $this->cache
		);
	}

	/**
	 * INSERT
	 * @param array $data
	 */
	protected function insert(array $data)
	{
		$this->connection->query('INSERT INTO ' . $this->getTableName() . ' %values', $data);
	}

	/**
	 * Zmeni razeni
	 * @param string $column
	 * @param mixed $id
	 * @param mixed $prevId
	 * @param mixed $nextId
	 */
	public function changeSort($column, $id, $prevId, $nextId)
	{
		$repo = $this->getRepository();
		$entity = $repo->getById($id);
		$prevEntity = $repo->getById($prevId);
		$nextEntity = $repo->getById($nextId);

		if ($nextEntity !== null && $entity->$column > $nextEntity->$column) {
			$this->connection->transactional(function (Connection $connection) use ($column, $entity, $nextEntity) {
				$connection->query('UPDATE %table SET %column = %column + 1 WHERE %column BETWEEN %i AND %i', $this->getTableName(), $column, $column, $column, $nextEntity->$column, $entity->$column);
			});
			$entity->$column = $nextEntity->$column;
		} elseif ($prevEntity !== null) {
			$this->connection->transactional(function (Connection $connection) use ($column, $entity, $prevEntity) {
				$connection->query('UPDATE %table SET %column = %column - 1 WHERE %column BETWEEN %i AND %i', $this->getTableName(), $column, $column, $column, $entity->$column, $prevEntity->$column);
			});
			$entity->$column = $prevEntity->$column;
		} else {
			$entity->$column = 1;
		}
		$repo->persistAndFlush($entity);
	}

}
