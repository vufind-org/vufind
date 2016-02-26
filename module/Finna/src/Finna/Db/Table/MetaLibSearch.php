<?php
/**
 * Table Definition for search
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
 * @package  Db_Table
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
namespace Finna\Db\Table;

/**
 * Table Definition for MetaLib search
 *
 * @category VuFind
 * @package  Db_Table
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
class MetaLibSearch extends \VuFind\Db\Table\Gateway
{
    /**
     * Constructor
     */
    public function __construct()
    {
        parent::__construct('finna_metalib_search', 'Finna\Db\Row\MetaLibSearch');
    }

    /**
     * Add a MetaLib search into the search table (history)
     *
     * @param array  $results  Search results
     * @param string $searchId Search hash
     *
     * @return void
     */
    public function saveMetaLibSearch($results, $searchId)
    {
        if ($this->getRowBySearchId($searchId)) {
            return;
        }
        $result = $this->createRow();
        $result->finna_search_id = $searchId;
        $result->search_object = serialize($results);
        $result->created = date('Y-m-d H:i:s');
        $result->save();
    }

    /**
     * Get a search object by search hash.
     *
     * @param string $hash Hash
     *
     * @throws \Exception
     * @return \Finna\Db\Row\MetaLibSearch
     */
    public function getRowBySearchId($hash)
    {
        return $this->select(['finna_search_id' => $hash])->current();
    }
}
