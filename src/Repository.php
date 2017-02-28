<?php

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

	protected function init()
	{

	}

	/**
	 * Vrati pole [id => name] serazene podle [name]
	 * @return array
	 */
	public function fetchPairsByName()
	{
		return $this->findAll()->orderBy('name')->fetchPairs('id', 'name');
	}

	/**
	 * Vrati pole [id => name] serazene podle [id]
	 * @return array
	 */
	public function fetchPairsById()
	{
		return $this->findAll()->orderBy('id')->fetchPairs('id', 'name');
	}

	/**
	 * Je tabulka prazdna?
	 * @return boolean
	 */
	public function isEmpty()
	{
		return $this->findAll()->count() == 0;
	}

}
