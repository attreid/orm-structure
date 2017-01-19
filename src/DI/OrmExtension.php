<?php

namespace Nattreid\Orm\DI;

use NAttreid\Orm\Structure\ITableFactory;
use NAttreid\Orm\Structure\Table;
use Nette\DI\ContainerBuilder;
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
		'useCamelCase' => true
	];

	public function loadConfiguration()
	{
		$config = $this->validateConfig($this->defaults, $this->getConfig());

		$builder = $this->getContainerBuilder();;
		if (!isset($config['model'])) {
			throw new InvalidStateException('Model is not defined.');
		}

		$repositories = $this->getRepositoryList($config['model']);

		if (isset($config['add'])) {
			foreach ($config['add'] as $model) {
				$repositories = array_merge($repositories, $this->getRepositoryList($model));
			}
		}

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

	protected function createMapperService($repositoryName, $repositoryClass, ContainerBuilder $builder)
	{
		$config = $this->validateConfig($this->defaults, $this->getConfig());

		$mapperName = $this->prefix('mappers.' . $repositoryName);
		if (!$builder->hasDefinition($mapperName)) {
			$mapperClass = str_replace('Repository', 'Mapper', $repositoryClass);
			if (!class_exists($mapperClass)) {
				throw new InvalidStateException("Unknown mapper for '{$repositoryName}' repository.");
			}

			$builder->addDefinition($mapperName)
				->setClass($mapperClass)
				->setArguments([
					'useCamelCase' => $config['useCamelCase'],
					'cache' => '@' . $this->prefix('cache'),
				]);
		}

		return $mapperName;
	}

}
