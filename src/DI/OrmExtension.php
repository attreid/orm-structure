<?php

declare(strict_types=1);

namespace NAttreid\Orm\DI;

use NAttreid\Orm\MapperManager;
use NAttreid\Orm\Structure\ITableFactory;
use NAttreid\Orm\Structure\Table;
use Nextras\Orm\Model\Model;

/**
 * Rozsireni orm
 *
 * @author Attreid <attreid@gmail.com>
 */
class OrmExtension extends \Nextras\Orm\Bridges\NetteDI\OrmExtension
{

	private $defaults = [
		'model' => Model::class,

		'add' => [],
		'useCamelCase' => true,
		'autoManageDb' => true
	];

	public function loadConfiguration(): void
	{
		$this->builder = $this->getContainerBuilder();

		$config = $this->validateConfig($this->defaults, $this->getConfig());
		$this->modelClass = $config['model'];

		$this->repositoryFinder = new PhpDocRepositoryFinder($this->modelClass, $this->builder, $this, $config['add']);

		$this->builder->addDefinition($this->prefix('mapperManager'))
			->setType(MapperManager::class)
			->setArguments([
				'useCamelCase' => $config['useCamelCase'],
				'autoManageDb' => $config['autoManageDb']
			]);

		$this->builder->addDefinition($this->prefix('tableFactory'))
			->setImplement(ITableFactory::class)
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
