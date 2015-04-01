<?php

namespace Beccati\PgSchemaBundle;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;
use Symfony\Component\HttpKernel\Bundle\Bundle;

class BeccatiPgSchemaBundle extends Bundle
{
    static private $types = [
        'tsrange' => 'TimestampRange',
        'tstzrange' => 'TimestampTzRange',
        'daterange' => 'DateRange',
    ];

    public function boot()
    {
        /** @var AbstractPlatform $platform */
        foreach (self::$types as $type => $class) {
            if (!Type::hasType($type)) {
                if (!isset($platform)) {
                    $platform = $this->container
                        ->get('doctrine.orm.default_entity_manager')
                        ->getConnection()
                        ->getDatabasePlatform();
                }

                Type::addType($type, 'Beccati\PgSchemaBundle\Doctrine\Types\\'.$class);
                $platform->registerDoctrineTypeMapping($class, $type);
            }
        }
    }
}
