<?php

declare(strict_types=1);

namespace NAttreid\Orm\DI;

use NAttreid\Orm\MapperManager;
use NAttreid\Orm\Structure\ITableFactory;
use NAttreid\Orm\Structure\Table;
use Nette\Schema\Expect;
use Nette\Schema\Schema;
use Nextras\Orm\Model\Model;

class OrmExtension extends \Nextras\Orm\Bridges\NetteDI\OrmExtension
{
	public function getConfigSchema(): Schema
	{
		return Expect::structure([
			'model' => Expect::string(Model::class),
			'add' => Expect::arrayOf('string')->default([]),
			'useCamelCase' => Expect::bool(true),
			'autoManageDb' => Expect::bool(true)
		]);
	}

	public function loadConfiguration(): void
	{
		$this->builder = $this->getContainerBuilder();

		$this->modelClass = $this->config->model;

		$this->repositoryFinder = new PhpDocRepositoryFinder($this->modelClass, $this->builder, $this, $this->config->add);

		$this->builder->addDefinition($this->prefix('mapperManager'))
			->setType(MapperManager::class)
			->setArguments([
				'useCamelCase' => $this->config->useCamelCase,
				'autoManageDb' => $this->config->autoManageDb
			]);

		$this->builder->addFactoryDefinition($this->prefix('tableFactory'))
			->setImplement(ITableFactory::class)
			->getResultDefinition()
			->setFactory(Table::class);

		$repositories = $this->repositoryFinder->loadConfiguration();

		$this->setupCache();
		$this->setupDependencyProvider();
		$this->setupDbalMapperDependencies();
		$this->setupMetadataParserFactory();

		if ($repositories !== null) {
			$repositoriesConfig = Model::getConfiguration($repositories);
			$this->setupMetadataStorage($repositoriesConfig[2]);
			$this->setupModel($this->modelClass, $repositoriesConfig);
		}
	}

}
