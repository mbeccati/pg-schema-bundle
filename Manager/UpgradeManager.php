<?php

namespace Beccati\PgSchemaBundle\Manager;

use Symfony\Component\Finder\Finder;
use Symfony\Component\Filesystem\Filesystem;


class UpgradeManager extends DatabaseManager
{
    /**
     * Perform the upgrade.
     *
     * @param int $versionFrom
     * @param int $versionTo
     * @param bool $dryRun
     * @param callback $question
     *
     * @return bool
     * @throws \Exception
     */
    public function upgrade($versionFrom = null, $versionTo = null, $dryRun = false, $question = null)
    {
        try {
            $currentVersion = $this->getDbVersion();
        } catch (\PDOException $e) {
            if ($e->getCode() == '42P01') {
                throw new \Exception("The database is empty");
            }

            $currentVersion = null;
        }

        $this->init($this->getDataDir(null));
        $latestVersion = $this->version;

        if (!isset($versionFrom)) {
            $versionFrom = $currentVersion;
        } else {
            $versionFrom = (int)$versionFrom;
            if (empty($versionFrom)) {
                throw new \Exception("Invalid source version");
            }
        }

        if (isset($versionTo)) {
            $forceVersion = true;
            $versionTo = (int)$versionTo;
            if (empty($versionTo)) {
                throw new \Exception("Invalid target version");
            } elseif ($versionTo == $latestVersion) {
                $versionTo = null;
            }
        }

        $root = $this->getDataDir($versionTo);
        $this->init($root);
        if (empty($forceVersion)) {
            // Use latest version - 1 by default for stamping, as current is
            // normally offset by +1, as it's ready for new changes
            $versionTo = --$this->version;
        }

        if ($versionTo == $versionFrom) {
            $this->log("No upgrade required");
            return true;
        }

        $this->logComment("+".str_repeat('-', 42)."+");
        $this->logComment("| %-36s %3d |", 'Current database version:', $currentVersion);
        if ($currentVersion != $versionFrom) {
            $this->logComment("| %-36s %3d |", 'Overriding source version:', $versionFrom);
        }
        $this->logComment("| %-36s %3d |", 'Target version:', $this->version);
        $this->logComment("+".str_repeat('-', 42)."+\n");

        // Filter
        $this->addFilter('BackslashCommands', $root);

        // Unlogged
        if ($this->pdo->query("SELECT relpersistence <> 'p' FROM pg_class WHERE relname = 'version'")->fetchColumn()) {
            $this->addFilter('Unlogged');
        }

        $files = $this->getUpgradeFiles($versionFrom, $versionTo);
        $this->log("The upgrade will execute the following files:");
        foreach ($files as $file) {
            $this->log(" - %s", $file->getFilename());
        }
        $this->log("");

        if ($dryRun) {
            return true;
        }

        if (null !== $question && !$question()) {
            $this->logError("\nUpgrade aborted\n");
            return false;
        }

        $this->log("");

        $this->pdo->beginTransaction();
        try {
            foreach ($files as $file) {
                $this->executeSqlFile($file->getPathname());
            }
        } catch (\PDOException $e) {
            $this->pdo->rollBack();

            throw $e;
        }

        $this->setDbVersion(empty($versionTo) ? $latestVersion : $versionTo);

        $this->pdo->commit();

        $this->log("\nDatabase successfuly upgraded\n");

        return true;
    }

    /**
     * Bump the schema version.
     *
     * @return int
     * @throws \Exception
     */
    public function bumpVersion()
    {
        $root = $this->getRootDir();
        $datadir = $this->getDataDir(null);

        $this->init($datadir);
        $latestVersion = $this->version;

        $src = 'current';
        $dst = "history/$latestVersion";

        $this->log("Copying <info>$src</info> directory to <info>$dst</info>");

        chdir($root);

        $fs = new Filesystem;
        $fs->mirror($src, $dst);

        $confFile = 'current/config.yml';
        $conf = $this->parseConfiguration($confFile);

        // Filter
        $this->addFilter('BackslashCommands', $datadir);

        $files = $this->getUpgradeFiles($conf['version'] - 1, $conf['version']);
        $this->log("Finalizing files:");

        /** @var \SplFileInfo $file */
        foreach ($files as $file) {
            $this->log(" - %s", $file->getFilename());
            $fname = $file->getPathname();
            file_put_contents($fname, $this->readSqlFile($fname));
        }

        $this->log("Bumping version");

        $conf['version']++;
        return $this->saveConfiguration($confFile, $conf);
    }

    /**
     * Returns the list of upgrade files that can be applied.
     *
     * @param int $versionFrom
     * @param int $versionTo
     *
     * @return Finder
     */
    protected function getUpgradeFiles($versionFrom, $versionTo)
    {
        $finder = new Finder();

        return $finder
                ->files()
                ->in($this->getRootDir().'/upgrades')
                ->name('/^\d+_.*\.sql$/')
                ->filter(function (\SplFileInfo $file) use ($versionFrom, $versionTo) {
                    $v = (int)$file->getBasename();
                    return $v > $versionFrom && (empty($versionTo) || $v <= $versionTo);
                })
                ->sort(function (\SplFileInfo $a, \SplFileInfo $b) {
                    $a = $a->getBasename();
                    $b = $b->getBasename();
                    $x = (int)$a;
                    $y = (int)$b;
                    if ($x == $y) {
                        return strcmp($a, $b);
                    }
                    return $x - $y;
                });
    }

}