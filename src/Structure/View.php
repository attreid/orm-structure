<?php

declare(strict_types=1);

namespace Attreid\OrmStructure\Structure;

use Nextras\Dbal\Connection;
use Nextras\Dbal\Drivers\Exception\QueryException;
use Nextras\Dbal\QueryBuilder\QueryBuilder;

final class View
{

	private QueryBuilder $queryBuilder;

	public function __construct(
		private readonly string     $name,
		private readonly Connection $connection
	)
	{
		$this->queryBuilder = $connection->createQueryBuilder();
	}

	/** @throws QueryException */
	private function exists(): bool
	{
		$result = $this->connection->query("SHOW TABLES LIKE %s", $this->name)->fetch();
		return (bool)$result;
	}

	public function check(): void
	{
		$this->connection->query('SET foreign_key_checks = 0');
		if ($this->exists()) {
			$this->connection->query('DROP VIEW %table', $this->name);
		}
		$this->create();
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

	public function getQueryBuilder(): QueryBuilder
	{
		return $this->queryBuilder;
	}
}