<?php

declare(strict_types=1);

namespace NAttreid\Orm\Structure;

use InvalidArgumentException;
use Nette\SmartObject;
use Serializable;

/**
 * @property-read string $name
 */
final class Index implements Serializable
{
	use SmartObject;

	const
		UNIQUE = 'UNIQUE',
		FULLTEXT = 'FULLTEXT';

	private string $name;
	private ?string $prefix = null;

	/** @var string[] */
	private array $keys;

	public function __construct(string...$key)
	{
		if (count($key) === 0) {
			throw new InvalidArgumentException;
		}

		$this->name = implode('_', $key);
		$this->keys = $key;
	}

	protected function getName(): string
	{
		return $this->name;
	}

	public function setUnique(): self
	{
		$this->prefix = self::UNIQUE;
		return $this;
	}

	public function setFulltext(): self
	{
		$this->prefix = self::FULLTEXT;
		return $this;
	}

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
		return $key == $this->getDefinition();
	}

	private function prepare(string $name, array $keys, string $prefix = null): string
	{
		$key = '[' . implode('], [', $keys) . ']';
		return ($prefix !== null ? $prefix . ' ' : '') . "KEY [$name] ($key)";
	}

	public function getDefinition(): string
	{
		return $this->prepare(
			$this->name,
			$this->keys,
			$this->prefix
		);
	}

	public function serialize(): string
	{
		return json_encode([
			'name' => $this->name,
			'keys' => $this->keys,
			'prefix' => $this->prefix
		]);
	}

	public function unserialize($serialized): void
	{
		$data = json_decode($serialized);
		$this->name = $data->name;
		$this->keys = $data->keys;
		$this->prefix = $data->prefix;
	}
}