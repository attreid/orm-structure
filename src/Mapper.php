<?php

namespace NAttreid\Orm;

use Nextras\Orm\Mapper\Dbal\StorageReflection\CamelCaseStorageReflection,
    Nette\Caching\Cache,
    NAttreid\Orm\Structure\Table,
    Nextras\Dbal\Connection;

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
     * Vrati predponu nazvu tabulky
     * @return string
     */
    public function getTablePrefix() {
        return '';
    }

    private function checkTable($cache) {
        $key = $this->getTableName() . 'Generator';
        $result = $this->cacheLoad($key);
        if ($result === NULL) {
            $result = $this->cacheSave($key, function() use ($cache) {
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
     * Smazani cache
     * @param array $args
     */
    protected function cleanCache(array $args) {
        $this->cache->clean($args);
    }

    /**
     * Vrati vysledek z cache nebo jej do cache ulozi
     * @param mixed $data
     * @param array $dependencies
     * @return mixed
     */
    protected function cache($data, array $dependencies = NULL) {
        $backtrace = debug_backtrace();
        $info = $backtrace[1];
        $key = $info['class'] . '_' . $info['function'] . md5(serialize($info['args']));

        $result = $this->cacheLoad($key);
        if ($result === NULL) {
            $result = $this->cacheSave($key, $data, $dependencies);
        }
        return $result;
    }

    /**
     * Vrati vysledek z cache
     * @param string $key
     * @return mixed
     */
    protected function cacheLoad($key) {
        return $this->cache->load($key);
    }

    /**
     * Ulozeni cache
     * @param string $key
     * @param mixed $data
     * @param array $dependencies
     * @return mixed
     */
    protected function cacheSave($key, $data, array $dependencies = NULL) {
        $dependencies[Cache::TAGS][] = self::TAG_MODEL;
        return $this->cache->save($key, $data, $dependencies);
    }

    /**
     * INSERT
     * @param array $data
     */
    protected function insert(array $data) {
        $this->connection->query('INSERT INTO ' . $this->getTableName() . ' %values', $data);
    }

}
