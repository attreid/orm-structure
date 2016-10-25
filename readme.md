# Rozšíření Nextras/ORM

Nastavení **config.neon**
```neon
extensions:
    dbal: Nextras\Dbal\Bridges\NetteDI\DbalExtension
    orm: Nattreid\Orm\DI\OrmExtension

dbal:
    dbal:
    driver: mysqli
    host: 127.0.0.1
    database: test
    username: test
    password: test

orm:
    model: App\Model\Orm
    add:
        - Another\Orm
```

## Model
```php
namespace App\Model;

/**
 * @property-read ExampleRepository $example
 */
class Orm extends \Nextras\Orm\Model\Model {
    
}
```

## Repository
```php
class ExampleRepository extends \NAttreid\Orm\Repository {

    public static function getEntityClassNames() {
        return [Example::class];
    }
}
```

## Mapper
```php
class ExampleMapper extends \NAttreid\Orm\Mapper {

    protected function createTable(\NAttreid\Orm\Structure\Table $table) {
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
        $table->setUnique('someId', 'parentId');

        $relationTable = $table->createRelationTable(OtherMapper::class);
        $relationTable->addForeignKey('exampleId', $table);
        $relationTable->addForeignKey('otherId', OtherMapper::class);
        $relationTable->setPrimaryKey('exampleId', 'otherId');
    }
}
```

## Entity
```php
/**
 * @property int $id {primary}
 * @property Some $some {m:1 Some, oneSided=true}
 * @property Example|NULL $parent {m:1 Example::$children}
 * @property OneHasMany|Example[] $children {1:m Example::$parent, orderBy=id}
 * @property string $pa
 * @property boolean $allowed {default TRUE}
 */
class Example extends \Nextras\Orm\Entity\Entity {

}
```
Dokumentace na adrese https://nextras.org/orm