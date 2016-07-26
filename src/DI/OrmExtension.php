<?php

namespace Nattreid\Orm\DI;

use Nextras\Orm\Entity\Reflection\MetadataParserFactory,
    Nextras\Orm\InvalidStateException,
    Nextras\Orm\Model\Model,
    NAttreid\Orm\Structure\Table,
    NAttreid\Orm\Structure\ITableFactory;

/**
 * Rozsireni orm
 *
 * @author Attreid <attreid@gmail.com>
 */
class OrmExtension extends \Nextras\Orm\Bridges\NetteDI\OrmExtension {

    public function loadConfiguration() {
        $configDefaults = [
            'metadataParserFactory' => MetadataParserFactory::class,
        ];

        $builder = $this->getContainerBuilder();

        $config = $this->getConfig($configDefaults);
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

}
