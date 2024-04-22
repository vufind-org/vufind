<?php

/**
 * Console command: Compile themes.
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

namespace VuFindConsole\Command\Compile;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use VuFindTheme\ThemeCompiler;

/**
 * Console command: Compile themes.
 *
 * @category VuFind
 * @package  Console
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
#[AsCommand(
    name: 'compile/theme',
    description: 'Theme compiler'
)]
class ThemeCommand extends Command
{
    /**
     * Theme compiler
     *
     * @var ThemeCompiler
     */
    protected $compiler;

    /**
     * Constructor
     *
     * @param ThemeCompiler $compiler Theme compiler
     * @param string|null   $name     The name of the command; passing null means it
     * must be set in configure()
     */
    public function __construct(ThemeCompiler $compiler, $name = null)
    {
        $this->compiler = $compiler;
        parent::__construct($name);
    }

    /**
     * Configure the command.
     *
     * @return void
     */
    protected function configure()
    {
        $this
            ->setHelp('Flattens a theme hierarchy for improved performance.')
            ->addArgument(
                'source',
                InputArgument::REQUIRED,
                'the source theme to compile'
            )->addArgument(
                'target',
                InputArgument::OPTIONAL,
                'the target name for the compiled theme '
                . '(defaults to <source> with _compiled appended)'
            )->addOption(
                'force',
                'f',
                InputOption::VALUE_NONE,
                'If <target> exists, it will only be overwritten when this is set'
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
        $source = $input->getArgument('source');
        $target = $input->getArgument('target');
        if (empty($target)) {
            $target = "{$source}_compiled";
        }
        $force = $input->getOption('force') ? true : false;
        if (!$this->compiler->compile($source, $target, $force)) {
            $output->writeln($this->compiler->getLastError());
            return 1;
        }
        $output->writeln('Success.');
        return 0;
    }
}
