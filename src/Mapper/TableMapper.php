<?php

declare(strict_types=1);

namespace Attreid\OrmStructure;

use Attreid\OrmStructure\Structure\Table;

abstract class TableMapper extends Mapper
{
	abstract public function createTable(Table $table): void;
}