<?php

declare(strict_types=1);

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
	 * @param mixed $onDelete false => RESTRICT, true => CASCADE, null => SET NULL
	 * @param mixed $onUpdate false => RESTRICT, true => CASCADE, null => SET NULL
	 */
	public function __construct(string $key, Table $table, Table $referenceTable, $onDelete = true, $onUpdate = false)
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
	protected function getName(): string
	{
		return $this->name;
	}

	/**
	 * @return Column
	 */
	protected function getColumn(): Column
	{
		return $this->column;
	}

	/**
	 * Vrati hodnotu pro zmenu
	 * @param mixed $value
	 * @return string
	 */
	private function prepareOnChange($value): string
	{
		if ($value === false) {
			return 'RESTRICT';
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
	public function equals(Row $row): bool
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
	private function prepare(string $name, string $key, string $referenceTable, string $referenceKey, string $onDelete, string $onUpdate): string
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