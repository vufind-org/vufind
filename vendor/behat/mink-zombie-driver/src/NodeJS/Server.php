<?php

/*
 * This file is part of the Behat\Mink.
 * (c) Konstantin Kudryashov <ever.zet@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Behat\Mink\Driver\NodeJS;

use Symfony\Component\Process\ProcessBuilder;
use Symfony\Component\Process\Process;

/**
 * Abstract base class to start and connect to a NodeJS server process.
 *
 * @author Pascal Cremer <b00gizm@gmail.com>
 */
abstract class Server
{
    /**
     * @var string
     */
    protected $host;

    /**
     * @var int
     */
    protected $port;

    /**
     * @var string
     */
    protected $nodeBin;

    /**
     * @var string
     */
    protected $serverPath;

    /**
     * @var int
     */
    protected $threshold;

    /**
     * @var string The full path to the NodeJS modules directory.
     */
    protected $nodeModulesPath;

    /**
     * @var Process
     */
    protected $process;

    /**
     * @var Connection
     */
    protected $connection;

    /**
     * Constructor
     *
     * @param string $host            The server host
     * @param int    $port            The server port
     * @param string $nodeBin         Path to NodeJS binary
     * @param string $serverPath      Path to server script
     * @param int    $threshold       Threshold value in micro seconds
     * @param string $nodeModulesPath Path to node_modules directory
     */
    public function __construct(
        $host = '127.0.0.1',
        $port = 8124,
        $nodeBin = null,
        $serverPath = null,
        $threshold = 2000000,
        $nodeModulesPath = ''
    ) {
        if (null === $nodeBin) {
            $nodeBin = 'node';
        }

        $this->host            = $host;
        $this->port            = intval($port);
        $this->nodeBin         = $nodeBin;
        $this->nodeModulesPath = $nodeModulesPath;

        if (null === $serverPath) {
            $serverPath = $this->createTemporaryServer();
        }

        $this->serverPath      = $serverPath;
        $this->threshold       = intval($threshold);
    }

    /**
     * Destructor
     *
     * Make sure that current process is stopped
     */
    public function __destruct()
    {
        $this->stop();
    }

    /**
     * Setter host
     *
     * @param string $host The server host
     */
    public function setHost($host)
    {
        $this->host = $host;
    }

    /**
     * Getter host
     *
     * @return string The server host
     */
    public function getHost()
    {
        return $this->host;
    }

    /**
     * Setter port
     *
     * @param int $port The server port
     */
    public function setPort($port)
    {
        $this->port = intval($port);
    }

    /**
     * Getter port
     *
     * @return int The server port
     */
    public function getPort()
    {
        return $this->port;
    }

    /**
     * Setter NodeJS binary path
     *
     * @param string $nodeBin Path to NodeJS binary
     */
    public function setNodeBin($nodeBin)
    {
        $this->nodeBin = $nodeBin;
    }

    /**
     * Getter NodeJS binary path
     *
     * @return string Path to NodeJS binary
     */
    public function getNodeBin()
    {
        return $this->nodeBin;
    }

    /**
     * Setter NodeJS modules path
     *
     * @param  string                    $nodeModulesPath Path to NodeJS modules.
     * @throws \InvalidArgumentException Invalid path
     */
    public function setNodeModulesPath($nodeModulesPath)
    {
        if (!is_dir($nodeModulesPath) || !preg_match('/\/$/', $nodeModulesPath)) {
            throw new \InvalidArgumentException(sprintf(
                "Node modules path '%s' is not a directory and/or does not end with a trailing '/'",
                $nodeModulesPath
            ));
        }
        $this->nodeModulesPath = $nodeModulesPath;
        $this->serverPath = $this->createTemporaryServer();
    }

    /**
     * Getter NodeJS modules path.
     *
     * @return string Path to NodeJS binary.
     */
    public function getNodeModulesPath()
    {
        return $this->nodeModulesPath;
    }

    /**
     * Setter server script path
     *
     * @param string $serverPath Path to server script
     */
    public function setServerPath($serverPath)
    {
        $this->serverPath = $serverPath;
    }

    /**
     * Getter server script path
     *
     * @return string Path to server script
     */
    public function getServerPath()
    {
        return $this->serverPath;
    }

    /**
     * Setter threshold value
     *
     * @param int $threshold Threshold value in micro seconds
     */
    public function setThreshold($threshold)
    {
        $this->threshold = intval($threshold);
    }

    /**
     * Getter threshold value
     *
     * @return int Threshold value in micro seconds
     */
    public function getThreshold()
    {
        return $this->threshold;
    }

    /**
     * Getter process object
     *
     * @return Process The process object
     */
    public function getProcess()
    {
        return $this->process;
    }

    /**
     * Getter connection object
     *
     * @return Connection
     */
    public function getConnection()
    {
        return $this->connection;
    }

    /**
     * Starts the server process
     *
     * @param Process $process A process object
     *
     * @throws \RuntimeException
     */
    public function start(Process $process = null)
    {
        // Check if the server script exists at given path
        if (false === $this->serverPath || false === is_file($this->serverPath)) {
            throw new \RuntimeException(sprintf(
                "Could not find server script at path '%s'",
                $this->serverPath
            ));
        }

        // Create process object if neccessary
        if (null === $process) {
            $processBuilder = new ProcessBuilder(array(
                $this->nodeBin,
                $this->serverPath,
            ));
            $process = $processBuilder->getProcess();
        }
        $this->process = $process;

        // Start server process
        $this->process->start();
        $this->connection = null;

        // Wait for the server to start up
        $time = 0;
        $successString = sprintf("server started on %s:%s", $this->host, $this->port);
        while ($this->process->isRunning() && $time < $this->threshold) {
            if ($successString == trim($this->process->getOutput())) {
                $this->connection = new Connection($this->host, $this->port);
                break;
            }
            usleep(1000);
            $time += 1000;
        }

        // Make sure the server is ready or throw an exception otherwise
        $this->checkAvailability();
    }

    /**
     * Stops the server process
     * @link https://github.com/symfony/Process
     */
    public function stop()
    {
        if (null === $this->process) {
            return;
        }

        if (!$this->isRunning()) {
            return;
        }

        if (null !== $this->getConnection()) {
            // Force a 'clean' exit
            // See: http://stackoverflow.com/a/5266208/187954
            $this->doEvalJS($this->getConnection(), 'process.exit(0);');
            $this->process->stop();
            $this->process = null;
        }
    }

    /**
     * Restarts the server process
     *
     * @param Process $process A process object
     */
    public function restart(Process $process = null)
    {
        $this->stop();
        $this->start($process);
    }

    /**
     * Checks if the server process is running
     *
     * @link https://github.com/symfony/Process
     *
     * @return bool
     */
    public function isRunning()
    {
        if (null === $this->process) {
            return false;
        }

        return $this->process->isRunning();
    }

    /**
     * Checks the availability of the server triggers the evaluation
     * of a string of JavaScript code by {{Behat\Mink\Driver\NodeJS\Server::doEvalJS()}}
     *
     * @param string $str        String of JavaScript code
     * @param string $returnType Whether it should be eval'ed as JavaScript or wrapped in a JSON response
     *
     * @return mixed The eval'ed response
     */
    public function evalJS($str, $returnType = 'js')
    {
        $this->checkAvailability();

        return $this->doEvalJS($this->connection, $str, $returnType);
    }

    /**
     * Inherited classes will implement this method to prepare a string of
     * JavaScript code for evaluation by the server and sending it over
     * the server connection socket
     *
     * @param Connection $conn       The server connection
     * @param string     $str        String of JavaScript code
     * @param string     $returnType The return type
     *
     * @return mixed The eval'ed response
     *
     * @throws \InvalidArgumentException When unsupported $returnType given.
     */
    abstract protected function doEvalJS(Connection $conn, $str, $returnType = 'js');

    /**
     * Checks whether server connection and server process are still available
     * and running
     *
     * @throws \RuntimeException
     */
    protected function checkAvailability()
    {
        if (null === $this->connection) {
            if (null === $this->process) {
                throw new \RuntimeException('No connection available. Did you start the server?');
            }

            if ($this->process->isRunning()) {
                $this->stop();
                throw new \RuntimeException(sprintf(
                    'Server did not respond in time: (%s) [Stopped]',
                    $this->process->getExitCode()
                ));
            }
        }

        if (!$this->process->isRunning()) {
            throw new \RuntimeException(sprintf(
                'Server process has been terminated: (%s) [%s]',
                $this->process->getExitCode(),
                $this->process->getErrorOutput()
            ));
        }
    }

    /**
     * Creates a temporary server script
     *
     * @return string Path to the temporary server script
     */
    protected function createTemporaryServer()
    {
        $serverScript = strtr($this->getServerScript(), array(
            '%host%'         => $this->host,
            '%port%'         => $this->port,
            '%modules_path%' => $this->nodeModulesPath,
        ));

        $serverPath = tempnam(sys_get_temp_dir(), 'mink_nodejs_server');
        file_put_contents($serverPath, $serverScript);

        return $serverPath;
    }

    /**
     * Inherited classes will implement this method to provide the JavaScript
     * code which powers the server script
     *
     * @return string The server's JavaScript code
     */
    abstract protected function getServerScript();
}
