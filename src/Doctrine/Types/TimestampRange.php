<?php

namespace Beccati\PgSchemaBundle\Doctrine\Types;

use Doctrine\DBAL\Types\Type;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Beccati\PgSchemaBundle\Model;

class TimestampRange extends Type
{
    public function getName()
    {
        return 'TimestampRange';
    }

    public function getSqlDeclaration(array $fieldDeclaration, AbstractPlatform $platform)
    {
        return 'tsrange';
    }

    public function convertToPHPValue($value, AbstractPlatform $platform)
    {
        $result = new Model\DateTimeRange();
        $result->fromString($value);

        return $result;
    }

    public function convertToDatabaseValue($value, AbstractPlatform $platform)
    {
        return (string)$value;
    }
}