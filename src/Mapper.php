<?php

declare(strict_types=1);

namespace Attreid\OrmStructure;

use Attreid\OrmStructure\Structure\Table;
use Nextras\Orm\Exception\InvalidStateException;
use Nextras\Orm\Mapper\Dbal\Conventions\Inflector\CamelCaseInflector;
use Nextras\Orm\Mapper\Dbal\Conventions\Inflector\IInflector;
use Nextras\Orm\Mapper\Dbal\Conventions\Inflector\SnakeCaseInflector;
use Nextras\Orm\StorageReflection\StringHelper;
use ReflectionClass;

abstract class Mapper extends \Nextras\Orm\Mapper\Mapper
{
	abstract public function createTable(Table $table): void;

	public function getTableName(): string
	{
		if ($this->tableName === null) {
			$tableName = str_replace('Mapper', '', (new ReflectionClass(static::class))->getShortName());
			$inflector = $this->createInflector();
			if ($inflector instanceof CamelCaseInflector) {
				$tableName = lcfirst($tableName);
			} elseif ($inflector instanceof SnakeCaseInflector) {
				$tableName = StringHelper::underscore($tableName);
			} else {
				throw new InvalidStateException("Unknown Inflector '" . $inflector::class . "'");
			}
			$this->tableName = $this->getTablePrefix() . $tableName;
		}
		return $this->tableName;
	}

	protected function getTablePrefix(): string
	{
		return '';
	}
}