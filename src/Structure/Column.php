<?php

declare(strict_types=1);

namespace Attreid\OrmStructure\Structure;

use Nextras\Dbal\Result\Row;

final class Column
{
	private string $type;
	private string $default = 'NOT null';

	public function __construct(private readonly string $name, private readonly Table $table)
	{
	}

	private function prepareColumn(Row $row): string
	{
		$nullable = $row->Null === 'YES';

		if ($row->Default === null && !$nullable) {
			$default = ' NOT null';
		} elseif ($row->Default === null && $nullable) {
			$default = ' DEFAULT null';
		} else {
			$default = ($nullable ? '' : ' NOT null') . " DEFAULT " . $this->parseDefault($row->Default);
		}

		if (!empty($row->Collation)) {
			$collate = ' COLLATE ' . $row->Collation;
		} else {
			$collate = '';
		}

		if ($row->Extra === 'auto_increment') {
			$autoIncrement = ' AUTO_INCREMENT';
		} else {
			$autoIncrement = '';
		}

		return "`{$row->Field}` "
			. $row->Type
			. $collate
			. $default
			. $autoIncrement;
	}

	private function parseDefault(string $default): string
	{
		return match ($default) {
			'current_timestamp()' => 'CURRENT_TIMESTAMP',
			default => "'$default'"
		};
	}

	public function bool(): self
	{
		return $this->tinyint(1);
	}

	public function bit(int $size): self
	{
		$this->type = 'bit(' . $size . ')';
		return $this;
	}

	public function tinyint($size = 4): self
	{
		$this->type = 'tinyint(' . $size . ')';
		return $this;
	}

	public function smallint(int $size = 6): self
	{
		$this->type = 'smallint(' . $size . ')';
		return $this;
	}

	public function mediumint(int $size = 8): self
	{
		$this->type = 'mediumint(' . $size . ')';
		return $this;
	}

	public function int(int $size = 11): self
	{
		$this->type = 'int(' . $size . ')';
		return $this;
	}

	public function bigint(int $size = 20): self
	{
		$this->type = 'bigint(' . $size . ')';
		return $this;
	}

	public function decimal(int $total, int $decimal): self
	{
		$this->type = 'decimal(' . $total . ',' . $decimal . ')';
		return $this;
	}

	public function float(int $total, int $decimal): self
	{
		$this->type = 'float(' . $total . ',' . $decimal . ')';
		return $this;
	}

	public function double(int $total, int $decimal): self
	{
		$this->type = 'double(' . $total . ',' . $decimal . ')';
		return $this;
	}

	public function datetime(): self
	{
		$this->type = 'datetime';
		return $this;
	}

	public function date(): self
	{
		$this->type = 'date';
		return $this;
	}

	public function time(): self
	{
		$this->type = 'time';
		return $this;
	}

	public function year($size = 4): self
	{
		$this->type = 'year(' . $size . ')';
		return $this;
	}

	private function stringOptions(?string $charset): string
	{
		return ' ' . ($charset !== null ? 'CHARACTER SET ' . $charset . ' ' : '') . 'COLLATE ' . $this->table->collate;
	}

	public function timestamp(bool $onUpdate = false): self
	{
		$this->type = 'timestamp';
		$this->default = 'NOT null DEFAULT CURRENT_TIMESTAMP' . ($onUpdate ? ' ON UPDATE CURRENT_TIMESTAMP' : '');
		$this->setDefault('CURRENT_TIMESTAMP');
		return $this;
	}

	public function char(int $size = 36, string $charset = null): self
	{
		$this->type = 'char(' . $size . ')' . $this->stringOptions($charset);
		return $this;
	}

	public function varChar(int $size = 255, string $charset = null): self
	{
		$this->type = 'varchar(' . $size . ')' . $this->stringOptions($charset);
		return $this;
	}

	public function binary(int $size = 36): self
	{
		$this->type = 'binary(' . $size . ')';
		return $this;
	}

	public function varbinary(int $size = 255): self
	{
		$this->type = 'varbinary(' . $size . ')';
		return $this;
	}

	public function tinytext(string $charset = null): self
	{
		$this->type = 'tinytext' . $this->stringOptions($charset);
		return $this;
	}

	public function text(string $charset = null): self
	{
		$this->type = 'text' . $this->stringOptions($charset);
		return $this;
	}

	public function mediumtext(string $charset = null): self
	{
		$this->type = 'mediumtext' . $this->stringOptions($charset);
		return $this;
	}

	public function longtext(string $charset = null): self
	{
		$this->type = 'longtext' . $this->stringOptions($charset);
		return $this;
	}

	public function tinyblob(): self
	{
		$this->type = 'tinyblob';
		return $this;
	}

	public function blob(): self
	{
		$this->type = 'blob';
		return $this;
	}

	public function mediumblob(): self
	{
		$this->type = 'mediumblob';
		return $this;
	}

	public function longblob(): self
	{
		$this->type = 'longblob';
		return $this;
	}

	/**
	 * @param mixed $default false => NOT null (default), null => DEFAULT null, other DEFAULT value
	 */
	public function setDefault(mixed $default = false, bool $empty = false): self
	{
		if ($this->type == 'timestamp') {
			return $this;
		}
		if ($default === false) {
			$this->default = 'NOT null';
		} elseif ($default === null) {
			$this->default = 'DEFAULT null';
		} else {
			$this->default = ($empty ? '' : 'NOT null ') . "DEFAULT {$this->table->escapeString((string)$default)}";
		}
		return $this;
	}

	public function setAutoIncrement(): self
	{
		$this->default = 'NOT null AUTO_INCREMENT';
		$this->table->setAutoIncrement(1);
		return $this;
	}

	public function setType(Column $column): self
	{
		$this->type = $column->type;
		return $this;
	}

	public function setUnique(): self
	{
		$this->table->addUnique($this->name);
		return $this;
	}

	public function setKey(): self
	{
		$this->table->addKey($this->name);
		return $this;
	}

	public function setFulltext(): self
	{
		$this->table->addFulltext($this->name);
		return $this;
	}

	public function equals(Row $column): bool
	{
		$col = $this->prepareColumn($column);
		return $col == "`$this->name` $this->type $this->default";
	}

	public function renameFrom(string...$names): self
	{
		foreach ($names as $name) {
			$this->table->addColumnToRename($name, $this);
		}
		return $this;
	}

	public function getDefinition(): string
	{
		return "[$this->name] $this->type $this->default";
	}

	public function __serialize(): array
	{
		return [
			'name' => $this->name,
			'type' => $this->type,
			'default' => $this->default
		];
	}

	public function __unserialize(array $data): void
	{
		$this->name = $data['name'];
		$this->type = $data['type'];
		$this->default = $data['default'];
	}
}
