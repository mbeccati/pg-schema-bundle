<?php

namespace Beccati\PgSchemaBundle\Manager\Filter;


class Unlogged implements FilterInterface
{
    public function filter($sql)
    {
        return preg_replace('/CREATE TABLE/i', 'CREATE UNLOGGED TABLE', $sql);
    }
}