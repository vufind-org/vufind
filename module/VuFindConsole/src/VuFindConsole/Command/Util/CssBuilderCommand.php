<?php
/**
 * Console command: build CSS.
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
namespace VuFindConsole\Command\Util;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use VuFindTheme\LessCompiler;

/**
 * Console command: build CSS.
 *
 * @category VuFind
 * @package  Console
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class CssBuilderCommand extends Command
{
    /**
     * The name of the command (the part after "public/index.php")
     *
     * @var string
     */
    protected static $defaultName = 'util/cssBuilder';

    /**
     * Cache directory for compiler
     *
     * @var string
     */
    protected $cacheDir;

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
            ->setDescription('LESS compiler')
            ->setHelp('Compiles CSS files from LESS.')
            ->addArgument(
                'themes',
                InputArgument::IS_ARRAY | InputArgument::OPTIONAL,
                'Name of theme(s) to compile; omit to compile everything'
            );
    }

    /**
     * Build the LESS compiler.
     *
     * @param OutputInterface $output Output object
     *
     * @return LessCompiler
     */
    protected function getCompiler(OutputInterface $output)
    {
        return new LessCompiler($output);
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
        $compiler = $this->getCompiler($output);
        $compiler->setTempPath($this->cacheDir);
        $compiler->compile(array_unique($input->getArgument('themes')));
        return 0;
    }
}
