<?php

namespace Beccati\PgSchemaBundle\Model;


class DateRange extends DateTimeRange
{
    protected function output($value)
    {
        /** @var \DateTime $value */
        return $value->format('Y-m-d');
    }
}