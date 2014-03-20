<?php
namespace Omeka\Installation;

use Zend\ServiceManager\ServiceLocatorAwareInterface;
use Zend\ServiceManager\ServiceLocatorInterface;

/**
 * Installation manager service.
 */
class Manager implements ServiceLocatorAwareInterface
{
    /**
     * Table against which to check for an Omeka installation
     */
    const CHECK_TABLE = 'user';

    /**
     * @var ServiceLocatorInterface
     */
    protected $services;

    /**
     * @var array Registered installation tasks.
     */
    protected $tasks = array();

    /**
     * @var array Registered task variables.
     */
    protected $vars = array();

    /**
     * Check whether Omeka is currently installed.
     */
    public function isInstalled()
    {
        $connection = $this->getServiceLocator()->get('Omeka\Connection');
        $config = $this->getServiceLocator()->get('ApplicationConfig');
        $tables = $connection->getSchemaManager()->listTableNames();
        $checkTable = $config['connection']['table_prefix'] . self::CHECK_TABLE;
        return in_array($checkTable, $tables);
    }

    /**
     * Install Omeka.
     *
     * @return Result
     */
    public function install()
    {
        $result = new Result;

        if ($this->isInstalled()) {
            $result->addMessage('Omeka is already installed.', Result::MESSAGE_TYPE_ERROR);
            return $result;
        }

        foreach ($this->getTasks() as $taskName) {
            $start = microtime(true);
            $task = new $taskName($result);
            if ($task instanceof ServiceLocatorAwareInterface) {
                $task->setServiceLocator($this->getServiceLocator());
            }
            // Set task-specific variables.
            $vars = $this->getVars($taskName);
            if ($vars) {
                $task->setVars($vars);
            }
            $task->perform();
            $end = microtime(true);
            $result->addMessage(sprintf('time: %.2f', $end - $start)); 
            // Tasks are dependent on previously run tasks. If there is an
            // error, stop installation immediately and return the result.
            if ($result->isError()) {
                return $result;
            }
        }
        return $result;
    }

    /**
     * Register an installation task.
     * 
     * @param string $task
     */
    public function registerTask($task)
    {
        if (!class_exists($task)) {
            throw new Exception\ConfigException(sprintf(
                'The "%s" installation task does not exist.', 
                $task
            ));
        }
        if (!is_subclass_of($task, 'Omeka\Installation\Task\TaskInterface')) {
            throw new Exception\ConfigException(sprintf(
                'The "%s" installation task does not implement Omeka\Installation\Task\TaskInterface.', 
                $task
            ));
        }
        $this->tasks[] = $task;
    }

    /**
     * Register installation tasks.
     * 
     * @param array $tasks
     */
    public function registerTasks(array $tasks)
    {
        foreach ($tasks as $task) {
            $this->registerTask($task);
        }
    }

    /**
     * Get registered installation tasks.
     * 
     * @return array
     */
    public function getTasks()
    {
        return $this->tasks;
    }

    /**
     * Register a specific task's variables.
     *
     * @param str $task
     * @param array $vars
     */
    public function registerVars($task, array $vars)
    {
        $this->vars[$task] = $vars;
    }

    /**
     * Get a specific task's variables.
     *
     * @return array|null
     */
    public function getVars($task)
    {
        return isset($this->vars[$task]) ? $this->vars[$task] : null;
    }

    /**
     * {@inheritdoc}
     */
    public function setServiceLocator(ServiceLocatorInterface $serviceLocator)
    {
        $this->services = $serviceLocator;
    }

    /**
     * {@inheritdoc}
     */
    public function getServiceLocator()
    {
        return $this->services;
    }
}
