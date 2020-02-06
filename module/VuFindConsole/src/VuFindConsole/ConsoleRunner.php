<?php
namespace VuFindConsole;

use Laminas\ServiceManager\ServiceManager;
use Symfony\Component\Console\Application;

class ConsoleRunner
{
    protected $config;
    protected $serviceManager;

    public function __construct(array $config, ServiceManager $sm)
    {
        $this->config = $config;
        $this->serviceManager = $sm;
    }

    public function run()
    {
        $consoleApp = new Application();
        $consoleApp->run();
    }
}
