<?php

/**
 * Count of all indexed items view helper
 *
 * PHP version 5
 *
 * Copyright (C) The National Library of Finland 2015.
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
 * @package  View_Helpers
 * @author   Mika Hatakka <mika.hatakka@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:developer_manual Wiki
 */
namespace Finna\View\Helper\Root;

/**
 * Count of all indexed items view helper
 *
 * @category VuFind
 * @package  View_Helpers
 * @author   Mika Hatakka <mika.hatakka@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:developer_manual Wiki
 */
class TotalIndexed extends \Zend\View\Helper\AbstractHelper
{
    protected $serviceLocator;

    /**
     * Constructor
     *
     * @param type $serviceLocator Service locator
     */
    public function __construct($serviceLocator)
    {
        $this->serviceLocator = $serviceLocator;
    }

    /**
     * Total item count in index.
     *
     * @return int count of indexed items or 0 if no information
     */
    public function getTotalIndexedCount()
    {
        $cacheDir = $this->serviceLocator->get('VuFind\CacheManager')
            ->getCacheDir();
        // Cache file for number of records in index
        $filename = $cacheDir . 'recordcount.txt';
        $hourOld = time() - (60 * 60);
        $fileTime = false;
        if (file_exists($filename)) {
            $fileTime = filemtime($filename);
        }

        if ($fileTime && $fileTime > $hourOld) {
            $totalIndexed = file_get_contents($filename);
        } else {
            $resultsManager = $this->serviceLocator
                ->get('VuFind\SearchResultsPluginManager');
            $results = $resultsManager->get('Solr');
            try {
                $results->performAndProcessSearch();
                $totalIndexed = $results->getResultTotal();
            } catch (\VuFindSearch\Backend\Exception\BackendException $e) {
                $totalIndexed = 0;
            }
            file_put_contents($filename, $totalIndexed);
        }
        return $totalIndexed;
    }

}
