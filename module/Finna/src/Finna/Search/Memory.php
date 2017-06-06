<?php
/**
 * Finna Search Memory
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
 * @category Finna
 * @package  Search
 * @author   Anna Niku <anna.niku@gofore.com>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
namespace Finna\Search;

/**
 * Wrapper class to handle search memory
 *
 * @category Finna
 * @package  Search
 * @author   Anna Niku <anna.niku@gofore.com>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
class Memory extends \VuFind\Search\Memory
{
    /**
     * Remember last search Id and type
     *
     * @param string $searchId   Last search Id.
     * @param string $searchType last search type.
     * @param string $lookfor    last search lookfor.
     *
     * @return void
     */
    public function rememberSearchData($searchId, $searchType, $lookfor)
    {
        $this->session->searchData = (object) [
            'id' => $searchId,
            'type' => $searchType,
            'lookfor' => $lookfor
        ];
    }

    /**
     * Retrieve a previous search data (id and type)
     *
     * @return object
     */
    public function retrieveLastSearchData()
    {
        return isset($this->session->searchData) ? $this->session->searchData : null;
    }
}
