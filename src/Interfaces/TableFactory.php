<?php

declare(strict_types=1);

namespace Attreid\OrmStructure\Interfaces;

use Attreid\OrmStructure\Structure\Table;

interface TableFactory
{
	public function create(string $name): Table;
}