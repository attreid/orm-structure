<?php

namespace NAttreid\Orm\Structure;

use Nette\SmartObject;
use Nextras\Dbal\Result\Row;

/**
 * Class Constrait
 *
 * @property-read string $name
 * @property-read Column $column
 *
 * @author Attreid <attreid@gmail.com>
 */
class Constrait
{
	use SmartObject;

	/** @var string */
	private $name;

	/** @var string */
	private $key;

	/** @var Table */
	private $table;

	/** @var Table */
	private $referenceTable;

	/** @var bool|mixed */
	private $onDelete;

	/** @var bool|mixed */
	private $onUpdate;

	/** @var Column */
	private $column;

	/**
	 * Constrait constructor.
	 * @param string $key
	 * @param Table $table
	 * @param Table $referenceTable
	 * @param mixed $onDelete false => NO ACTION, true => CASCADE, null => SET NULL
	 * @param mixed $onUpdate false => NO ACTION, true => CASCADE, null => SET NULL
	 */
	public function __construct($key, Table $table, Table $referenceTable, $onDelete = true, $onUpdate = false)
	{
		$column = $table->addColumn($key)
			->setType($referenceTable->primaryKey->column);

		if ($onDelete === null) {
			$column->setDefault(null);
		} else {
			$column->setDefault();
		}
		$this->column = $column;

		$table->addKey($key);

		$this->name = 'fk_' . $table->name . '_' . $key . '_' . $referenceTable->name . '_' . $referenceTable->primaryKey->name;
		$this->key = $key;
		$this->table = $table;
		$this->referenceTable = $referenceTable;
		$this->onDelete = $onDelete;
		$this->onUpdate = $onUpdate;
	}

	/**
	 * @return string
	 */
	protected function getName()
	{
		return $this->name;
	}

	/**
	 * @return Column
	 */
	protected function getColumn()
	{
		return $this->column;
	}

	/**
	 * Vrati hodnotu pro zmenu
	 * @param mixed $value
	 * @return string
	 */
	private function prepareOnChange($value)
	{
		if ($value === false) {
			return 'NO ACTION';
		} elseif ($value === null) {
			return 'SET NULL';
		} else {
			return 'CASCADE';
		}
	}

	/**
	 * @param Row $row
	 * @return bool
	 */
	public function equals(Row $row)
	{
		$constrait = $this->prepare(
			$row->CONSTRAINT_NAME,
			$row->COLUMN_NAME,
			$row->REFERENCED_TABLE_NAME,
			$row->REFERENCED_COLUMN_NAME,
			$row->DELETE_RULE,
			$row->UPDATE_RULE
		);
		return $constrait == $this->__toString();
	}

	/**
	 * @param string $name
	 * @param string $key
	 * @param string $referenceTable
	 * @param string $referenceKey
	 * @param string $onDelete
	 * @param string $onUpdate
	 * @return string
	 */
	private function prepare($name, $key, $referenceTable, $referenceKey, $onDelete, $onUpdate)
	{
		return "CONSTRAINT [$name] FOREIGN KEY ([$key]) REFERENCES [$referenceTable] ([$referenceKey]) ON DELETE $onDelete ON UPDATE $onUpdate";
	}

	public function __toString()
	{
		return $this->prepare(
			$this->name,
			$this->key,
			$this->referenceTable->name,
			$this->referenceTable->primaryKey->name,
			$this->prepareOnChange($this->onDelete),
			$this->prepareOnChange($this->onUpdate)
		);
	}
}