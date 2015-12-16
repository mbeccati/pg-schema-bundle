<?php

namespace Beccati\PgSchemaBundle\Model;


abstract class AbstractRange
{
    private $data;
    private $inc;

    private static $start = [
        '[' => true,
        '(' => false,
    ];

    private static $end = [
        ']' => true,
        ')' => false,
    ];

    abstract protected function input($value);
    abstract protected function output($value);

    final public function __construct($value = null)
    {
        if (null === $value) {
            $this->setEmpty();
        } else {
            $this->fromString($value);
        }
    }

    final public function __toString()
    {
        if ($this->isEmpty()) {
            return 'empty';
        }

        return
            ($this->inc[0] ? '[' : '(').
            (null === $this->data[0] ? 'infinity' : $this->output($this->data[0])).
            ','.
            (null === $this->data[1] ? 'infinity' : $this->output($this->data[1])).
            ($this->inc[1] ? ']' : ')');
    }

    final public function getLower()
    {
        return $this->data[0];
    }

    final public function getUpper()
    {
        return $this->data[1];
    }

    final public function isEmpty()
    {
        return null === $this->data[0] && null === $this->data[1];
    }

    final public function isLowerInc()
    {
        return $this->inc[0];
    }

    final public function isUpperInc()
    {
        return $this->inc[1];
    }

    final public function setLower($lower)
    {
        $this->data[0] = $lower;
    }

    final public function setUpper($upper)
    {
        $this->data[1] = $upper;
    }

    final public function setLowerInc($lower)
    {
        $this->inc[0] = $lower;
    }

    final public function setUpperInc($upper)
    {
        $this->inc[1] = $upper;
    }

    final public function fromString($input)
    {
        $len = strlen($input);

        if (0 === $len || 'empty' === $input) {
            $this->setEmpty();
            return;
        }

        if ($len <= 3) {
            throw new \InvalidArgumentException("Malformed input string");
        }

        $parts = explode(',', substr($input, 1, -1));

        if (2 !== count($parts)) {
            throw new \InvalidArgumentException("Malformed input string");
        }

        $ch = $input[0];
        if (!isset(self::$start[$ch])) {
            throw new \InvalidArgumentException("Unrecognized start character '{$ch}'");
        }
        $this->inc[0] = self::$start[$ch];

        $ch = $input[$len - 1];
        if (!isset(self::$end[$ch])) {
            throw new \InvalidArgumentException("Unrecognized end character '{$ch}'");
        }
        $this->inc[1] = self::$end[$ch];

        $this->data[0] = 'infinity' === $parts[0] ? null : $this->input($parts[0]);
        $this->data[1] = 'infinity' === $parts[1] ? null : $this->input($parts[1]);
    }

    final public function setEmpty()
    {
        $this->data = $this->inc = [ null, null ];
    }
}