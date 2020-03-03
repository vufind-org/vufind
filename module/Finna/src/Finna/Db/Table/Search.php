<?php
/**
 * Table Definition for search
 *
 * PHP version 7
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
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
 *
 * @category VuFind
 * @package  Db_Table
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
namespace Finna\Db\Table;

/**
 * Table Definition for search
 *
 * @category VuFind
 * @package  Db_Table
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
class Search extends \VuFind\Db\Table\Search
{
    /**
     * Get distinct view URLs with scheduled alerts.
     *
     * @return array URLs
     */
    public function getScheduleBaseUrls()
    {
        $sql
            = "SELECT distinct notification_base_url as url FROM {$this->table}"
            . " WHERE notification_base_url != '';";

        $result = $this->getAdapter()->query(
            $sql,
            \Zend\Db\Adapter\Adapter::QUERY_MODE_EXECUTE
        );
        $urls = [];
        foreach ($result as $res) {
            $urls[] = $res['url'];
        }
        return $urls;
    }

    /**
     * Get scheduled searches.
     *
     * @param string $baseUrl View URL
     *
     * @return array Array of Finna\Db\Row\Search objects.
     */
    public function getScheduledSearches($baseUrl = '')
    {
        $callback = function ($select) use ($baseUrl) {
            $select->columns(['*']);
            $select->where->equalTo('saved', 1);
            $select->where('notification_frequency > 0');
            $select->where->equalTo('notification_base_url', $baseUrl);
            $select->order('user_id');
        };

        return $this->select($callback);
    }

    /**
     * Get saved searches.
     *
     * @param int $uid User ID
     *
     * @return \Zend\Db\ResultSet\ResultSet
     */
    public function getSavedSearches($uid)
    {
        $callback = function ($select) use ($uid) {
            $select->where->equalTo('user_id', $uid);
            $select->where->equalTo('saved', 1);
            $select->order('id');
        };
        return $this->select($callback);
    }
}
