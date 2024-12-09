<?php

/**
 * Console output trait (used to add output support to other classes).
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

use Symfony\Component\Console\Output\OutputInterface;

/**
 * Console output trait (used to add output support to other classes).
 *
 * @category VuFind
 * @package  Console
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
trait ConsoleOutputTrait
{
    /**
     * Output interface.
     *
     * @var OutputInterface
     */
    protected $outputInterface = null;

    /**
     * Set the output interface. Implements a fluent interface.
     *
     * @param OutputInterface $output Output interface
     *
     * @return mixed
     */
    public function setOutputInterface(OutputInterface $output)
    {
        $this->outputInterface = $output;
        return $this;
    }

    /**
     * Write a line to the output (if available). Implements a fluent interface.
     *
     * @param string $output Line to output.
     *
     * @return mixed
     */
    public function writeln(string $output)
    {
        if ($this->outputInterface) {
            $this->outputInterface->writeln($output);
        }
        return $this;
    }
}
