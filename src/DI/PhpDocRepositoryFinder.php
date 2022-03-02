<?php

declare(strict_types=1);

namespace NAttreid\Orm\DI;

use Nette\DI\ContainerBuilder;
use Nextras\Orm\Bridges\NetteDI\OrmExtension;

final class PhpDocRepositoryFinder extends \Nextras\Orm\Bridges\NetteDI\PhpDocRepositoryFinder
{
	/** @var string[] */
	private array $addModelClasses;

	public function __construct(string $modelClass, ContainerBuilder $containerBuilder, OrmExtension $extension, array $addModelClasses = [])
	{
		parent::__construct($modelClass, $containerBuilder, $extension);
		$this->addModelClasses = $addModelClasses;
	}

	public function loadConfiguration(): array
	{
		$repositories = $this->findRepositories($this->modelClass);
		foreach ($this->addModelClasses as $addModelClass) {
			$repositories = array_merge($repositories, $this->findRepositories($addModelClass));
		}
		$repositoriesMap = [];
		foreach ($repositories as $repositoryName => $repositoryClass) {
			$this->setupMapperService($repositoryName, $repositoryClass);
			$this->setupRepositoryService($repositoryName, $repositoryClass);
			$repositoriesMap[$repositoryClass] = $this->extension->prefix('repositories.' . $repositoryName);
		}

		$this->setupRepositoryLoader($repositoriesMap);
		return $repositories;
	}
}