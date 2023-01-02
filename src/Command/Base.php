<?php

namespace Beccati\PgSchemaBundle\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class Base extends Command
{
    static protected $commandName = 'example';
    static protected $commandDesc = 'An example command';

    public static function getDefaultName(): ?string
    {
        return 'pgsql:schema:'.static::$commandName;
    }

    public static function getDefaultDescription(): ?string
    {
        return static::$commandDesc;
    }

    protected function configure()
    {
        $this->extensionCheck();

        parent::configure();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        return 1;
    }

    protected function extensionCheck()
    {
        if (!extension_loaded('pdo_pgsql')) {
            throw new \RuntimeException("The {$ext} extension is not installed");
        }
    }
}

