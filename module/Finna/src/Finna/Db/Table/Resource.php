<?php
/**
 * Table Definition for resource
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
 * @package  Db_Table
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
namespace Finna\Db\Table;

/**
 * Table Definition for resource
 *
 * @category VuFind2
 * @package  Db_Table
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
class Resource extends \VuFind\Db\Table\Resource
{
    /**
     * Get a set of records from the requested favorite list.
     *
     * @param string $user   ID of user owning favorite list
     * @param string $list   ID of list to retrieve (null for all favorites)
     * @param array  $tags   Tags to use for limiting results
     * @param string $sort   Resource table field to use for sorting (null for
     * no particular sort).
     * @param int    $offset Offset for results
     * @param int    $limit  Limit for results (null for none)
     *
     * @return \Zend\Db\ResultSet\AbstractResultSet
     */
    public function getFavorites($user, $list = null, $tags = [],
        $sort = null, $offset = 0, $limit = null
    ) {
        $sort = isset($_GET['sort']) ? $_GET['sort'] : false;
        if (!$sort) {
            $sort = 'saved';
        }
        if ($sort == 'format') {
            $translator = $this->getServiceLocator()->getTranslator();
        }

        $userResource = $this->getDbTable('UserResource');

        $favorites = parent::getFavorites(
            $user, $list, $tags, null, $offset, $limit
        );

        $favoritesSorted = [];
        foreach ($favorites as $fav) {
            $source = $fav->source;
            try {
                $driver = $this->getServiceLocator()->getServiceLocator()
                    ->get('VuFind\RecordLoader')->load($fav->record_id, $source);
            } catch (\Exception $e) {
                continue;
            }

            switch ($sort) {

            case 'title':
                $sortKey = $driver->getSortTitle();
                break;
            case 'author':
                $sortKey = $driver->getPrimaryAuthor();
                break;
            case 'date':
                if ($year = $driver->tryMethod('getYear', [])) {
                    $sortKey = $year;
                } else {
                    $years = $driver->getPublicationDates();
                    if (isset($years[0])) {
                        $sortKey = $years[0];
                    } else {
                        $sortKey = '';
                    }
                }
                break;
            case 'format':
                $formats = $driver->getFormats();
                $sortKey = count($formats)
                    ? $translator->translate(end($formats)) : '';
                break;
            default:
                $params = ['resource_id' => $fav->id, 'user_id' => $user];
                if ($list) {
                    $params['list_id'] = $list;
                }
                $res = $userResource->select($params)->current();
                $sortKey = $res->saved;
                break;
            }
            $sortKey = mb_strtolower($sortKey, 'UTF-8');
            $sortKey .= '_' . $fav->record_id;
            $favoritesSorted[$sortKey] = $fav;
        }

        ksort($favoritesSorted);
        return array_values($favoritesSorted);
    }
}
