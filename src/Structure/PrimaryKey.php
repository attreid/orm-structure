<?php

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

	public function __construct(Table $table, ...$key)
	{
		if (count($key) === 0) {
			throw new InvalidArgumentException;
		}

		$this->table = $table;
		$this->keys = $key;
	}

	/**
	 * @return string
	 */
	protected function getName()
	{
		return $this->keys[0];
	}

	/**
	 * @return Column
	 */
	protected function getColumn()
	{
		return $this->table->columns[$this->name];
	}

	public function __toString()
	{
		return 'PRIMARY KEY([' . implode('], [', $this->keys) . '])';
	}
}