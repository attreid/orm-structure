<?php

declare(strict_types = 1);

namespace Nattreid\Orm\DI;

use NAttreid\Orm\MapperManager;
use NAttreid\Orm\Structure\ITableFactory;
use NAttreid\Orm\Structure\Table;
use Nextras\Orm\Entity\Reflection\MetadataParserFactory;
use Nextras\Orm\InvalidStateException;
use Nextras\Orm\Model\Model;

/**
 * Rozsireni orm
 *
 * @author Attreid <attreid@gmail.com>
 */
class OrmExtension extends \Nextras\Orm\Bridges\NetteDI\OrmExtension
{

	private $defaults = [
		'metadataParserFactory' => MetadataParserFactory::class,
		'useCamelCase' => true,
		'model' => null,
		'add' => [],
		'autoManageDb' => true
	];

	public function loadConfiguration()
	{
		$config = $this->validateConfig($this->defaults, $this->getConfig());

		$builder = $this->getContainerBuilder();;
		if ($config['model'] === null) {
			throw new InvalidStateException('Model is not defined.');
		}

		$repositories = $this->getRepositoryList($config['model']);
		foreach ($config['add'] as $model) {
			$repositories = array_merge($repositories, $this->getRepositoryList($model));
		}

		$builder->addDefinition($this->prefix('mapperManager'))
			->setClass(MapperManager::class)
			->setArguments([
				'useCamelCase' => $config['useCamelCase'],
				'autoManageDb' => $config['autoManageDb']
			]);

		$builder->addDefinition($this->prefix('tableFactory'))
			->setImplement(ITableFactory::class)
			->setFactory(Table::class);

		$repositoriesConfig = Model::getConfiguration($repositories);

		$this->setupCache();
		$this->setupDependencyProvider();
		$this->setupMetadataParserFactory($config['metadataParserFactory']);
		$this->setupRepositoryLoader($repositories);
		$this->setupMetadataStorage($repositoriesConfig);
		$this->setupRepositoriesAndMappers($repositories);
		$this->setupModel($config['model'], $repositoriesConfig);
	}

}
