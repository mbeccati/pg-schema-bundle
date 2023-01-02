<?php

namespace Beccati\PgSchemaBundle\Doctrine\Types;

use Doctrine\DBAL\Types\Type;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Beccati\PgSchemaBundle\Model;

class DateRange extends Type
{
    public function getName(): string
    {
        return 'DateRange';
    }

    public function getSqlDeclaration(array $fieldDeclaration, AbstractPlatform $platform)
    {
        return 'daterange';
    }

    public function convertToPHPValue($value, AbstractPlatform $platform): mixed
    {
        $result = new Model\DateRange();
        $result->fromString($value);

        return $result;
    }

    public function convertToDatabaseValue($value, AbstractPlatform $platform): mixed
    {
        return (string)$value;
    }
}