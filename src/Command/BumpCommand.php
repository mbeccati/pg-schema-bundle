<?php

namespace Beccati\PgSchemaBundle\Command;

use Beccati\PgSchemaBundle\Manager\UpgradeManager;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class BumpCommand extends Base
{
    static protected $commandName = 'bump';
    static protected $commandDesc = 'Bump version';

    public function __construct(
        public UpgradeManager $upgradeManager,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->upgradeManager->setOutput($output);

        $success = $this->upgradeManager->bumpVersion();

        return $success ? 0 : 1;
    }
}
