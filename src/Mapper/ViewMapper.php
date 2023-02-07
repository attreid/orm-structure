<?php

declare(strict_types=1);

namespace Attreid\OrmStructure;

use Nette\Caching\Cache;
use Nextras\Dbal\IConnection;
use Nextras\Dbal\QueryBuilder\QueryBuilder;
use Nextras\Orm\Entity\Reflection\EntityMetadata;
use Nextras\Orm\Mapper\Dbal\Conventions\Conventions;
use Nextras\Orm\Mapper\Dbal\Conventions\IConventions;
use Nextras\Orm\Mapper\Dbal\Conventions\Inflector\IInflector;

abstract class ViewMapper extends Mapper
{
	abstract public function createDefinition(QueryBuilder $builder): void;

	abstract protected function getPrimaryKey(): array;

	protected function createConventions(): IConventions
	{
		return new class(
			$this->createInflector(),
			$this->connection,
			$this->getTableName(),
			$this->getRepository()->getEntityMetadata(),
			$this->cache,
			$this->getPrimaryKey()
		) extends Conventions {
			public function __construct(IInflector $inflector, IConnection $connection, string $storageName, EntityMetadata $entityMetadata, Cache $cache, private readonly array $primaryKey)
			{
				parent::__construct($inflector, $connection, $storageName, $entityMetadata, $cache);
			}

			public function getStoragePrimaryKey(): array
			{
				return $this->primaryKey;
			}

			protected function getDefaultMappings(): array
			{
				return [];
			}

			protected function getDefaultModifiers(): array
			{
				return [];
			}
		};
	}
}