<?php

declare(strict_types=1);

namespace Attreid\Orm\Structure;

use InvalidArgumentException;
use Nette\SmartObject;

/**
 * @property-read string $name
 */
final class PrimaryKey
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

	public function __serialize(): array
	{
		return [
			'keys' => $this->keys
		];
	}

	public function __unserialize(array $data): void
	{
		$this->keys = $data['keys'];
	}
}