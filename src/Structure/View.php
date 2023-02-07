<?php

declare(strict_types=1);

namespace Attreid\OrmStructure\Structure;

use Nextras\Dbal\Connection;
use Nextras\Dbal\Drivers\Exception\QueryException;
use Nextras\Dbal\QueryBuilder\QueryBuilder;
use Nextras\Dbal\SqlProcessor;

final class View
{

	private QueryBuilder $queryBuilder;
	private string $database;

	public function __construct(
		private readonly string     $name,
		private readonly Connection $connection
	)
	{
		$this->database = $connection->getConfig()['database'];
	}

	/** @throws QueryException */
	public function exists(string $table): bool
	{
		$result = $this->connection->query("SHOW TABLES LIKE %s WHERE TABLE_TYPE LIKE '%VIEW%'", $table)->fetch();
		return (bool)$result;
	}

	public function check(): void
	{
		$this->connection->query('SET foreign_key_checks = 0');
		if (!$this->exists()) {
			$this->create();
		} else {
			$this->modifyView();
		}
		$this->connection->query('SET foreign_key_checks = 1');
	}

	private function create(): void
	{
		$query = "CREATE VIEW %table AS ";
		$args = $this->queryBuilder->getQueryParameters();
		array_unshift($args, $this->name);

		$this->connection->queryArgs(
			$query . $this->queryBuilder->getQuerySql(),
			$args
		);
	}

	public function addQuery(QueryBuilder $queryBuilder): void
	{
		$this->queryBuilder = $queryBuilder;
	}

	private function getViewDefinition(): ?string
	{
		return $this->connection->query('
			SELECT  
			    [VIEW_DEFINITION] def
			FROM [information_schema.VIEWS]
			WHERE 
				[TABLE_SCHEMA] = %s AND
				[TABLE_NAME] = %s',
			$this->database,
			$this->name
		)->fetch()?->def;
	}

	private function getDefinition(): string
	{
		$args = $this->queryBuilder->getQueryParameters();
		array_unshift($args, $this->queryBuilder->getQuerySql());

		$processor = new SqlProcessor($this->connection->getDriver(), $this->connection->getPlatform());
		return $processor->process($args);
	}

	private function modifyView(): void
	{
		$viewDefinition = $this->getViewDefinition();
		$definition = $this->getDefinition();

		if ($viewDefinition !== $definition) {
			$this->connection->query('DROP VIEW %table', $this->name);
			$this->create();
		}
	}
}