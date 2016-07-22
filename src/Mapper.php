<?php

namespace NAttreid\Orm;

use Nextras\Orm\Mapper\Dbal\StorageReflection\CamelCaseStorageReflection,
    Nette\Caching\Cache,
    NAttreid\Orm\Structure\Table,
    Nextras\Dbal\Connection,
    Nextras\Dbal\QueryBuilder\QueryBuilder;

/**
 * Mapper
 *
 * @author Attreid <attreid@gmail.com>
 */
abstract class Mapper extends \Nextras\Orm\Mapper\Mapper {

    const TAG_MODEL = 'mapper/model';

    public function __construct(Connection $connection, Cache $cache) {
        parent::__construct($connection, $cache);
        $this->checkTable($cache);
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
     * Vrati predponu nazvu tabulky
     * @return string
     */
    public function getTablePrefix() {
        return '';
    }

    private function checkTable($cache) {
        $key = $this->getTableName() . 'Generator';
        $result = $this->cache->load($key);
        if ($result === NULL) {
            $result = $this->cache->save($key, function() use ($cache) {
                $table = new Table($this->getTableName(), $this->getTablePrefix(), $this->connection, $cache);
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
