<?php

declare(strict_types=1);

namespace NAttreid\Orm\Structure;

use Nette\SmartObject;
use Serializable;

/**
 * Class Key
 *
 * @property string $name
 * @property-read string[] $columns
 * @property string $type
 * @property bool $unique
 *
 * @author Attreid <attreid@gmail.com>
 */
class Key implements Serializable
{
	use SmartObject;

	/** @var string */
	private $name;

	/** @var string[] */
	private $columns;

	/** @var string */
	private $type;

	/** @var bool */
	private $unique;

	/**
	 * @return string
	 */
	protected function getName(): string
	{
		return $this->name;
	}

	/**
	 * @param string $name
	 */
	protected function setName(string $name): void
	{
		$this->name = $name;
	}

	/**
	 * @return string[]
	 */
	protected function getColumns(): array
	{
		return $this->columns;
	}

	/**
	 * @param int $index
	 * @param string $name
	 */
	public function addColumn(int $index, string $name): void
	{
		$this->columns[$index] = $name;
	}

	/**
	 * @return string
	 */
	protected function getType(): string
	{
		return $this->type;
	}

	/**
	 * @param string $type
	 */
	protected function setType(string $type): void
	{
		$this->type = $type;
	}

	/**
	 * @return bool
	 */
	protected function isUnique(): bool
	{
		return $this->unique;
	}

	/**
	 * @param bool $unique
	 */
	protected function setUnique(bool $unique): void
	{
		$this->unique = $unique;
	}

	public function serialize(): string
	{
		return json_encode([
			'name' => $this->name,
			'columns' => $this->columns,
			'type' => $this->type,
			'unique' => $this->unique
		]);
	}

	public function unserialize($serialized): void
	{
		$data = json_decode($serialized);
		$this->name = $data->name;
		$this->columns = $data->columns;
		$this->type = $data->type;
		$this->unique = $data->unique;
	}
}