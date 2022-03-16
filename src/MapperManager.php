<?php

declare(strict_types=1);

namespace NAttreid\Orm;

use NAttreid\Orm\Structure\ITableFactory;
use Nette\SmartObject;

/**
 * @property-read bool $useCamelCase
 * @property-read bool $autoManageDb
 * @property-read ITableFactory $tableFactory
 */
final class MapperManager
{
	use SmartObject;

	private bool $useCamelCase;
	private bool $autoManageDb;
	private ITableFactory $tableFactory;

	public function __construct(bool $useCamelCase, bool $autoManageDb, ITableFactory $tableFactory)
	{
		$this->useCamelCase = $useCamelCase;
		$this->autoManageDb = $autoManageDb;
		$this->tableFactory = $tableFactory;
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
}
