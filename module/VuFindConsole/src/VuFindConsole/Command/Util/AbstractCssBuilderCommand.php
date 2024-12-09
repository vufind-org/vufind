<?php

/**
 * Abstract console command: build CSS with precompiler.
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

namespace VuFindConsole\Command\Util;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Abstract console command: build CSS with precompiler.
 *
 * @category VuFind
 * @package  Console
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
abstract class AbstractCssBuilderCommand extends Command
{
    /**
     * Cache directory for compiler
     *
     * @var string
     */
    protected $cacheDir;

    /**
     * Name of precompiler format
     *
     * @var string
     */
    protected $format;

    /**
     * Constructor
     *
     * @param string      $cacheDir Cache directory for compiler
     * @param string|null $name     The name of the command; passing null means it
     * must be set in configure()
     */
    public function __construct($cacheDir, $name = null)
    {
        $this->cacheDir = $cacheDir;
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
            ->setHelp('Compiles CSS files from ' . $this->format . '.')
            ->addArgument(
                'themes',
                InputArgument::IS_ARRAY | InputArgument::OPTIONAL,
                'Name of theme(s) to compile; omit to compile everything'
            );
    }

    /**
     * Build the compiler.
     *
     * @param OutputInterface $output Output object
     *
     * @return \VuFindTheme\AbstractCssPreCompiler
     */
    abstract protected function getCompiler(OutputInterface $output);

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
        $compiler = $this->getCompiler($output);
        $compiler->setTempPath($this->cacheDir);
        $compiler->compile(array_unique($input->getArgument('themes')));
        return 0;
    }
}
