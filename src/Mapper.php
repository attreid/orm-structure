<?php

namespace NAttreid\Orm;

use Nextras\Orm\Mapper\Dbal\StorageReflection\CamelCaseStorageReflection,
    Nette\Caching\Cache,
    NAttreid\Orm\Structure\Table,
    Nextras\Dbal\Connection,
    Nextras\Dbal\QueryBuilder\QueryBuilder,
    NAttreid\Orm\Structure\ITableFactory,
    NAttreid\Utils\Hasher,
    Nextras\Orm\Entity\IEntity;

/**
 * Mapper
 *
 * @author Attreid <attreid@gmail.com>
 */
abstract class Mapper extends \Nextras\Orm\Mapper\Mapper {

    /** @var ITableFactory */
    private $tableFactory;

    /** @var Hasher */
    private $hasher;

    public function __construct(Connection $connection, Cache $cache, ITableFactory $tableFactory, Hasher $hasher = NULL) {
        parent::__construct($connection, $cache);
        $this->tableFactory = $tableFactory;
        $this->hasher = $hasher;
        $this->checkTable();
    }

    /** @inheritdoc */
    public function getTableName() {
        if (!$this->tableName) {
            $this->tableName = str_replace('Mapper', '', lcfirst($this->getReflection()->getShortName()));
        }

        return $this->getTablePrefix() . $this->tableName;
    }

    /**
     * Vrati vysledek dotazu
     * @param QueryBuilder $builder
     * @return Result | NULL
     */
    protected function execute(QueryBuilder $builder) {
        return $this->connection->queryArgs($builder->getQuerySql(), $builder->getQueryParameters());
    }

    /**
     * Vrati entitu dotazu
     * @param QueryBuilder $builder
     * @return IEntity
     */
    protected function fetch(QueryBuilder $builder) {
        return $this->toCollection($builder)->fetch();
    }

    /**
     * Vrati predponu nazvu tabulky
     * @return string
     */
    public function getTablePrefix() {
        return '';
    }

    /**
     * Vrati radek podle hash sloupce
     * @param string $column
     * @param string $hash
     * @return IEntity
     */
    public function getByHash($column, $hash) {
        if ($this->hasher === NULL) {
            throw new \Nette\DI\MissingServiceException('Hasher is missing');
        }
        return $this->fetch($this->hasher->hashSQL($this->builder(), $column, $hash));
    }

    private function checkTable() {
        $key = $this->getTableName() . 'Generator';
        $result = $this->cache->load($key);
        if ($result === NULL) {
            $result = $this->cache->save($key, function() {
                $table = $this->tableFactory->create($this->getTableName(), $this->getTablePrefix());
                $this->createTable($table);

                if ($table->check()) {
                    $this->loadDefaultData();
                }
                return TRUE;
            });
        }
    }

    /**
     * Nastavi strukturu tabulky
     * @param Table $table
     */
    abstract protected function createTable(Table $table);

    /**
     * Nacteni vychozich dat do tabulky po vytvoreni
     */
    protected function loadDefaultData() {
        
    }

    /** @inheritdoc */
    protected function createStorageReflection() {
        return new CamelCaseStorageReflection(
                $this->connection, $this->getTableName(), $this->getRepository()->getEntityMetadata()->getPrimaryKey(), $this->cache
        );
    }

    /**
     * INSERT
     * @param array $data
     */
    protected function insert(array $data) {
        $this->connection->query('INSERT INTO ' . $this->getTableName() . ' %values', $data);
    }

}
