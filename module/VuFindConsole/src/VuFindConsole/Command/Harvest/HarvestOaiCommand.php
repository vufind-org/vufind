<?php
/**
 * Console command: VuFind-specific customizations to OAI-PMH harvest command
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
namespace VuFindConsole\Command\Harvest;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Console command: VuFind-specific customizations to OAI-PMH harvest command
 *
 * @category VuFind
 * @package  Console
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class HarvestOaiCommand extends \VuFindHarvest\OaiPmh\HarvesterCommand
{
    /**
     * The name of the command
     *
     * @var string
     */
    protected static $defaultName = 'harvest/harvest_oai';

    /**
     * Warn the user if VUFIND_LOCAL_DIR is not set.
     *
     * @param OutputInterface $output Output object
     *
     * @return void
     */
    protected function checkLocalSetting(OutputInterface $output)
    {
        if (!getenv('VUFIND_LOCAL_DIR')) {
            $output->writeln(
                'WARNING: The VUFIND_LOCAL_DIR environment variable is not set.'
            );
            $output->writeln(
                'This should point to your local configuration directory (i.e.'
            );
            $output->writeln(realpath(APPLICATION_PATH . '/local') . ').');
            $output->writeln(
                'Without it, inappropriate default settings may be loaded.'
            );
            $output->writeln('');
        }
    }

    /**
     * Run the command.
     *
     * @param InputInterface  $input  Input object
     * @param OutputInterface $output Output object
     *
     * @return int 0 for success
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->checkLocalSetting($output);

        // Add the default --ini setting if missing:
        if (!$input->getOption('ini')) {
            $ini = \VuFind\Config\Locator::getConfigPath('oai.ini', 'harvest');
            $input->setOption('ini', $ini);
        }
        return parent::execute($input, $output);
    }
}
