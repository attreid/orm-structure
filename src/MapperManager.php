<?php

declare(strict_types=1);

namespace NAttreid\Orm;

use NAttreid\Orm\Structure\ITableFactory;
use NAttreid\Utils\Hasher;
use Nette\SmartObject;

/**
 * @property-read bool $useCamelCase
 * @property-read bool $autoManageDb
 * @property-read ITableFactory $tableFactory
 * @property-read Hasher $hasher
 */
class MapperManager
{
	use SmartObject;

	private bool $useCamelCase;
	private bool $autoManageDb;
	private ITableFactory $tableFactory;
	private ?Hasher $hasher;

	public function __construct(bool $useCamelCase, bool $autoManageDb, ITableFactory $tableFactory, Hasher $hasher = null)
	{
		$this->useCamelCase = $useCamelCase;
		$this->autoManageDb = $autoManageDb;
		$this->tableFactory = $tableFactory;
		$this->hasher = $hasher;
	}

	protected function isUseCamelCase(): bool
	{
		return $this->useCamelCase;
	}

	protected function isAutoManageDb(): bool
	{
		return $this->autoManageDb;
	}

	protected function getTableFactory(): ITableFactory
	{
		return $this->tableFactory;
	}

	protected function getHasher(): Hasher
	{
		return $this->hasher;
	}
}
