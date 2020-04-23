<?php

declare(strict_types=1);

namespace NAttreid\Orm\Structure;

use Nette\SmartObject;
use Nextras\Dbal\Result\Row;
use Serializable;

/**
 * Class Constrait
 *
 * @property-read string $name
 *
 * @author Attreid <attreid@gmail.com>
 */
class Constrait implements Serializable
{
	use SmartObject;

	/** @var string */
	private $name;

	/** @var string */
	private $key;

	/** @var string */
	private $referenceTable;

	/** @var string */
	private $referenceTablePrimaryKey;

	/** @var bool|mixed */
	private $onDelete;

	/** @var bool|mixed */
	private $onUpdate;

	/**
	 * Constrait constructor.
	 * @param string $key
	 * @param string $tableName
	 * @param string $referenceTable
	 * @param string $referenceTablePrimaryKey
	 * @param mixed $onDelete false => RESTRICT, true => CASCADE, null => SET NULL
	 * @param mixed $onUpdate false => RESTRICT, true => CASCADE, null => SET NULL
	 */
	public function __construct(string $key, string $tableName, string $referenceTable, string $referenceTablePrimaryKey, $onDelete = true, $onUpdate = false)
	{
		$this->name = 'fk_' . $tableName . '_' . $key . '_' . $referenceTable . '_' . $referenceTablePrimaryKey;
		$this->key = $key;
		$this->referenceTable = $referenceTable;
		$this->referenceTablePrimaryKey = $referenceTablePrimaryKey;
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
	 * Vrati hodnotu pro zmenu
	 * @param string $value
	 * @return string
	 */
	private function parseOnChange(string $value): string
	{
		switch ($value) {
			default:
			case 'NO ACTION':
			case 'RESTRICT':
				return 'RESTRICT';

			case 'SET NULL':
				return 'SET NULL';

			case 'CASCADE':
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
		return $constrait == $this->getDefinition();
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
		return "CONSTRAINT [$name] FOREIGN KEY ([$key]) REFERENCES [$referenceTable] ([$referenceKey]) ON DELETE {$this->parseOnChange($onDelete)} ON UPDATE {$this->parseOnChange($onUpdate)}";
	}

	public function getDefinition(): string
	{
		return $this->prepare(
			$this->name,
			$this->key,
			$this->referenceTable,
			$this->referenceTablePrimaryKey,
			$this->prepareOnChange($this->onDelete),
			$this->prepareOnChange($this->onUpdate)
		);
	}

	public function serialize(): string
	{
		return json_encode([
			'name' => $this->name,
			'key' => $this->key,
			'referenceTable' => $this->referenceTable,
			'referenceTablePrimaryKey' => $this->referenceTablePrimaryKey,
			'onDelete' => $this->onDelete,
			'onUpdate' => $this->onUpdate
		]);
	}

	public function unserialize($serialized): void
	{
		$data = json_decode($serialized);
		$this->name = $data->name;
		$this->key = $data->key;
		$this->referenceTable = $data->referenceTable;
		$this->referenceTablePrimaryKey = $data->referenceTablePrimaryKey;
		$this->onDelete = $data->onDelete;
		$this->onUpdate = $data->onUpdate;
	}
}