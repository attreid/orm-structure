<?php

declare(strict_types=1);

namespace NAttreid\Orm;

use Nextras\Orm\Mapper\IMapper;
use Nextras\Orm\Repository\IDependencyProvider;

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

	public function fetchPairsByName(): array
	{
		return $this->findAll()->resetOrderBy()->orderBy('name')->fetchPairs('id', 'name');
	}

	public function fetchPairsById(): array
	{
		return $this->findAll()->resetOrderBy()->orderBy('id')->fetchPairs('id', 'name');
	}

	public function isEmpty(): bool
	{
		return $this->findAll()->count() == 0;
	}
}
