<?php

declare(strict_types = 1);

namespace NAttreid\Orm\Structure;

use InvalidArgumentException;
use Nette\SmartObject;

/**
 * Class PrimaryKey
 *
 * @property-read string $name
 * @property-read Column $column
 *
 * @author Attreid <attreid@gmail.com>
 */
class PrimaryKey
{
	use SmartObject;

	/** @var string[] */
	private $keys;

	/** @var Table */
	private $table;

	public function __construct(Table $table, string...$key)
	{
		if (count($key) === 0) {
			throw new InvalidArgumentException;
		}

		$this->table = $table;
		$this->keys = $key;
	}

	/**
	 * @param array $keys
	 * @return bool
	 */
	public function equals(array $keys): bool
	{
		return $this->keys === $keys;
	}

	/**
	 * @return string
	 */
	protected function getName(): string
	{
		return $this->keys[0];
	}

	/**
	 * @return Column
	 */
	protected function getColumn(): Column
	{
		return $this->table->columns[$this->name];
	}

	public function __toString()
	{
		return 'PRIMARY KEY([' . implode('], [', $this->keys) . '])';
	}
}