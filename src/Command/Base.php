<?php

namespace Beccati\PgSchemaBundle\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;


class Base extends ContainerAwareCommand
{
    static protected $commandName = 'example';
    static protected $commandDesc = 'An example command';

    protected function configure()
    {
        $this->extensionCheck();

        parent::configure();
        $this
            ->setName('pgsql:schema:'.static::$commandName)
            ->setDescription(static::$commandDesc);
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {

    }

    protected function extensionCheck()
    {
        $extensions = array('pdo_pgsql');
        foreach ($extensions as $ext) {
            if (!extension_loaded($ext)) {
                throw new \Exception("The {$ext} extension is not installed");
            }
        }
    }
}

