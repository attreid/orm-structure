# Creating a MySQL structure for Nextras Orm

Settings **config.neon**
```neon
extensions:
	structure: Attreid\OrmStructure\DI\StructureExtension

structure:
	autoManageDb: true
```

## Table Mapper
```php
class ExampleMapper extends \Attreid\OrmStructure\TableMapper {

    public function createTable(\Attreid\Orm\Structure\Table $table) {
        $table->setDefaultDataFile(__DIR__.'/import.sql');
        
        $table->addPrimaryKey('id')
                ->int()
                ->setAutoIncrement();
        $table->addForeignKey('some_id', SomeMapper::class);
        $table->addForeignKey('parent_id', $table)
                ->setDefault(NULL);
        $table->addColumn('pa')
                ->varChar(20);
        $table->addColumn('allowed')
                ->boolean()
                ->setDefault(1)
                ->setKey();
        $table->addUnique('some_id', 'parent_id');
        $table->addFulltext('pa');

        $relationTable = $table->createRelationTable(OtherMapper::class);
        $relationTable->addForeignKey('example_id', $table);
        $relationTable->addForeignKey('other_id', OtherMapper::class);
        $relationTable->setPrimaryKey('example_id', 'other_id');

        // migration 
        if (!$relationTable->exists) {
            $table->migration[] = function (Row $row, Connection $connection) use ($relationTable) {
                if (isset($row->oldColumnId)) {
                    $connection->query('INSERT INTO %table %values', $relationTable->name, [
                        'exampleId' => $row->id,
                        'otherId' => $row->oldColumnId
                    ]);
                }
            };
        }
        
        $table->addOnCreate([
			[
                'id' => 1,
                'some_id' => 1,
                'parent_id' => 1,
                'pa' => 'test',
                // ...
			]
		]);
    }
}
```

## View Mapper
```php
class ExampleMapper extends \Attreid\OrmStructure\ViewMapper {

   	public function createDefinition(QueryBuilder $builder): void
	{
		$builder
			->addSelect('ROW_NUMBER() OVER (ORDER BY some_row) id')
			->addSelect('another_row')
			->from('some_table', 't');
	}

	protected function getPrimaryKey(): array
	{
		return ['id'];
	}
	
	protected function getMapping(): array
	{
	    return [];
	    
	    // OR 
	    
		return [
			'another' => 'currency_row'
		];
	}
}
```