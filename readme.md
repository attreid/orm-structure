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
 * @property-read AclRepository $acl
 */
class Orm extends \Nextras\Orm\Model\Model {
    
}
```

Dokumentace na adrese https://nextras.org/orm