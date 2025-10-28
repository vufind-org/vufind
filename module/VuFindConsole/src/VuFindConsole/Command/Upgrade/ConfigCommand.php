<?php

/**
 * Console command: configuration upgrader
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2025.
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
 * along with this program; if not, see
 * <https://www.gnu.org/licenses/>.
 *
 * @category VuFind
 * @package  Console
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */

namespace VuFindConsole\Command\Upgrade;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use VuFind\Config\Upgrade;
use VuFind\Config\Version;

/**
 * Console command: configuration upgrader
 *
 * @category VuFind
 * @package  Console
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
#[AsCommand(
    name: 'upgrade/config',
    description: 'Configuration upgrader'
)]
class ConfigCommand extends Command
{
    /**
     * Constructor
     *
     * @param Upgrade $upgrader Configuration upgrader
     * @param ?string $name     The name of the command; passing null means it must be set in configure()
     */
    public function __construct(protected Upgrade $upgrader, $name = null)
    {
        parent::__construct($name);
    }

    /**
     * Configure the command.
     *
     * @return void
     */
    protected function configure()
    {
        $this->setHelp('Upgrade the configuration.');
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
        try {
            $this->upgrader->run(Version::getBuildVersion());
        } catch (\Exception $e) {
            $output->writeln((string)$e);
            return Command::FAILURE;
        }
        foreach ($this->upgrader->getWarnings() as $warning) {
            $output->writeln($warning);
        }
        $output->writeln('Configuration upgrade successful! Please review your configurations.');
        $output->writeln('The automatic update process sometimes re-enables disabled settings and removes comments.');
        $output->writeln('Backups of your old configurations have been created for comparison purposes.');
        return Command::SUCCESS;
    }
}
