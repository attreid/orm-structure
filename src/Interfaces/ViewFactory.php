<?php

declare(strict_types=1);

namespace Attreid\OrmStructure\Interfaces;

use Attreid\OrmStructure\Structure\View;

interface ViewFactory
{
	public function create(string $name): View;
}