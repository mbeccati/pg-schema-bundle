<?php

namespace Beccati\PgSchemaBundle\Manager\Filter;


class BackSlashCommands implements FilterInterface
{
    public function __construct($dir)
    {
        $this->dir = $dir;
    }

    public function filter($sql)
    {
        $dir = $this->dir;

        // \i command
        $sql = preg_replace_callback('#^\\\\i(.*)$#m', function ($m) use ($dir) {
            $import = trim($m[1]);
            if ($import[0] != '/') {
                $import = "{$dir}/{$import}";
            }
            return file_get_contents($import);
        }, $sql);

        return $sql;
    }
}