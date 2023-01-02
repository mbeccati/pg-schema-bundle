<?php

namespace Beccati\PgSchemaBundle\Manager\Filter;

interface FilterInterface
{
    /**
     * An SQL filter interface
     *
     * @param string $str Text to filter
     * @return string Filtered text
     */
    public function filter($sql);
}