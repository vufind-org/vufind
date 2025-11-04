<?php

/**
 * View helper for remembering recent user searches/parameters.
 *
 * PHP version 8
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
 * along with this program; if not, see
 * <https://www.gnu.org/licenses/>.
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
     * Retrieve the last search class id
     *
     * @return string
     */
    public function getLastSearchClassId()
    {
        $search = $this->memory->getLastSearch();
        return $search ? $search->getBackendId() : '';
    }

    /**
     * Retrieve the last search type
     *
     * @return string
     */
    public function getLastSearchType()
    {
        $search = $this->memory->getLastSearch();
        return $search ? $search->getParams()->getSearchType() : '';
    }

    /**
     * Retrieve the last search lookfor
     *
     * @return string
     */
    public function getLastSearchLookfor()
    {
        $search = $this->memory->getLastSearch();
        return (null == $search || $search->getUrlQuery()->isQuerySuppressed())
            ? '' : $search->getParams()->getDisplayQuery();
    }

    /**
     * Retrieve the scroll data
     *
     * @return array
     *
     * @deprecated For template back-compatibility only
     */
    public function getLastScrollData()
    {
        return [];
    }
}
