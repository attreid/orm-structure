<?php

namespace NAttreid\Orm\Structure;

use Nextras\Dbal\Connection,
    Nextras\Dbal\Result\Row,
    Nette\DI\Container,
    NAttreid\Orm\Mapper;

/**
 * Tabulka
 *
 * @author Attreid <attreid@gmail.com>
 */
class Table {

    /** @var string */
    private $name;

    /** @var Connection */
    private $connection;

    /** @var Container */
    private $container;

    /** @var string */
    private $engine = 'InnoDB';

    /** @var string */
    private $charset = 'utf8';

    /** @var string */
    public $collate = 'utf8_czech_ci';

    /** @var Column */
    private $columns = [];

    /** @var string */
    private $primaryKey = [];

    /** @var string */
    private $keys = [];

    /** @var string */
    private $constraints = [];

    /** @var int */
    private $autoIncrement = NULL;

    /** @var string */
    private $addition = NULL;

    /** @var Table[] */
    private $relationTables = [];

    /** @var string */
    private $prefix;

    public function __construct($name, $prefix, Connection $connection, Container $container) {
        $this->name = $name;
        $this->prefix = $prefix;
        $this->connection = $connection;
        $this->container = $container;
    }

    /**
     * Nastavi engine (default=InnoDB)
     * @param string $engine
     * @return this
     */
    public function setEngine($engine) {
        $this->engine = $engine;
        return $this;
    }

    /**
     * Nastavi charset (default=utf8)
     * @param string $charset
     * @return this
     */
    public function setCharset($charset) {
        $this->charset = $charset;
        return $this;
    }

    /**
     * Nastavi engine (default=utf8_czech_ci)
     * @param string $collate
     * @return this
     */
    public function setCollate($collate) {
        $this->collate = $collate;
        return $this;
    }

    /**
     * Vytvori spojovou tabulku
     * @param string $name
     * @return self
     */
    public function createRelationTable($table, $table2) {
        list($tableName) = $this->getTableData($table);
        list($tableName2) = $this->getTableData($table2);

        $name = $tableName . '_x_' . $tableName2;

        return $this->relationTables[] = new Table($name, $this->prefix, $this->connection, $this->cache);
    }

    /**
     * Proveri zda tabulka existuje a podle toho ji bud vytvori nebo upravi (pokud je treba)
     * @return boolean TRUE => pokud je vytvorena, FALSE => pokud jiz existovala
     */
    public function check() {
        $exist = $this->connection->query("SHOW TABLES LIKE %s", $this->name)->fetch();
        if (!$exist) {
            $this->create();
            $result = TRUE;
        } else {
            $this->modify();
            $result = FALSE;
        }
        foreach ($this->relationTables as $table) {
            $table->check();
        }
        return $result;
    }

    /**
     * Vytvori tabulku
     */
    private function create() {
        $query = "CREATE TABLE IF NOT EXISTS $this->name (\n"
                . implode(",\n", $this->columns) . ",\n"
                . (!empty($this->primaryKey) ? 'PRIMARY KEY (' . implode(',', $this->primaryKey) . ')' . (empty($this->keys) ? '' : ",\n") : '')
                . implode(",\n", $this->keys) . (empty($this->constraints) ? '' : ",\n")
                . implode(",\n", $this->constraints)
                . "\n) ENGINE=$this->engine" . (empty($this->autoIncrement) ? '' : " AUTO_INCREMENT=$this->autoIncrement") . " DEFAULT CHARSET=$this->charset COLLATE=$this->collate"
                . (empty($this->addition) ? '' : "/*$this->addition*/");

        $this->connection->query($query);
    }

    /**
     * Upravi tabulku
     */
    private function modify() {
        $drop = $modify = $add = $primKey = [];

        // sloupce
        $col = $this->columns;
        foreach ($this->connection->query('SHOW FULL COLUMNS FROM %table', $this->name) as $column) {
            $name = $column->Field;

            if (isset($col[$name])) {
                if ($this->prepareColumn($column) != (string) $col[$name]) {
                    $modify[] = $col[$name];
                }
                unset($col[$name]);
            } else {
                $drop[] = $name;
            }
        }
        if (!empty($col)) {
            $add[] = '(' . implode(",\n", $col) . ')';
        }

        // primarni klic
        foreach ($this->connection->query('SHOW INDEX FROM ' . $this->name . ' WHERE Key_name = %s', 'PRIMARY') as $index) {
            $primKey[] = $index->Column_name;
        }
        $primKey = implode(', ', $primKey);
        if ((array) $primKey != $this->primaryKey) {
            if (!empty($primKey)) {
                $drop[] = 'PRIMARY KEY';
            }
            if (!empty($this->primaryKey)) {
                $add[] = "PRIMARY KEY(" . implode(',', $this->primaryKey) . ")";
            }
        }

        // klice
        $keys = $this->keys;
        foreach ($this->connection->query('SHOW INDEX FROM ' . $this->name . ' WHERE Seq_in_index = %i AND Key_name != %s', 1, 'PRIMARY') as $key) {
            $name = $key->Key_name;

            if (isset($keys[$name])) {
                unset($keys[$name]);
            } else {
                $drop[] = 'INDEX ' . $name;
            }
        }
        if (!empty($keys)) {
            $add = array_merge($add, $keys);
        }

        // foreign key
        $constraints = $this->constraints;
        $foreignKeys = $this->connection->getPlatform()->getForeignKeys($this->name);
        foreach ($foreignKeys as $key) {
            $name = $key['name'];
            if (isset($constraints[$name])) {
                unset($constraints[$name]);
            } else {
                $drop[] = 'FOREIGN KEY ' . $name;
            }
        }
        if (!empty($constraints)) {
            $add = array_merge($add, $constraints);
        }

        // modify
        if (!empty($modify)) {
            $this->connection->query("ALTER TABLE $this->name MODIFY " . implode(', MODIFY ', $modify));
        }

        // drop
        if (!empty($drop)) {
            $this->connection->query("ALTER TABLE $this->name DROP " . implode(', DROP ', $drop));
        }

        // add
        if (!empty($add)) {
            $this->connection->query("ALTER TABLE $this->name ADD " . implode(', ADD ', $add));
        }
    }

    /**
     * Vrati primarni klic
     * @return array
     */
    public function getPrimaryKey() {
        return $this->primaryKey;
    }

    /**
     * Pridavek za dotaz (partition atd)
     * @param string $addition
     */
    public function add($addition) {
        $this->addition = $addition;
    }

    /**
     * Prida sloupec
     * @param string $name
     * @return Column
     */
    public function addColumn($name) {
        return $this->columns[$name] = new Column($this, $name);
    }

    /**
     * Prida primarni klic
     * @param string $name
     * @return Column
     */
    public function addPrimaryKey($name) {
        $column = $this->addColumn($name);
        $this->setPrimaryKey($name);
        return $column;
    }

    /**
     * Nastavi cizi klic
     * @param string $name
     * @param string|Table $mapperClass klic uz musi byt v tabulce nastaven
     * @param mixed $onDelete FALSE => NO ACTION, TRUE => CASCADE, NULL => SET NULL
     * @param mixed $onUpdate FALSE => NO ACTION, TRUE => CASCADE, NULL => SET NULL
     * @return Column
     */
    public function addForeignKey($name, $mapperClass, $onDelete = TRUE, $onUpdate = FALSE) {
        $column = $this->addColumn($name)
                ->int();

        if ($onDelete === NULL) {
            $column->setDefault(NULL);
        } else {
            $column->setDefault();
        }

        $this->setKey($name);

        list($tableName, $tableKey) = $this->getTableData($mapperClass);

        $foreignName = 'fk_' . $this->name . '_' . $name . '_' . $tableName . '_' . $tableKey;

        $this->constraints[$foreignName] = "CONSTRAINT `$foreignName` FOREIGN KEY (`$name`) REFERENCES `$tableName` (`$tableKey`) ON DELETE {$this->prepareOnChange($onDelete)} ON UPDATE {$this->prepareOnChange($onUpdate)}";
        return $column;
    }

    /**
     * Odebere sloupec
     * @param string $name
     */
    public function removeColumn($name) {
        unset($this->columns[$name]);
    }

    /**
     * Nastavi hodnotu sloupce na unikatni
     * @param  mixed $key [klic, ...]
     */
    public function setUnique(...$key) {
        if (count($key) > 0) {
            $this->keys[implode('_', $key)] = 'UNIQUE ' . $this->prepareKey($key);
        }
        return $this;
    }

    /**
     * Nastavi klic
     * @param  mixed $key [klic, ...]
     * @return this
     */
    public function setKey(...$key) {
        if (count($key) > 0) {
            $this->keys[implode('_', $key)] = $this->prepareKey($key);
        }
        return $this;
    }

    /**
     * Nastavi primarni klic
     * @param  mixed $key [klic, ...]
     */
    public function setPrimaryKey(...$key) {
        if (count($key) > 0) {
            $this->primaryKey = $key;
        }
        return $this;
    }

    /**
     * Nastavi auto increment
     * @param int $first
     * @return this
     */
    public function setAutoIncrement($first) {
        $this->autoIncrement = $first;
        return $this;
    }

    /**
     * Vrati nazev tabulky a jeji klic
     * @param Table|Mapper $table
     * @return array[name, primaryKey]
     * @throws \InvalidArgumentException
     */
    private function getTableData($table) {
        if ($table instanceof Table) {
            return [
                $table->name,
                $table->getPrimaryKey()[0]
            ];
        } elseif (is_subclass_of($table, Mapper::class)) {
            /* @var $mapper Mapper */
            $mapper = $this->container->getByType($table);
            $name = $mapper->getTableName();
            return [
                $name,
                $this->connection->query('SHOW INDEX FROM %table WHERE Key_name = %s ', $name, 'PRIMARY')->fetch()->Column_name
            ];
        } else {
            throw new \InvalidArgumentException;
        }
    }

    /**
     * Pripravi klice
     * @param array $args
     * @return string
     */
    private function prepareKey($args) {
        $name = '';
        $key = '';
        foreach ($args as $arg) {
            if (!empty($key)) {
                $name .= '_';
                $key .= ',';
            }
            $name .= $arg;
            $key .= "`$arg`";
        }

        return "KEY $name ($key)";
    }

    /**
     * Vrati hodnotu pro zmenu
     * @param mixed $value
     * @return string
     */
    private function prepareOnChange($value) {
        if ($value === FALSE) {
            return 'NO ACTION';
        } elseif ($value === NULL) {
            return 'SET NULL';
        } else {
            return 'CASCADE';
        }
    }

    /**
     * Pripravi sloupec pro porovnani
     * @param Row $row
     * @return string
     */
    private function prepareColumn(Row $row) {
        $nullable = $row->Null === 'YES';

        if ($row->Default === NULL && !$nullable) {
            $default = ' NOT NULL';
        } elseif ($row->Default === NULL && $nullable) {
            $default = ' DEFAULT NULL';
        } else {
            $default = ($nullable ? '' : ' NOT NULL') . " DEFAULT '{$row->Default}'";
        }

        if (!empty($row->Collation)) {
            $collate = ' COLLATE ' . $row->Collation;
        } else {
            $collate = '';
        }

        if ($row->Extra === 'auto_increment') {
            $autoIncrement = ' AUTO_INCREMENT';
        } else {
            $autoIncrement = '';
        }

        return "`{$row->Field}` "
                . $row->Type
                . $collate
                . $default
                . $autoIncrement;
    }

}

interface ITableFactory {

    /** @return Table */
    public function create($name, $prefix);
}
