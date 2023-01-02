<?php

namespace Beccati\PgSchemaBundle\Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class InitCommand extends Base
{
    static protected $commandName = 'init';
    static protected $commandDesc = 'Initialize the project schema folder';

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $root = $this->getContainer()->getParameter('kernel.root_dir').'/Resources/pgschema';

        if (file_exists($root)) {
            if (is_dir($root)) {
                throw new \InvalidArgumentException("The folder '{$root}' already exists");
            } else {
                throw new \InvalidArgumentException("A file called '{$root}' already exists");
            }
        }

        $verbose = $output->getVerbosity() != OutputInterface::VERBOSITY_QUIET;

        if ($verbose) {
            $output->writeln("Creating directory structure");
        }

        mkdir($root);
        mkdir($root.'/current');
        mkdir($root.'/current/public');

        mkdir($root.'/upgrades');
        touch($root.'/upgrades/.gitignore');

        mkdir($root.'/history');
        touch($root.'/history/.gitignore');

        if ($verbose) {
            $output->writeln("Creating configuration file");
        }

        file_put_contents($root.'/current/config.yml', <<<EOF
version: 1
schemas: [ public ]
extensions: ~
EOF
        );

        if ($verbose) {
            $output->writeln("Creating versioning SQL file");
        }

        file_put_contents($root.'/current/public/00_version.sql', <<<EOF

-- Table: version
CREATE TABLE version (version int NOT NULL PRIMARY KEY);
CREATE UNIQUE INDEX version_1 ON version ((1));

EOF
        );
    }
}

