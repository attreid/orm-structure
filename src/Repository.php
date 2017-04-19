<?php

declare(strict_types=1);

namespace NAttreid\Orm;

use Nextras\Orm\Mapper\IMapper;
use Nextras\Orm\Repository\IDependencyProvider;

/**
 * Repository
 *
 * @author Attreid <attreid@gmail.com>
 */
abstract class Repository extends \Nextras\Orm\Repository\Repository
{

	public function __construct(IMapper $mapper, IDependencyProvider $dependencyProvider = null)
	{
		parent::__construct($mapper, $dependencyProvider);
		$this->init();
	}

	protected function init(): void
	{

	}

	/**
	 * Vrati pole [id => name] serazene podle [name]
	 * @return array
	 */
	public function fetchPairsByName(): array
	{
		return $this->findAll()->orderBy('name')->fetchPairs('id', 'name');
	}

	/**
	 * Vrati pole [id => name] serazene podle [id]
	 * @return array
	 */
	public function fetchPairsById(): array
	{
		return $this->findAll()->orderBy('id')->fetchPairs('id', 'name');
	}

	/**
	 * Je tabulka prazdna?
	 * @return bool
	 */
	public function isEmpty(): bool
	{
		return $this->findAll()->count() == 0;
	}

}
