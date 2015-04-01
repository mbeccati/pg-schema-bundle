<?php

namespace Beccati\PgSchemaBundle\Model;


class DateTimeRange extends AbstractRange
{
    protected function input($value)
    {
        return new \DateTime(trim($value, '"'));
    }

    protected function output($value)
    {
        /** @var \DateTime $value */
        return $value->format('Y-m-d H:i:s');
    }
}