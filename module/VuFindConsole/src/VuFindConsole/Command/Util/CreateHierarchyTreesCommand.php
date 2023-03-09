<?php
/**
 * Generic base class for Solr commands.
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
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use VuFind\Record\Loader;
use VuFind\Search\Results\PluginManager;

/**
 * Generic base class for Solr commands.
 *
 * @category VuFind
 * @package  Console
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class CreateHierarchyTreesCommand extends Command
{
    /**
     * The name of the command (the part after "public/index.php")
     *
     * @var string
     */
    protected static $defaultName = 'util/createHierarchyTrees';

    /**
     * Record loader
     *
     * @var Loader
     */
    protected $recordLoader;

    /**
     * Search results manager
     *
     * @var PluginManager
     */
    protected $resultsManager;

    /**
     * Constructor
     *
     * @param Loader        $loader  Record loader
     * @param PluginManager $results Search results manager
     * @param string|null   $name    The name of the command; passing null means it
     * must be set in configure()
     */
    public function __construct(Loader $loader, PluginManager $results, $name = null)
    {
        $this->recordLoader = $loader;
        $this->resultsManager = $results;
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
            ->setDescription('Cache populator for hierarchies')
            ->setHelp('Populates the hierarchy tree cache.')
            ->addArgument(
                'backend',
                InputArgument::OPTIONAL,
                'Search backend, e.g. ' . DEFAULT_SEARCH_BACKEND
                . ' (default) or Search2',
                DEFAULT_SEARCH_BACKEND
            )->addOption(
                'skip',
                's',
                InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY,
                'format(s) to skip caching (x = xml, j = json)'
            )->addOption(
                'skip-xml',
                null,
                InputOption::VALUE_NONE,
                'skip the XML cache (synonymous with -sx)'
            )->addOption(
                'skip-json',
                null,
                InputOption::VALUE_NONE,
                'skip the JSON cache (synonymous with -sj)'
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
        $skips = $input->getOption('skip') ?? [];
        $skipJson = $input->getOption('skip-json') || in_array('j', $skips);
        $skipXml = $input->getOption('skip-xml') || in_array('x', $skips);
        $backendId = $input->getArgument('backend');
        $hierarchies = $this->resultsManager->get($backendId)
            ->getFullFieldFacets(['hierarchy_top_id']);
        $list = $hierarchies['hierarchy_top_id']['data']['list'] ?? [];
        foreach ($list as $hierarchy) {
            $recordid = $hierarchy['value'];
            $count = $hierarchy['count'];
            if (empty($recordid)) {
                continue;
            }
            $output->writeln(
                "\tBuilding tree for " . $recordid . '... '
                . number_format($count) . ' records'
            );
            try {
                $driver = $this->recordLoader->load($recordid, $backendId);
                // Only do this if the record is actually a hierarchy type record
                if ($driver->getHierarchyType()) {
                    // JSON
                    if (!$skipJson) {
                        $output->writeln("\t\tJSON cache...");
                        $driver->getHierarchyDriver()->getTreeSource()->getJSON(
                            $recordid,
                            ['refresh' => true]
                        );
                    } else {
                        $output->writeln("\t\tJSON skipped.");
                    }
                    // XML
                    if (!$skipXml) {
                        $output->writeln("\t\tXML cache...");
                        $driver->getHierarchyDriver()->getTreeSource()->getXML(
                            $recordid,
                            ['refresh' => true]
                        );
                    } else {
                        $output->writeln("\t\tXML skipped.");
                    }
                }
            } catch (\VuFind\Exception\RecordMissing $e) {
                $output->writeln(
                    'WARNING! - Caught exception: ' . $e->getMessage() . "\n"
                );
            }
        }
        $output->writeln(
            count($hierarchies['hierarchy_top_id']['data']['list']) . ' files'
        );

        return 0;
    }
}
