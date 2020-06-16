<?php
/**
 * Console service for updating search hashes.
 *
 * PHP version 7
 *
 * Copyright (C) The National Library of Finland 2015-2020.
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
 * @package  Service
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:developer_manual Wiki
 */
namespace FinnaConsole\Command\Util;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Console service for updating search hashes.
 *
 * @category VuFind
 * @package  Service
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:developer_manual Wiki
 */
class UpdateSearchHashes extends AbstractUtilCommand
{
    /**
     * The name of the command (the part after "public/index.php")
     *
     * @var string
     */
    protected static $defaultName = 'util/update_search_hashes';

    /**
     * Table for searches
     *
     * @var \VuFind\Db\Table\Search
     */
    protected $table;

    /**
     * Search results plugin manager
     *
     * @var \VuFind\Search\Results\PluginManager
     */
    protected $resultsManager;

    /**
     * Constructor
     *
     * @param \VuFind\Db\Table\Search              $table   Search table.
     * @param \VuFind\Search\Results\PluginManager $manager Search results manager.
     */
    public function __construct(
        \VuFind\Db\Table\Search $table, \VuFind\Search\Results\PluginManager $manager
    ) {
        $this->table = $table;
        $this->resultsManager = $manager;

        parent::__construct();
    }

    /**
     * Configure the command.
     *
     * @return void
     */
    protected function configure()
    {
        $this->setDescription('Update hashes of saved searches');
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
        $searchWhere = ['checksum' => null, 'saved' => 1];
        $searchRows = $this->table->select($searchWhere);
        if (count($searchRows) > 0) {
            $count = 0;
            $orFilterCount = 0;
            foreach ($searchRows as $searchRow) {
                try {
                    $minified = $searchRow->getSearchObject();
                    if (!empty($minified->o)) {
                        // Fix orFilters while at it
                        if (!isset($minified->f)) {
                            $minified->f = [];
                        }
                        foreach ($minified->o as $field => $orFilters) {
                            foreach ($orFilters as $orFilter) {
                                $minified->f["~$field"][] = $orFilter;
                            }
                        }
                        unset($minified->o);
                        ++$orFilterCount;
                        $searchRow->search_object = serialize($minified);
                    }
                    $searchObj = $minified->deminify($this->resultsManager);
                    $url = $searchObj->getUrlQuery()->getParams();
                    $checksum = crc32($url) & 0xFFFFFFF;
                    $searchRow->checksum = $checksum;
                    $searchRow->save();
                } catch (\Exception $e) {
                    echo "Failed to process search {$searchRow->id}: "
                        . $e->getMessage() . "\n";
                    print_r($searchRow->getSearchObject());
                    continue;
                }
                ++$count;
            }
            echo "Added checksum to $count rows and converted orFilters in"
                . " $orFilterCount rows in search table\n";
        } else {
            echo "No saved rows without hash found\n";
        }
        return true;
    }
}
