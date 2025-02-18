<?php

/**
 * Generic base class for Solr commands.
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
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use VuFind\Record\Loader;
use VuFind\Search\Results\PluginManager;

use function count;

/**
 * Generic base class for Solr commands.
 *
 * @category VuFind
 * @package  Console
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
#[AsCommand(
    name: 'util/createHierarchyTrees',
    description: 'Cache populator for hierarchies'
)]
class CreateHierarchyTreesCommand extends Command
{
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
            ->setHelp('Populates the hierarchy tree cache.')
            ->addArgument(
                'backend',
                InputArgument::OPTIONAL,
                'Search backend, e.g. ' . DEFAULT_SEARCH_BACKEND
                . ' (default) or Search2',
                DEFAULT_SEARCH_BACKEND
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
                    $driver->getHierarchyDriver()->getTreeSource()->getJSON(
                        $recordid,
                        ['refresh' => true]
                    );
                }
            } catch (\VuFind\Exception\RecordMissing $e) {
                $output->writeln(
                    'WARNING! - Caught exception: ' . $e->getMessage() . "\n"
                );
            }
        }
        $output->writeln(count($list) . ' files');

        return 0;
    }
}
