<?php

declare(strict_types = 1);

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
	private $default = 'NOT null';

	/** @var Table */
	private $table;

	public function __construct(Table $table, string $name)
	{
		$this->name = $name;
		$this->table = $table;
	}

	/**
	 * Pripravi sloupec pro porovnani
	 * @param Row $row
	 * @return string
	 */
	private function prepareColumn(Row $row): string
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
	 * Nastavi typ na bool (hodnota 0,1)
	 * @return self
	 */
	public function bool(): self
	{
		$this->type = 'tinyint(1)';
		return $this;
	}

	/**
	 * Nastavi hodnotu sloupce na unikatni
	 * @return self
	 */
	public function setUnique(): self
	{
		$this->table->addUnique($this->name);
		return $this;
	}

	/**
	 * Nastavi klic
	 * @return self
	 */
	public function setKey(): self
	{
		$this->table->addKey($this->name);
		return $this;
	}

	/**
	 * Nastave jako fulltext
	 * @return $this
	 */
	public function setFulltext(): self
	{
		$this->table->addFulltext($this->name);
		return $this;
	}

	/**
	 * Nastavi typ na int
	 * @param int $size
	 * @return self
	 */
	public function int(int $size = 11): self
	{
		$this->type = 'int(' . $size . ')';
		return $this;
	}

	/**
	 * Nastavi typ na bigint
	 * @param int $size
	 * @return self
	 */
	public function bigint(int $size = 15): self
	{
		$this->type = 'bigint(' . $size . ')';
		return $this;
	}

	/**
	 * Nastavi typ na decimal
	 * @param int $total
	 * @param int $decimal
	 * @return self
	 */
	public function decimal(int $total, int $decimal): self
	{
		$this->type = 'decimal(' . $total . ',' . $decimal . ')';
		return $this;
	}

	/**
	 * Nastavi typ na float
	 * @param int $total
	 * @param int $decimal
	 * @return self
	 */
	public function float(int $total, int $decimal): self
	{
		$this->type = 'float(' . $total . ',' . $decimal . ')';
		return $this;
	}

	/**
	 * Nastavi typ na varchar
	 * @param int $size
	 * @return self
	 */
	public function varChar(int $size = 255): self
	{
		$this->type = 'varchar(' . $size . ') COLLATE ' . $this->table->collate;
		return $this;
	}

	/**
	 * Nastavi typ na char
	 * @param int $size
	 * @return self
	 */
	public function char(int $size = 36): self
	{
		$this->type = 'char(' . $size . ') COLLATE ' . $this->table->collate;
		return $this;
	}

	/**
	 * Nastavi typ na text
	 * @return self
	 */
	public function text(): self
	{
		$this->type = 'text COLLATE ' . $this->table->collate;
		return $this;
	}

	/**
	 * Nastavi typ na datetime
	 * @return self
	 */
	public function datetime(): self
	{
		$this->type = 'datetime';
		return $this;
	}

	/**
	 * Nastavi typ na date
	 * @return self
	 */
	public function date(): self
	{
		$this->type = 'date';
		return $this;
	}

	/**
	 * Nastavi typ na timestamp (pri vytvoreni se ulozi datum)
	 * @param bool $onUpdate true = datum se zmeni pri zmene
	 * @return self
	 */
	public function timestamp(bool $onUpdate = false): self
	{
		$this->type = 'timestamp';
		$this->default = 'NOT null DEFAULT CURRENT_TIMESTAMP' . ($onUpdate ? ' ON UPDATE CURRENT_TIMESTAMP' : '');
		$this->setDefault('CURRENT_TIMESTAMP');
		return $this;
	}

	/**
	 * Nastavi default
	 * @param mixed $default false => NOT null (default), null => DEFAULT null, ostatni DEFAULT dana hodnota
	 * @param bool $empty
	 * @return self
	 */
	public function setDefault($default = false, bool $empty = false): self
	{
		if ($this->type == 'timestamp') {
			return $this;
		}
		if ($default === false) {
			$this->default = 'NOT null';
		} elseif ($default === null) {
			$this->default = 'DEFAULT null';
		} else {
			$this->default = ($empty ? '' : 'NOT null ') . "DEFAULT {$this->table->escapeString((string)$default)}";
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

	/**
	 * @param Column $column
	 * @return $this
	 */
	public function setType(Column $column): self
	{
		$this->type = $column->type;
		return $this;
	}

	/**
	 * Porovnani sloupcu
	 * @param string $column
	 * @return bool
	 */
	public function equals(Row $column): bool
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
