<?php

declare(strict_types = 1);

namespace NAttreid\Orm\Structure;

use InvalidArgumentException;
use Nette\SmartObject;

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

	public function __construct(string...$key)
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
	protected function getName(): string
	{
		return $this->name;
	}

	/**
	 * Nastavi hodnotu sloupce na unikatni
	 * @return self
	 */
	public function setUnique(): self
	{
		$this->prefix = self::UNIQUE;
		return $this;
	}

	/**
	 * Nastavi typ na fulltext
	 * @return self
	 */
	public function setFulltext(): self
	{
		$this->prefix = self::FULLTEXT;
		return $this;
	}

	/**
	 * @param Key $row
	 * @return bool
	 */
	public function equals(Key $row): bool
	{
		$prefix = null;
		if ($row->unique) {
			$prefix = self::UNIQUE;
		} elseif ($row->type == 'FULLTEXT') {
			$prefix = self::FULLTEXT;
		}

		$key = $this->prepare(
			$row->name,
			$row->columns,
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
	private function prepare(string $name, array $keys, string $prefix = null): string
	{
		$key = '[' . implode('], [', $keys) . ']';
		return ($prefix !== null ? $prefix . ' ' : '') . "KEY [$name] ($key)";
	}

	public function __toString()
	{
		return $this->prepare(
			$this->name,
			$this->keys,
			$this->prefix
		);
	}
}