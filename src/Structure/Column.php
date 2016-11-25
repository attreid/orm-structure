<?php

namespace NAttreid\Orm\Structure;

use Nextras\Dbal\Result\Row;
use Nextras\Orm\InvalidStateException;

/**
 * Sloupec
 *
 * @author Attreid <attreid@gmail.com>
 */
class Column
{

	/** @var string */
	private $name;

	/** @var string */
	private $type;

	/** @var string */
	private $default = 'NOT NULL';

	/** @var Table */
	private $table;

	public function __construct(Table $table, $name)
	{
		$this->name = $name;
		$this->table = $table;
	}

	/**
	 * Pripravi sloupec pro porovnani
	 * @param Row $row
	 * @return string
	 */
	private function prepareColumn(Row $row)
	{
		$nullable = $row->Null === 'YES';

		if ($row->Default === null && !$nullable) {
			$default = ' NOT null';
		} elseif ($row->Default === null && $nullable) {
			$default = ' DEFAULT null';
		} else {
			$default = ($nullable ? '' : ' NOT null') . " DEFAULT '{$row->Default}'";
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

	/**
	 * Nastavi typ na boolean (hodnota 0,1)
	 * @return self
	 */
	public function boolean()
	{
		$this->type = 'tinyint(1)';
		return $this;
	}

	/**
	 * Nastavi hodnotu sloupce na unikatni
	 * @return self
	 */
	public function setUnique()
	{
		$this->table->addUnique($this->name);
		return $this;
	}

	/**
	 * Nastavi klic
	 * @return self
	 */
	public function setKey()
	{
		$this->table->addKey($this->name);
		return $this;
	}

	/**
	 * Nastave jako fulltext
	 * @return $this
	 */
	public function setFulltext()
	{
		$this->table->addFulltext($this->name);
		return $this;
	}

	/**
	 * Nastavi typ na int
	 * @param int $size
	 * @return self
	 */
	public function int($size = 11)
	{
		$this->type = 'int(' . (int)$size . ')';
		return $this;
	}

	/**
	 * Nastavi typ na bigint
	 * @param int $size
	 * @return self
	 */
	public function bigint($size = 15)
	{
		$this->type = 'bigint(' . (int)$size . ')';
		return $this;
	}

	/**
	 * Nastavi typ na decimal
	 * @param int $total
	 * @param int $decimal
	 * @return self
	 */
	public function decimal($total, $decimal)
	{
		$this->type = 'decimal(' . (int)$total . ',' . (int)$decimal . ')';
		return $this;
	}

	/**
	 * Nastavi typ na float
	 * @param int $total
	 * @param int $decimal
	 * @return self
	 */
	public function float($total, $decimal)
	{
		$this->type = 'float(' . (int)$total . ',' . (int)$decimal . ')';
		return $this;
	}

	/**
	 * Nastavi typ na varchar
	 * @param int $size
	 * @return self
	 */
	public function varChar($size = 255)
	{
		$this->type = 'varchar(' . $size . ') COLLATE ' . $this->table->collate;
		return $this;
	}

	/**
	 * Nastavi typ na char
	 * @param int $size
	 * @return self
	 */
	public function char($size = 36)
	{
		$this->type = 'char(' . $size . ') COLLATE ' . $this->table->collate;
		return $this;
	}

	/**
	 * Nastavi typ na text
	 * @return self
	 */
	public function text()
	{
		$this->type = 'text COLLATE ' . $this->table->collate;
		return $this;
	}

	/**
	 * Nastavi typ na datetime
	 * @return self
	 */
	public function datetime()
	{
		$this->type = 'datetime';
		return $this;
	}

	/**
	 * Nastavi typ na date
	 * @return self
	 */
	public function date()
	{
		$this->type = 'date';
		return $this;
	}

	/**
	 * Nastavi typ na timestamp (pri vytvoreni se ulozi datum)
	 * @param boolean $onUpdate true = datum se zmeni pri zmene
	 * @return self
	 */
	public function timestamp($onUpdate = false)
	{
		$this->type = 'timestamp';
		$this->default = 'NOT NULL DEFAULT CURRENT_TIMESTAMP' . ($onUpdate ? ' ON UPDATE CURRENT_TIMESTAMP' : '');
		$this->setDefault('CURRENT_TIMESTAMP');
		return $this;
	}

	/**
	 * Nastavi default
	 * @param mixed $default false => NOT null (default), null => DEFAULT null, ostatni DEFAULT dana hodnota
	 * @param boolean $empty
	 * @return self
	 */
	public function setDefault($default = false, $empty = false)
	{
		if ($this->type == 'timestamp') {
			return $this;
		}
		if ($default === false) {
			$this->default = 'NOT NULL';
		} elseif ($default === null) {
			$this->default = 'DEFAULT NULL';
		} else {
			$this->default = ($empty ? '' : 'NOT NULL ') . "DEFAULT {$this->table->escapeString($default)}";
		}
		return $this;
	}

	/**
	 * Nastavi autoIncrement
	 */
	public function setAutoIncrement()
	{
		$this->default = 'NOT NULL AUTO_INCREMENT';
		$this->table->setAutoIncrement(1);
	}

	/**
	 * @param Column $column
	 * @return $this
	 */
	public function setType(Column $column)
	{
		$this->type = $column->type;
		return $this;
	}

	/**
	 * Porovnani sloupcu
	 * @param string $column
	 * @return bool
	 */
	public function equals(Row $column)
	{
		$col = $this->prepareColumn($column);
		return $col == "`$this->name` $this->type $this->default";
	}

	public function __toString()
	{
		if ($this->type === null) {
			throw new InvalidStateException('Type is not set');
		}
		return "[$this->name] $this->type $this->default";
	}
}
