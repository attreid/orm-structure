<?php

namespace NAttreid\Orm\Structure;

/**
 * Sloupec
 *
 * @author Attreid <attreid@gmail.com>
 */
class Column {

    /** @var string */
    private $name;

    /** @var string */
    private $type;

    /** @var string */
    private $default = 'NOT NULL';

    /** @var Table */
    private $table;

    public function __construct(Table $table, $name) {
        $this->name = $name;
        $this->table = $table;
    }

    /**
     * Nastavi typ na boolean (hodnota 0,1)
     * @return this
     */
    public function boolean() {
        $this->type = 'tinyint(1)';
        return $this;
    }

    /**
     * Nastavi hodnotu sloupce na unikatni
     * @return this
     */
    public function setUnique() {
        $this->table->setUnique($this->name);
        return $this;
    }

    /**
     * Nastavi klic
     * @return this
     */
    public function setKey() {
        $this->table->setKey($this->name);
        return $this;
    }

    /**
     * Nastavi typ na int
     * @param int $size
     * @return this
     */
    public function int($size = 11) {
        $this->type = 'int(' . (int) $size . ')';
        return $this;
    }

    /**
     * Nastavi typ na decimal
     * @param int $total
     * @param int $decimal
     * @return this
     */
    public function decimal($total, $decimal) {
        $this->type = 'decimal(' . (int) $total . ',' . (int) $decimal . ')';
        return $this;
    }

    /**
     * Nastavi typ na float
     * @param int $total
     * @param int $decimal
     * @return this
     */
    public function float($total, $decimal) {
        $this->type = 'float(' . (int) $total . ',' . (int) $decimal . ')';
        return $this;
    }

    /**
     * Nastavi typ na varchar
     * @param int $size
     * @return this
     */
    public function varChar($size = 255) {
        $this->type = 'varchar(' . $size . ') COLLATE ' . $this->table->collate;
        return $this;
    }

    /**
     * Nastavi typ na char
     * @param int $size
     * @return this
     */
    public function char($size = 36) {
        $this->type = 'char(' . $size . ') COLLATE ' . $this->table->collate;
        return $this;
    }

    /**
     * Nastavi typ na text
     * @return this
     */
    public function text() {
        $this->type = 'text COLLATE ' . $this->table->collate;
        return $this;
    }

    /**
     * Nastavi typ na datetime
     * @return this
     */
    public function datetime() {
        $this->type = 'datetime';
        return $this;
    }

    /**
     * Nastavi typ na date
     * @return this
     */
    public function date() {
        $this->type = 'date';
        return $this;
    }

    /**
     * Nastavi typ na timestamp (pri vytvoreni se ulozi datum)
     * @param boolean $onUpdate TRUE = datum se zmeni pri zmene
     * @return this
     */
    public function timestamp($onUpdate = FALSE) {
        $this->type = 'timestamp';
        $this->default = 'NOT NULL DEFAULT CURRENT_TIMESTAMP' . ($onUpdate ? ' ON UPDATE CURRENT_TIMESTAMP' : '');
        $this->setDefault('CURRENT_TIMESTAMP');
        return $this;
    }

    /**
     * Nastavi default
     * @param mixed $default FALSE => NOT NULL (default), NULL => DEFAULT NULL, ostatni DEFAULT dana hodnota
     * @param boolean $empty
     * @return this
     */
    public function setDefault($default = FALSE, $empty = FALSE) {
        if ($this->type == 'timestamp') {
            return $this;
        }
        if ($default === FALSE) {
            $this->default = 'NOT NULL';
        } elseif ($default === NULL) {
            $this->default = 'DEFAULT NULL';
        } else {
            $this->default = ($empty ? '' : 'NOT NULL ') . "DEFAULT '$default'";
        }
        return $this;
    }

    /**
     * Nastavi autoIncrement
     */
    public function setAutoIncrement() {
        $this->default = 'NOT NULL AUTO_INCREMENT';
        $this->table->setAutoIncrement(1);
    }

    public function __toString() {
        return "`$this->name` $this->type $this->default";
    }

}
