<?php

namespace NAttreid\Orm\Structure;

use InvalidArgumentException;
use Nette\SmartObject;
use stdClass;

/**
 * Class Index
 *
 * @property-read string $name
 *
 * @author Attreid <attreid@gmail.com>
 */
class Index
{
	use SmartObject;

	const
		UNIQUE = 'UNIQUE',
		FULLTEXT = 'FULLTEXT';

	/** @var string */
	private $name;

	/** @var string[] */
	private $keys;

	/** @var string */
	private $prefix;

	public function __construct(...$key)
	{
		if (count($key) === 0) {
			throw new InvalidArgumentException;
		}

		$this->name = implode('_', $key);
		$this->keys = $key;
	}

	/**
	 * @return string
	 */
	protected function getName()
	{
		return $this->name;
	}

	/**
	 * Nastavi hodnotu sloupce na unikatni
	 * @return self
	 */
	public function setUnique()
	{
		$this->prefix = self::UNIQUE;
		return $this;
	}

	/**
	 * Nastavi typ na fulltext
	 * @return self
	 */
	public function setFulltext()
	{
		$this->prefix = self::FULLTEXT;
		return $this;
	}

	/**
	 * @param stdClass[] $row
	 * @return bool
	 */
	public function equals($row)
	{
		$columns = ksort($row->columns);
		$prefix = null;
		if ($row->unique) {
			$prefix = self::UNIQUE;
		} elseif ($row->type == 'FULLTEXT') {
			$prefix = self::FULLTEXT;
		}

		$key = $this->prepare(
			$row->name,
			$columns,
			$prefix
		);
		return $key == $this->__toString();
	}

	/**
	 * @param string $name
	 * @param array $keys
	 * @param string $prefix
	 * @return string
	 */
	private function prepare($name, array $keys, $prefix = null)
	{
		$key = '[' . implode('], [', $keys) . ']';
		return ($prefix !== null ? $prefix . ' ' : '') . "KEY [$name] ($key)";
	}

	public function __toString()
	{
		return $this->prepare(
			$this->name,
			$this->key,
			$this->prefix
		);
	}
}