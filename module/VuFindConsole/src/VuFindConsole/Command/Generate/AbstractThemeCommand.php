<?php

/**
 * Abstract base class for theme resource generator commands.
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

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use VuFindTheme\GeneratorInterface;

/**
 * Abstract base class for theme resource generator commands.
 *
 * @category VuFind
 * @package  Console
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
abstract class AbstractThemeCommand extends Command
{
    /**
     * Theme resource generator
     *
     * @var GeneratorInterface
     */
    protected $generator;

    /**
     * Type of resource being generated (used in help messages)
     *
     * @var string
     */
    protected $type;

    /**
     * Extra text to append to the output when generation is successful.
     *
     * @var string
     */
    protected $extraSuccessMessage = '';

    /**
     * Constructor
     *
     * @param GeneratorInterface $generator Generator to call
     * @param string|null        $name      The name of the command; passing null
     * means it must be set in configure()
     */
    public function __construct(GeneratorInterface $generator, $name = null)
    {
        $this->generator = $generator;
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
            ->setDescription(ucwords($this->type) . ' generator')
            ->setHelp('Creates and configures a new ' . $this->type . '.')
            ->addArgument(
                'name',
                InputArgument::OPTIONAL,
                'name of ' . $this->type
                . ' to generate. Defaults to custom  if unspecified.'
            );
    }

    /**
     * Run the generator.
     *
     * @param string $name Name of resource to generate
     *
     * @return bool
     */
    protected function generate($name)
    {
        return $this->generator->generate($name);
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
        $name = $input->getArgument('name');
        if (empty($name)) {
            $output->writeln("\tNo {$this->type} name provided, using \"custom\"");
            $name = 'custom';
        }

        $this->generator->setOutputInterface($output);

        if (!$this->generate($name)) {
            $output->writeln($this->generator->getLastError());
            return 1;
        }
        $output->writeln(rtrim("\tFinished. {$this->extraSuccessMessage}"));
        return 0;
    }
}
