<?php

namespace Beccati\PgSchemaBundle\Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputOption;


class UpgradeCommand extends Base
{
    static protected $commandName = 'upgrade';
    static protected $commandDesc = 'Upgrade the schema';

    protected function configure()
    {
        parent::configure();

        $this->addOption('dry-run', null, InputOption::VALUE_NONE, 'Do not execute the upgrade');
        $this->addOption('from', null, InputOption::VALUE_REQUIRED, 'Override current database version');
        $this->addOption('to', null, InputOption::VALUE_REQUIRED, 'Target database version');
        $this->addOption('force', 'f', InputOption::VALUE_NONE, 'Execute upgrade without asking confirmation');
        //$this->addOption('create', 'c', InputOption::VALUE_NONE, 'Create the schema if the database is empty');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $db = $this->getContainer()->get('beccati_pg_schema.manager.upgrade');

        $db->setOutput($output);

        $success = $db->upgrade(
            $input->getOption('from'),
            $input->getOption('to'),
            $input->getOption('dry-run'),
            $input->getOption('force') ? null : $this->getHelperSet()->get('dialog')
        );

        return $success ? 0 : 1;
    }
}

