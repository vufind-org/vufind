<?php
/**
 * View helper for remembering recent user searches/parameters.
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
 * @package  View_Helpers
 * @author   Anna Niku <anna.niku@gofore.com>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
namespace Finna\View\Helper\Root;

/**
 * View helper for remembering recent user searches/parameters.
 *
 * @category Finna
 * @package  View_Helpers
 * @author   Anna Niku <anna.niku@gofore.com>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class SearchMemory extends \VuFind\View\Helper\Root\SearchMemory
{
    /**
     * Retrieve the last search id
     *
     * @return string
     */
    public function getLastSearchId()
    {
        $searchData =  $this->memory->retrieveLastSearchData();
        return $searchData->id;
    }

    /**
     * Retrieve the last search type
     *
     * @return string
     */
    public function getLastSearchType()
    {
        $searchData =  $this->memory->retrieveLastSearchData();
        return $searchData->type;
    }

    /**
     * Retrieve the last search lookfor
     *
     * @return string
     */
    public function getLastSearchLookfor()
    {
        $searchData =  $this->memory->retrieveLastSearchData();
        return $searchData->lookfor;
    }

    /**
     * Retrieve the last search url
     *
     * @return string
     */
    public function getLastSearchUrl()
    {
        return $this->memory->retrieveSearch();
    }
}
