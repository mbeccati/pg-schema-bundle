<?php

namespace Beccati\PgSchemaBundle\Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;


class BumpCommand extends Base
{
    static protected $commandName = 'bump';
    static protected $commandDesc = 'Bump version';

    protected function configure()
    {
        parent::configure();
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $db = $this->getContainer()->get('beccati_pg_schema.manager.upgrade');

        $db->setOutput($output);

        $success = $db->bumpVersion();

        return $success ? 0 : 1;
    }
}

