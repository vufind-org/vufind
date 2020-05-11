<?php
/**
 * Console output trait (used to add output support to other classes).
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
     * Set the output interface.
     *
     * @param OutputInterface $output Output interface
     *
     * @return void
     */
    public function setOutputInterface(OutputInterface $output): void
    {
        $this->outputInterface = $output;
    }

    /**
     * Write a line to the output (if available).
     *
     * @param string $output Line to output.
     *
     * @return void
     */
    public function writeln(string $output): void
    {
        if ($this->outputInterface) {
            $this->outputInterface->writeln($output);
        }
    }
}
