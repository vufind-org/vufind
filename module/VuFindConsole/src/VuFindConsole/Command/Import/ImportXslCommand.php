<?php

/**
 * Console command: XSLT importer
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

namespace VuFindConsole\Command\Import;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use VuFind\XSLT\Importer;

use function is_callable;

/**
 * Console command: XSLT importer
 *
 * @category VuFind
 * @package  Console
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
#[AsCommand(
    name: 'import/import-xsl',
    description: 'XSLT importer'
)]
class ImportXslCommand extends Command
{
    /**
     * XSLT importer
     *
     * @var Importer
     */
    protected $importer;

    /**
     * Constructor
     *
     * @param Importer    $importer XSLT importer
     * @param string|null $name     The name of the command; passing null means it
     * must be set in configure()
     */
    public function __construct(Importer $importer, $name = null)
    {
        $this->importer = $importer;
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
            ->setHelp('Indexes XML into Solr using XSLT.')
            ->addArgument(
                'XML_file',
                InputArgument::REQUIRED,
                'source file to index'
            )->addArgument(
                'properties_file',
                InputArgument::REQUIRED,
                'import configuration file ($VUFIND_LOCAL_DIR/import and '
                . ' $VUFIND_HOME/import will'
                . "\nbe searched for this filename; see ojs.properties "
                . 'for configuration examples)'
            )->addOption(
                'test-only',
                null,
                InputOption::VALUE_NONE,
                'activates test mode, which displays transformed XML without '
                . 'updating Solr'
            )->addOption(
                'index',
                null,
                InputOption::VALUE_OPTIONAL,
                'name of search backend to index content into (could be overridden '
                . "with,\nfor example, SolrAuth to index authority records)",
                'Solr'
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
        $testMode = $input->getOption('test-only') ? true : false;
        $index = $input->getOption('index');
        $xml = $input->getArgument('XML_file');
        $properties = $input->getArgument('properties_file');
        // Try to import the document if successful:
        try {
            $result = $this->importer->save($xml, $properties, $index, $testMode);
            if ($testMode) {
                $output->writeln($result);
            }
        } catch (\Exception $e) {
            $output->writeln('Fatal error: ' . $e->getMessage());
            if (is_callable([$e, 'getPrevious']) && $e = $e->getPrevious()) {
                while ($e) {
                    $output->writeln('Previous exception: ' . $e->getMessage());
                    $e = $e->getPrevious();
                }
            }
            return 1;
        }
        if (!$testMode) {
            $output->writeln("Successfully imported $xml...");
        }
        return 0;
    }
}
