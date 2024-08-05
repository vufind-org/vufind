<?php

/**
 * Console command: extend class.
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

namespace VuFindConsole\Command\Generate;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Console command: extend class.
 *
 * @category VuFind
 * @package  Console
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
#[AsCommand(
    name: 'generate/extendclass',
    description: 'Subclass generator'
)]
class ExtendClassCommand extends AbstractContainerAwareCommand
{
    /**
     * Configure the command.
     *
     * @return void
     */
    protected function configure()
    {
        $this
            ->setHelp('Subclasses a service, with lookup by class name.')
            ->addArgument(
                'class_name',
                InputArgument::REQUIRED,
                'the name of the class you wish to extend'
            )->addArgument(
                'target_module',
                InputArgument::REQUIRED,
                'the module where the new class will be generated'
            )->addOption(
                'extendfactory',
                null,
                InputOption::VALUE_NONE,
                'when set, subclass the factory; otherwise, use existing factory'
            );
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
        $class = $input->getArgument('class_name');
        $target = $input->getArgument('target_module');
        $extendFactory = $input->getOption('extendfactory');

        try {
            $this->generatorTools->setOutputInterface($output);
            $this->generatorTools->extendClass(
                $this->container,
                $class,
                $target,
                $extendFactory
            );
        } catch (\Exception $e) {
            $output->writeln($e->getMessage());
            return 1;
        }

        return 0;
    }
}
