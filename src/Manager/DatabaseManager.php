<?php

namespace Beccati\PgSchemaBundle\Manager;

use Beccati\PgSchemaBundle\Manager\Filter\FilterInterface;

use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerAwareTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Yaml\Parser;
use Symfony\Component\Yaml\Dumper;
use Symfony\Component\Console\Output\OutputInterface;


class DatabaseManager implements ContainerAwareInterface
{
    use ContainerAwareTrait;

    /**
     * @var \PDO
     */
    protected $pdo;

    /**
     *
     * @var OutputInterface
     */
    protected $output;

    /**
     * @var int
     */
    protected $version = 0;

    /**
     * @var array
     */
    protected $schemas = array('public');

    /**
     * @var array
     */
    protected $extensions = array();

    /**
     * @var bool
     */
    protected $unlogged = false;

    /**
     * @var FilterInterface[]
     */
    private $filters = array();


    /**
     * The constructor.
     *
     * @param ContainerInterface $container
     */
    public function __construct(ContainerInterface $container)
    {
        $this->setContainer($container);

        $this->pdo = $this->container->get('doctrine.dbal.default_connection')->getWrappedConnection();
    }

    /**
     * Create the tables using the current schema or a specific version.
     *
     * @param int $version If null, the current version is used
     *
     * @throws \Exception
     */
    public function createTables($version = null)
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
    protected function init($dir)
    {
        $conf = $this->parseConfiguration($dir.'/config.yml');

        $this->version      = $conf['version'];
        $this->schemas      = (array)$conf['schemas'];
        $this->extensions   = (array)$conf['extensions'];
    }

    /**
     * Creates the database extensions.
     */
    protected function createExtensions()
    {
        foreach ($this->extensions as $extension) {
            if (!$this->checkExtension($extension)) {
                $this->installExtension($extension);
            }
        }
    }

    /**
     * Sets the output handler.
     *
     * @param OutputInterface $output
     */
    public function setOutput(OutputInterface $output)
    {
        $this->output = $output;
    }

    /**
     * Gets the output handler.
     *
     * @return OutputInterface
     */
    public function getOutput()
    {
        return $this->output;
    }

    /**
     * Sets the unlogged parameter.
     *
     * @param bool $unlogged
     */
    public function setUnlogged($unlogged)
    {
        $this->unlogged = (bool)$unlogged;
    }

    /**
     * Gets the unlogged parameter.
     *
     * @return bool
     */
    public function getUnlogged()
    {
        return $this->unlogged;
    }

    /**
     * Reads an sql file, applying the registered filters.
     *
     * @param string $file
     *
     * @return string
     * @throws \Exception
     */
    protected function readSqlFile($file)
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
     * @param string $file
     *
     * @throws \Exception
     */
    protected function executeSqlFile($file)
    {
        $this->log("Loading %s", $file);
        $sql = $this->readSqlFile($file);

        $this->log(" - Executing SQL");
        $this->pdo->exec($sql);
    }

    /**
     * Checks if the Postgres extension is already installed.
     *
     * @param string $extension
     *
     * @return bool
     */
    protected function checkExtension($extension)
    {
        return (bool)$this->pdo->query("SELECT 1 FROM pg_extension WHERE extname = '{$extension}' LIMIT 1")->rowCount();
    }

    /**
     * Installs a Postgres extension.
     *
     * @param string $extension
     *
     * @return bool
     */
    private function installExtension($extension)
    {
        return (bool)$this->pdo->exec("CREATE EXTENSION {$extension}");
    }

    /**
     * Gets the current database schema version.
     *
     * @return int
     */
    protected function getDbVersion()
    {
        return (int)$this->pdo->query("SELECT * FROM version")->fetchColumn();
    }

    /**
     * Updates the schema version on the database.
     *
     * @param $version
     *
     * @return int
     */
    protected function setDbVersion($version)
    {
        $version = (int)$version;
        return $this->pdo->exec("TRUNCATE version; INSERT INTO version VALUES ({$version})");
    }

    /**
     * Parse the configuration file.
     *
     * @param string $file
     *
     * @return mixed
     * @throws \Exception
     */
    protected function parseConfiguration($file)
    {
        $yaml = new Parser();

        if (!file_exists($file)) {
            throw new \Exception("Coundn't find {$file}");
        }

        return $yaml->parse(file_get_contents($file));
    }

    /**
     * Dumps the configuration file.
     *
     * @param string $file
     * @param mixed $conf
     *
     * @return bool
     */
    protected function saveConfiguration($file, $conf)
    {
        $yaml = new Dumper();

        $str = $yaml->dump($conf, 1);

        return (bool)file_put_contents($file, $str);
    }

    /**
     * Get the path to the "pgschema" root directory.
     *
     * @return string
     */
    protected function getRootDir()
    {
        return $this->container->getParameter('kernel.root_dir').'/Resources/pgschema/';
    }

    /**
     * Gets the data dir for a specific schema version.
     *
     * @param int $version
     *
     * @return string
     * @throws \Exception
     */
    protected function getDataDir($version)
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
     * @param mixed $options
     */
    protected function addFilter($name, $options = null)
    {
        $className = __NAMESPACE__.'\\Filter\\'.$name;
        $this->filters[$name] = new $className($options);
    }

    /**
     * Clears the registered filters.
     */
    public function clearFilters()
    {
        $this->filters = array();
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
     *
     * @param $str
     */
    public function log($str)
    {
        $this->_log(func_get_args());
    }

    /**
     * Variadic log function (sprintf format, INFO level).
     *
     * @param $str
     */
    public function logInfo($str)
    {
        $this->_log(func_get_args(), 'info');
    }

    /**
     * Variadic log function (sprintf format, COMMENT level).
     *
     * @param $str
     */
    public function logComment($str)
    {
        $this->_log(func_get_args(), 'comment');
    }

    /**
     * Variadic log function (sprintf format, QUESTION level).
     *
     * @param $str
     */
    public function logQuestion($str)
    {
        $this->_log(func_get_args(), 'question');
    }

    /**
     * Variadic log function (sprintf format, ERROR level).
     *
     * @param $str
     */
    public function logError($str)
    {
        $this->_log(func_get_args(), 'error');
    }
}