<?php

namespace Beccati\PgSchemaBundle\Manager\Filter;

interface FilterInterface
{
    /**
     * An SQL filter interface
     *
     * @param string $sql Text to filter
     * @return string Filtered text
     */
    public function filter(string $sql): string;
}