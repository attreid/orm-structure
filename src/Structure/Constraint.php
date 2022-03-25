<?php

declare(strict_types=1);

namespace Attreid\OrmStructure\Structure;

use Nette\SmartObject;
use Nextras\Dbal\Result\Row;

/**
 * @property-read string $name
 */
final class Constraint
{
	use SmartObject;

	private string $name;
	private string $key;
	private string $referenceTable;
	private string $referenceTablePrimaryKey;
	private ?bool $onDelete;
	private ?bool $onUpdate;

	/**
	 * @param mixed $onDelete false => RESTRICT, true => CASCADE, null => SET NULL
	 * @param mixed $onUpdate false => RESTRICT, true => CASCADE, null => SET NULL
	 */
	public function __construct(string $key, string $tableName, string $referenceTable, string $referenceTablePrimaryKey, ?bool $onDelete = true, ?bool $onUpdate = false)
	{
		$this->name = 'fk_' . $this->prepareTableName($tableName) . '_' . $key . '_' . $this->prepareTableName($referenceTable) . '_' . $referenceTablePrimaryKey;
		$this->key = $key;
		$this->referenceTable = $referenceTable;
		$this->referenceTablePrimaryKey = $referenceTablePrimaryKey;
		$this->onDelete = $onDelete;
		$this->onUpdate = $onUpdate;
	}

	protected function getName(): string
	{
		return $this->name;
	}

	public function setIdentifier(string $name): self
	{
		$this->name = $name;
		return $this;
	}

	private function prepareOnChange(?bool $value): string
	{
		if ($value === false) {
			return 'RESTRICT';
		} elseif ($value === null) {
			return 'SET NULL';
		} else {
			return 'CASCADE';
		}
	}

	private function parseOnChange(string $value): string
	{
		return match ($value) {
			default => 'RESTRICT',
			'NO ACTION', 'RESTRICT' => 'RESTRICT',
			'SET NULL' => 'SET NULL',
			'CASCADE' => 'CASCADE'
		};
	}

	public function equals(Row $row): bool
	{
		$constraint = $this->prepare(
			$row->CONSTRAINT_NAME,
			$row->COLUMN_NAME,
			$row->REFERENCED_TABLE_NAME,
			$row->REFERENCED_COLUMN_NAME,
			$row->DELETE_RULE,
			$row->UPDATE_RULE
		);
		return $constraint == $this->getDefinition();
	}

	private function prepareTableName(string $table): string
	{
		$result = '';
		$arr = preg_split('/(?=[A-Z|_])/', $table, -1, PREG_SPLIT_NO_EMPTY);
		foreach ($arr as $item) {
			$result .= substr(str_replace('_', '', $item), 0, 2);
		}
		return $result;
	}

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

	public function __serialize(): array
	{
		return [
			'name' => $this->name,
			'key' => $this->key,
			'referenceTable' => $this->referenceTable,
			'referenceTablePrimaryKey' => $this->referenceTablePrimaryKey,
			'onDelete' => $this->onDelete,
			'onUpdate' => $this->onUpdate
		];
	}

	public function __unserialize(array $data): void
	{
		$this->name = $data['name'];
		$this->key = $data['key'];
		$this->referenceTable = $data['referenceTable'];
		$this->referenceTablePrimaryKey = $data['referenceTablePrimaryKey'];
		$this->onDelete = $data['onDelete'];
		$this->onUpdate = $data['onUpdate'];
	}
}