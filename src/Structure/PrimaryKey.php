<?php

declare(strict_types=1);

namespace NAttreid\Orm\Structure;

use InvalidArgumentException;
use Nette\SmartObject;
use Serializable;

/**
 * @property-read string $name
 */
final class PrimaryKey implements Serializable
{
	use SmartObject;

	/** @var string[] */
	private array $keys;

	public function __construct(string...$key)
	{
		if (count($key) === 0) {
			throw new InvalidArgumentException;
		}

		$this->keys = $key;
	}

	public function equals(array $keys): bool
	{
		return $this->keys === $keys;
	}

	protected function getName(): string
	{
		return $this->keys[0];
	}

	public function getDefinition(): string
	{
		return 'PRIMARY KEY([' . implode('], [', $this->keys) . '])';
	}

	public function serialize(): string
	{
		return json_encode([
			'keys' => $this->keys
		]);
	}

	public function unserialize($serialized): void
	{
		$data = json_decode($serialized);
		$this->keys = $data->keys;
	}
}