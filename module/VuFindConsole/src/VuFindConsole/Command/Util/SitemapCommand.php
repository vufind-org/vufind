<?php

/**
 * Console command: generate sitemaps
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

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use VuFind\Sitemap\Generator;

/**
 * Console command: generate sitemaps
 *
 * @category VuFind
 * @package  Console
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
#[AsCommand(
    name: 'util/sitemap',
    description: 'XML sitemap generator'
)]
class SitemapCommand extends Command
{
    /**
     * Sitemap generator
     *
     * @var Generator
     */
    protected $generator;

    /**
     * Constructor
     *
     * @param Generator   $generator Sitemap generator
     * @param string|null $name      The name of the command; passing null means it
     * must be set in configure()
     */
    public function __construct(Generator $generator, $name = null)
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
            ->setHelp('Generates XML sitemap files.')
            ->addOption(
                'baseurl',
                null,
                InputOption::VALUE_REQUIRED,
                'Base URL (overrides the url setting in Site section of config.ini)'
            )->addOption(
                'basesitemapurl',
                null,
                InputOption::VALUE_REQUIRED,
                'Base sitemap URL (overrides the url setting in Site section of '
                . 'config.ini, or baseSitemapUrl in sitemap.ini)'
            )->addOption(
                'filelocation',
                null,
                InputOption::VALUE_REQUIRED,
                'Output path (overrides the fileLocation setting in sitemap.ini)'
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
        if ($input->hasOption('verbose') && $input->getOption('verbose')) {
            $this->generator->setVerbose([$output, 'writeln']);
        }
        if ($url = $input->getOption('baseurl')) {
            $this->generator->setBaseUrl($url);
        }
        if ($sitemapUrl = $input->getOption('basesitemapurl')) {
            $this->generator->setBaseSitemapUrl($sitemapUrl);
        }
        if ($fileLocation = $input->getOption('filelocation')) {
            $this->generator->setFileLocation($fileLocation);
        }
        $this->generator->generate();
        foreach ($this->generator->getWarnings() as $warning) {
            $output->writeln("$warning");
        }
        return 0;
    }
}
