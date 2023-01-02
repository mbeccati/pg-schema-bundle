<?php

namespace Beccati\PgSchemaBundle\Command;

use Beccati\PgSchemaBundle\Manager\DatabaseManager;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputOption;

class CreateCommand extends Base
{
    static protected $commandName = 'create';
    static protected $commandDesc = 'Create the schema';

    public function __construct(
        public DatabaseManager $databaseManager,
    ) {
        parent::__construct();
    }

    protected function configure()
    {
        parent::configure();

        $this->addOption('unlogged', 'u', InputOption::VALUE_NONE, 'If set, all the tables will be created as unlogged');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->databaseManager->setOutput($output);

        $this->databaseManager->setUnlogged($input->getOption('unlogged'));

        $this->databaseManager->createTables();
    }
}

