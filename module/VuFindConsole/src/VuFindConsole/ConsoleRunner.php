<?php
/**
 * Console runner.
 *
 * PHP version 7
 *
 * Copyright (C) Villanova University 2020.
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License version 2,
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
 *
 * @category VuFind
 * @package  Console
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
namespace VuFindConsole;

use Laminas\ServiceManager\ServiceManager;
use Symfony\Component\Console\Application;

/**
 * Console runner.
 *
 * @category VuFind
 * @package  Console
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class ConsoleRunner
{
    /**
     * List of commands
     *
     * @var array
     */
    protected $commands;

    /**
     * Plugin manager (to retrieve commands)
     *
     * @var ServiceManager
     */
    protected $pluginManager;

    /**
     * Constructor
     *
     * @param array          $config List of commands
     * @param ServiceManager $pm     Plugin manager (to retrieve commands)
     */
    public function __construct(array $commands, ServiceManager $pm)
    {
        $this->commands = $commands;
        $this->pluginManager = $pm;
    }

    /**
     * Run the console action
     *
     * @return mixed
     */
    public function run()
    {
        $consoleApp = new Application();
        foreach ($this->commands as $command) {
            $consoleApp->add($this->pluginManager->get($command));
        }
        return $consoleApp->run();
    }
}
