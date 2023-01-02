<?php

namespace Beccati\PgSchemaBundle\Manager\Filter;

class CreateFunction implements FilterInterface
{
    public function filter(string $sql): string
    {
        return preg_replace('/^CREATE FUNCTION/im', 'CREATE OR REPLACE FUNCTION', $sql);
    }
}