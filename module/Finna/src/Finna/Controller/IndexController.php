<?php
/**
 * Default Controller
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
 * @category VuFind2
 * @package  Controller
 * @author   Mika Hatakka <mika.hatakka@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:developer_manual Wiki
 */

namespace Finna\Controller;

/**
 * For adding our needed data.
 *
 * @category VuFind2
 * @package  Controller
 * @author   Mika Hatakka <mika.hatakka@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:developer_manual Wiki
 */
class IndexController extends \VuFind\Controller\IndexController
{

    /**
     * Add the total count of indexed material to home page. One hour cache.
     *
     * @return mixed
     */
    public function homeAction()
    {
        $view = parent::homeAction();

        $cacheDir = $this->getServiceLocator()->get('VuFind\CacheManager')
            ->getCacheDir();
        // Cache file for number of records in index
        $filename = $cacheDir . 'recordcount.txt';
        $hourOld = time() - (60 * 60);
        $fileTime = filemtime($filename);

        if ($fileTime && $fileTime > $hourOld) {
            $indexResultTotal = file_get_contents($filename);
        } else {
            $indexResultTotal = $this->_getEmptySearch();
            file_put_contents($filename, $indexResultTotal);
        }
        $view->indexResultTotal = $indexResultTotal;

        return $view;
    }

    /**
     * Make a empty search to solr to get total count of indexed materials.
     *
     * @return int count of total indexed materials
     */
    private function _getEmptySearch()
    {
        $resultsManager = $this->getServiceLocator()
            ->get('VuFind\SearchResultsPluginManager');
        $results = $resultsManager->get('Solr');
        try {
            $results->performAndProcessSearch();
            $totalIndexed = $results->getResultTotal();
        } catch (\VuFindSearch\Backend\Exception\BackendException $e) {
            $totalIndexed = 0;
        }
        return $totalIndexed;
    }

}
