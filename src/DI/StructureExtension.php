<?php

declare(strict_types=1);

namespace Attreid\OrmStructure\DI;

use Attreid\OrmStructure\Interfaces\TableFactory;
use Attreid\OrmStructure\Interfaces\ViewFactory;
use Attreid\OrmStructure\Mapper;
use Attreid\OrmStructure\Structure;
use Attreid\OrmStructure\Structure\Table;
use Attreid\OrmStructure\Structure\View;
use Nette\DI\CompilerExtension;
use Nette\PhpGenerator\ClassType;
use Nette\Schema\Expect;
use Nette\Schema\Schema;

final class StructureExtension extends CompilerExtension
{
	public function getConfigSchema(): Schema
	{
		return Expect::structure([
			'autoManageDb' => Expect::bool(true)
		]);
	}

	public function loadConfiguration(): void
	{
		$builder = $this->getContainerBuilder();

		$builder->addDefinition($this->prefix('structure'))
			->setType(Structure::class)
			->setArguments([
				'autoManageDb' => $this->config->autoManageDb
			]);

		$builder->addFactoryDefinition($this->prefix('tableFactory'))
			->setImplement(TableFactory::class)
			->getResultDefinition()
			->setFactory(Table::class);

		$builder->addFactoryDefinition($this->prefix('viewFactory'))
			->setImplement(ViewFactory::class)
			->getResultDefinition()
			->setFactory(View::class);
	}

	public function beforeCompile(): void
	{
		$builder = $this->getContainerBuilder();

		$structure = $builder->getDefinition($this->prefix('structure'));
		foreach ($builder->findByType(Mapper::class) as $mapper) {
			$structure->addSetup('addMapper', [$mapper]);
		}
	}

	public function afterCompile(ClassType $class): void
	{
		$initialize = $class->getMethod('initialize');
		$initialize->addBody('$this->getService(?)->run();', [$this->prefix('structure')]);
	}
}
