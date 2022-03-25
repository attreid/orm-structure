<?php

declare(strict_types=1);

namespace Attreid\Orm\Structure;

use Nette\SmartObject;

/**
 * @property string $name
 * @property-read string[] $columns
 * @property string $type
 * @property bool $unique
 */
final class Key
{
	use SmartObject;

	private string $name;
	/** @var string[] */
	private array $columns;
	private string $type;
	private bool $unique;

	protected function getName(): string
	{
		return $this->name;
	}

	protected function setName(string $name): void
	{
		$this->name = $name;
	}

	/** @return string[] */
	protected function getColumns(): array
	{
		return $this->columns;
	}

	public function addColumn(int $index, string $name): void
	{
		$this->columns[$index] = $name;
	}

	protected function getType(): string
	{
		return $this->type;
	}

	protected function setType(string $type): void
	{
		$this->type = $type;
	}

	protected function isUnique(): bool
	{
		return $this->unique;
	}

	protected function setUnique(bool $unique): void
	{
		$this->unique = $unique;
	}

	public function __serialize(): array
	{
		return [
			'name' => $this->name,
			'columns' => $this->columns,
			'type' => $this->type,
			'unique' => $this->unique
		];
	}

	public function __unserialize(array $data): void
	{
		$this->name = $data['name'];
		$this->columns = $data['columns'];
		$this->type = $data['type'];
		$this->unique = $data['unique'];
	}
}