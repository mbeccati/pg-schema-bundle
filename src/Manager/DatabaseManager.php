<?php

namespace Beccati\PgSchemaBundle\Manager;

use Beccati\PgSchemaBundle\Manager\Filter\FilterInterface;
use Doctrine\DBAL\Connection;
use PDO;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerAwareTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Yaml\Parser;
use Symfony\Component\Yaml\Dumper;
use Symfony\Component\Console\Output\OutputInterface;

class DatabaseManager
{
    protected PDO $pdo;
    protected OutputInterface $output;
    protected int $version = 0;
    protected array $schemas = ['public'];
    protected array $extensions = [];
    protected bool $unlogged = false;

    /**
     * @var array<FilterInterface>
     */
    private array $filters = [];

    public function __construct(Connection $dbalConnection)
    {
        $this->pdo = $dbalConnection->getWrappedConnection()->getNativeConnection();
    }

    /**
     * Create the tables using the current schema or a specific version.
     *
     * @param int $version If null, the current version is used
     *
     * @throws \Exception
     */
    public function createTables(int $version = null): void
    {
        try {
            $existingVersion = $this->getDbVersion();
            throw new \Exception("The database already contains a schema at version {$existingVersion}");
        } catch (\PDOException $e) {
            if ($e->getCode() != '42P01') {
                throw $e;
            }
        }

        $root = $this->getDataDir($version);
        $this->init($root);

        if ($this->unlogged) {
            $this->addFilter('Unlogged');
        }

        $this->pdo->beginTransaction();

        $this->createExtensions();

        try {
            foreach ($this->schemas as $schema) {
                $dir = "{$root}/{$schema}";
                if (!is_dir($dir)) {
                    throw new \Exception("Schema directory not found {$dir}");
                }
                foreach (glob("{$dir}/*.sql") as $file) {
                    $this->executeSqlFile($file);
                }
            }

            $this->setDbVersion($this->version);
        } catch (\PDOException $e) {
            $this->pdo->rollback();
            throw $e;
        }
        $this->pdo->commit();

        $this->clearFilters();
    }

    /**
     * Initialise the manager.
     *
     * @param string $dir the directory that contains the configuration file
     *
     * @throws \Exception
     */
    protected function init(string $dir): void
    {
        $conf = $this->parseConfiguration($dir.'/config.yml');

        $this->version      = $conf['version'];
        $this->schemas      = (array)$conf['schemas'];
        $this->extensions   = (array)$conf['extensions'];
    }

    /**
     * Creates the database extensions.
     */
    protected function createExtensions(): void
    {
        foreach ($this->extensions as $extension) {
            if (!$this->checkExtension($extension)) {
                $this->installExtension($extension);
            }
        }
    }

    /**
     * Sets the output handler.
     */
    public function setOutput(OutputInterface $output): void
    {
        $this->output = $output;
    }

    /**
     * Gets the output handler.
     */
    public function getOutput(): OutputInterface
    {
        return $this->output;
    }

    /**
     * Sets the unlogged parameter.
     */
    public function setUnlogged(bool $unlogged)
    {
        $this->unlogged = $unlogged;
    }

    /**
     * Gets the unlogged parameter.
     */
    public function getUnlogged(): bool
    {
        return $this->unlogged;
    }

    /**
     * Reads an sql file, applying the registered filters.
     *
     * @throws \Exception
     */
    protected function readSqlFile(string $file): string
    {
        if (!file_exists($file)) {
            throw new \Exception("Coundn't find {$file}");
        }

        $sql =  file_get_contents($file);

        foreach ($this->filters as $k => $v) {
            $this->log("   -> Applying %s filter", $k);
            $sql = $v->filter($sql);
        }

        return $sql;
    }

    /**
     * Executes an SQL file, applying the registered filters.
     *
     * @throws \Exception
     */
    protected function executeSqlFile(string $file): void
    {
        $this->log("Loading %s", $file);
        $sql = $this->readSqlFile($file);

        $this->log(" - Executing SQL");
        $this->pdo->exec($sql);
    }

    /**
     * Checks if the Postgres extension is already installed.
     */
    protected function checkExtension(string $extension): bool
    {
        return (bool)$this->pdo->query("SELECT 1 FROM pg_extension WHERE extname = '{$extension}' LIMIT 1")->rowCount();
    }

    /**
     * Installs a Postgres extension.
     */
    private function installExtension(string $extension): bool
    {
        return (bool)$this->pdo->exec("CREATE EXTENSION {$extension}");
    }

    /**
     * Gets the current database schema version.
     */
    protected function getDbVersion(): int
    {
        return (int)$this->pdo->query("SELECT * FROM version")->fetchColumn();
    }

    /**
     * Updates the schema version on the database.
     */
    protected function setDbVersion(int $version): int
    {
        return $this->pdo->exec("TRUNCATE version; INSERT INTO version VALUES ({$version})");
    }

    /**
     * Parse the configuration file.
     *
     * @throws \Exception
     */
    protected function parseConfiguration(stirng $file): mixed
    {
        $yaml = new Parser();

        if (!file_exists($file)) {
            throw new \Exception("Coundn't find {$file}");
        }

        return $yaml->parse(file_get_contents($file));
    }

    /**
     * Dumps the configuration file.
     */
    protected function saveConfiguration(string $file, mixed $conf): bool
    {
        $yaml = new Dumper();

        $str = $yaml->dump($conf, 1);

        return (bool)file_put_contents($file, $str);
    }

    /**
     * Get the path to the "pgschema" root directory.
     */
    protected function getRootDir(): string
    {
        return $this->container->getParameter('kernel.root_dir').'/Resources/pgschema/';
    }

    /**
     * Gets the data dir for a specific schema version.
     *
     * @throws \Exception
     */
    protected function getDataDir(int $version): string
    {
        $dir = $this->getRootDir();

        if (!isset($version)) {
            $dir .= 'current';
        } else {
            $dir .= 'history/'.$version;
        }

        if (!is_dir($dir)) {
            throw new \Exception("Directory not found {$dir}");
        }

        return $dir;
    }

    /**
     * Register a new filter, passing $options to the Filter constructor.
     *
     * @param string $name The class name (w/o namespace)
     */
    protected function addFilter(string $name, array $options = null)
    {
        $className = __NAMESPACE__.'\\Filter\\'.$name;
        $this->filters[$name] = new $className($options);
    }

    /**
     * Clears the registered filters.
     */
    public function clearFilters()
    {
        $this->filters = [];
    }

    /**
     * Logs to the console.
     *
     * @param array $args
     * @param string $tag
     */
    private function _log(array $args, $tag = '')
    {
        if ($this->output && $this->output->getVerbosity() >= OutputInterface::VERBOSITY_NORMAL) {
            if (!empty($tag)) {
                $args[0] = "<{$tag}>{$args[0]}</{$tag}>";
            }

            $this->output->writeln(call_user_func_array('sprintf', $args));
        }
    }

    /**
     * Variadic log function (sprintf format).
     */
    public function log(...$args)
    {
        $this->_log($args);
    }

    /**
     * Variadic log function (sprintf format, INFO level).
     */
    public function logInfo(...$args)
    {
        $this->_log($args, 'info');
    }

    /**
     * Variadic log function (sprintf format, COMMENT level).
     */
    public function logComment(...$args)
    {
        $this->_log($args, 'comment');
    }

    /**
     * Variadic log function (sprintf format, QUESTION level).
     */
    public function logQuestion(...$args)
    {
        $this->_log($args, 'question');
    }

    /**
     * Variadic log function (sprintf format, ERROR level).
     */
    public function logError(...$args)
    {
        $this->_log($args, 'error');
    }
}