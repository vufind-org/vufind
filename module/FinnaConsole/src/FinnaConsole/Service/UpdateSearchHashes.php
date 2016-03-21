<?php
/**
 * Console service for updating search hashes.
 *
 * PHP version 5
 *
 * Copyright (C) The National Library of Finland 2015-2016.
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
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 *
 * @category VuFind
 * @package  Service
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:developer_manual Wiki
 */
namespace FinnaConsole\Service;
use Zend\Db\Sql\Select;

/**
 * Console service for anonymizing expired user accounts.
 *
 * @category VuFind
 * @package  Service
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:developer_manual Wiki
 */
class UpdateSearchHashes extends AbstractService
{
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
    protected $manager;

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
        $this->manager = $manager;
    }

    /**
     * Run service.
     *
     * @param array $arguments Command line arguments.
     *
     * @return boolean success
     */
    public function run($arguments)
    {
        if (!isset($arguments[0]) || $arguments[0] != 'Y') {
            echo "Usage:\n  php index.php util update_search_hashes Y\n\n"
                . "  Update hashes of saved searches.\n";
            return false;
        }

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
                    $searchObj = $minified->deminify($this->manager);
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
