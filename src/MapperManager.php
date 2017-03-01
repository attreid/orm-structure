<?php

declare(strict_types = 1);

namespace NAttreid\Orm;

use NAttreid\Orm\Structure\ITableFactory;
use NAttreid\Utils\Hasher;
use Nette\SmartObject;

/**
 * Class MapperManager
 *
 * @property-read bool $useCamelCase
 * @property-read bool $autoManageDb
 * @property-read ITableFactory $tableFactory
 * @property-read Hasher $hasher
 *
 * @author Attreid <attreid@gmail.com>
 */
class MapperManager
{
	use SmartObject;

	/** @var bool */
	private $useCamelCase;

	/** @var bool */
	private $autoManageDb;

	/** @var ITableFactory */
	private $tableFactory;

	/** @var Hasher */
	private $hasher;

	public function __construct(bool $useCamelCase, bool $autoManageDb, ITableFactory $tableFactory, Hasher $hasher = null)
	{
		$this->useCamelCase = $useCamelCase;
		$this->autoManageDb = $autoManageDb;
		$this->tableFactory = $tableFactory;
		$this->hasher = $hasher;
	}

	/**
	 * @return bool
	 */
	protected function isUseCamelCase(): bool
	{
		return $this->useCamelCase;
	}

	/**
	 * @return bool
	 */
	protected function isAutoManageDb(): bool
	{
		return $this->autoManageDb;
	}

	/**
	 * @return ITableFactory
	 */
	protected function getTableFactory(): ITableFactory
	{
		return $this->tableFactory;
	}

	/**
	 * @return Hasher
	 */
	protected function getHasher(): Hasher
	{
		return $this->hasher;
	}


}
