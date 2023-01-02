<?php

namespace Beccati\PgSchemaBundle\Manager\Filter;

class Unlogged implements FilterInterface
{
    public function filter(string $sql): string
    {
        return preg_replace('/CREATE TABLE/i', 'CREATE UNLOGGED TABLE', $sql);
    }
}