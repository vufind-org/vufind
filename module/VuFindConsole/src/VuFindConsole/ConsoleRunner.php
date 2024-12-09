<?php

/**
 * Console runner.
 *
 * PHP version 8
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

use Psr\Container\ContainerInterface;
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
     * @var ContainerInterface
     */
    protected $pluginManager;

    /**
     * Constructor
     *
     * @param ContainerInterface $pm Plugin manager (to retrieve commands)
     */
    public function __construct(ContainerInterface $pm)
    {
        $this->pluginManager = $pm;
    }

    /**
     * Get the command or list of commands to run.
     *
     * @return array
     */
    protected function getCommandList()
    {
        // Does the first argument match a command alias? If so, load only that:
        if ($this->pluginManager->has($_SERVER['argv'][1] ?? '')) {
            return [$_SERVER['argv'][1]];
        }

        // Do the first two arguments match a command alias? If so, manipulate
        // the arguments (converting legacy format to Symfony format) and return
        // the resulting command:
        $command = ($_SERVER['argv'][1] ?? '') . '/' . ($_SERVER['argv'][2] ?? '');
        if ($this->pluginManager->has($command)) {
            $_SERVER['argc']--;
            array_splice($_SERVER['argv'], 1, 2, [$command]);
            return [$command];
        }

        // Default behavior: return all values
        return $this->pluginManager->getCommandList();
    }

    /**
     * Run the console action
     *
     * @return mixed
     */
    public function run()
    {
        // Get command list before initializing Application, since we may need
        // to manipulate $_SERVER for backward compatibility.
        $commands = $this->getCommandList();

        // Launch Symfony:
        $consoleApp = new Application();
        foreach ($commands as $command) {
            $consoleApp->add($this->pluginManager->get($command));
        }
        return $consoleApp->run();
    }
}
