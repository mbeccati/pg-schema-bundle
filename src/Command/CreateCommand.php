<?php

namespace Beccati\PgSchemaBundle\Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputOption;


class CreateCommand extends Base
{
    static protected $commandName = 'create';
    static protected $commandDesc = 'Create the schema';

    protected function configure()
    {
        parent::configure();

        $this->addOption('unlogged', 'u', InputOption::VALUE_NONE, 'If set, all the tables will be created as unlogged');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $db = $this->getContainer()->get('beccati_pg_schema.manager.database');

        $db->setOutput($output);

        $db->setUnlogged($input->getOption('unlogged'));

        $db->createTables();
    }
}

