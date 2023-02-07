<?php

declare(strict_types=1);

namespace Attreid\OrmStructure;

use Attreid\OrmStructure\Structure\View;

abstract class ViewMapper extends Mapper
{
	abstract public function createView(View $view): void;
}