<?php

/**
 * Console command: VuFind-specific customizations to OAI-PMH harvest command
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

namespace VuFindConsole\Command\Harvest;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use VuFind\Config\PathResolver;
use VuFindHarvest\OaiPmh\HarvesterFactory;

/**
 * Console command: VuFind-specific customizations to OAI-PMH harvest command
 *
 * @category VuFind
 * @package  Console
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
#[AsCommand(
    name: 'harvest/harvest_oai',
    description: 'OAI-PMH harvester'
)]
class HarvestOaiCommand extends \VuFindHarvest\OaiPmh\HarvesterCommand
{
    /**
     * Config file path resolver
     *
     * @var PathResolver
     */
    protected $pathResolver;

    /**
     * Constructor
     *
     * @param Client           $client       HTTP client (omit for default)
     * @param string           $harvestRoot  Root directory for harvesting (omit for
     * default)
     * @param HarvesterFactory $factory      Harvester factory (omit for default)
     * @param bool             $silent       Should we suppress output?
     * @param string|null      $name         The name of the command; passing null
     * means it must be set in configure()
     * @param PathResolver     $pathResolver Config file path resolver
     */
    public function __construct(
        $client = null,
        $harvestRoot = null,
        HarvesterFactory $factory = null,
        $silent = false,
        $name = null,
        PathResolver $pathResolver = null
    ) {
        parent::__construct($client, $harvestRoot, $factory, $silent, $name);
        $this->pathResolver = $pathResolver;
    }

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
            $ini = $this->pathResolver
                ? $this->pathResolver->getConfigPath('oai.ini', 'harvest')
                : \VuFind\Config\Locator::getConfigPath('oai.ini', 'harvest');
            $input->setOption('ini', $ini);
        }
        return parent::execute($input, $output);
    }
}
