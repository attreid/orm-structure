# MySQL structure creation for Nextras Orm

Settings **config.neon**
```neon
extensions:
	structure: Attreid\OrmStructure\DI\StructureExtension

structure:
	autoManageDb: true
```

## Mapper
```php
class ExampleMapper extends \Attreid\OrmStructure\Mapper {

    public function createTable(\Attreid\Orm\Structure\Table $table) {
        $table->setDefaultDataFile(__DIR__.'/import.sql');
        
        $table->addPrimaryKey('id')
                ->int()
                ->setAutoIncrement();
        $table->addForeignKey('someId', SomeMapper::class);
        $table->addForeignKey('parentId', $table)
                ->setDefault(NULL);
        $table->addColumn('pa')
                ->varChar(20);
        $table->addColumn('allowed')
                ->boolean()
                ->setDefault(1)
                ->setKey();
        $table->addUnique('someId', 'parentId');
        $table->addFulltext('pa');

        $relationTable = $table->createRelationTable(OtherMapper::class);
        $relationTable->addForeignKey('exampleId', $table);
        $relationTable->addForeignKey('otherId', OtherMapper::class);
        $relationTable->setPrimaryKey('exampleId', 'otherId');

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
                'someId' => 1,
                'parentId' => 1,
                'pa' => 'test',
                // ...
			]
		]);
    }
}
```