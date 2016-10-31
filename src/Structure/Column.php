<?php

namespace NAttreid\Orm\Structure;

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
	private $default = 'NOT null';

	/** @var Table */
	private $table;

	public function __construct(Table $table, $name)
	{
		$this->name = $name;
		$this->table = $table;
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
		$this->default = 'NOT null DEFAULT CURRENT_TIMESTAMP' . ($onUpdate ? ' ON UPDATE CURRENT_TIMESTAMP' : '');
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
			$this->default = 'NOT null';
		} elseif ($default === null) {
			$this->default = 'DEFAULT null';
		} else {
			$this->default = ($empty ? '' : 'NOT null ') . "DEFAULT '$default'";
		}
		return $this;
	}

	/**
	 * Nastavi autoIncrement
	 */
	public function setAutoIncrement()
	{
		$this->default = 'NOT null AUTO_INCREMENT';
		$this->table->setAutoIncrement(1);
	}

	public function __toString()
	{
		return "`$this->name` $this->type $this->default";
	}

}
