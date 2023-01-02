<?php

namespace Beccati\PgSchemaBundle\Command;

use Beccati\PgSchemaBundle\Manager\UpgradeManager;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Question\ConfirmationQuestion;

class UpgradeCommand extends Base
{
    static protected $commandName = 'upgrade';
    static protected $commandDesc = 'Upgrade the schema';

    public function __construct(
        public UpgradeManager $upgradeManager,
    ) {
        parent::__construct();
    }

    protected function configure()
    {
        parent::configure();

        $this->addOption('dry-run', null, InputOption::VALUE_NONE, 'Do not execute the upgrade');
        $this->addOption('from', null, InputOption::VALUE_REQUIRED, 'Override current database version');
        $this->addOption('to', null, InputOption::VALUE_REQUIRED, 'Target database version');
        $this->addOption('force', 'f', InputOption::VALUE_NONE, 'Execute upgrade without asking confirmation');
        //$this->addOption('create', 'c', InputOption::VALUE_NONE, 'Create the schema if the database is empty');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->upgradeManager->setOutput($output);

        $success = $this->upgradeManager->upgrade(
            $input->getOption('from'),
            $input->getOption('to'),
            $input->getOption('dry-run'),
            $input->getOption('force') ? null : function () use ($input, $output) {
                $helper = new QuestionHelper();
                $question = new ConfirmationQuestion('Continue with this action?', false);

                return $helper->ask($input, $output, $question);
            }
        );

        return $success ? 0 : 1;
    }
}

