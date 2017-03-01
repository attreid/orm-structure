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

	public function __construct($useCamelCase, $autoManageDb, ITableFactory $tableFactory, Hasher $hasher = null)
	{
		$this->useCamelCase = (bool)$useCamelCase;
		$this->autoManageDb = (bool)$autoManageDb;
		$this->tableFactory = $tableFactory;
		$this->hasher = $hasher;
	}

	/**
	 * @return bool
	 */
	protected function isUseCamelCase()
	{
		return $this->useCamelCase;
	}

	/**
	 * @return bool
	 */
	protected function isAutoManageDb()
	{
		return $this->autoManageDb;
	}

	/**
	 * @return ITableFactory
	 */
	protected function getTableFactory()
	{
		return $this->tableFactory;
	}

	/**
	 * @return Hasher
	 */
	protected function getHasher()
	{
		return $this->hasher;
	}


}
