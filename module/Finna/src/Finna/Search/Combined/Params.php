<?php
/**
 * Combined aspect of the Search Multi-class (Params)
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
 * @package  Search_Base
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
namespace Finna\Search\Combined;

/**
 * Combined Search Parameters
 *
 * @category VuFind
 * @package  Search_Base
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
class Params extends \Finna\Search\Solr\Params
{
    protected $combinedSearches = null;

    /**
     * Set active search ids in combined results view.
     *
     * @param array $ids Array of searchClass => searchId elements.
     *
     * @return void
     */
    public function setCombinedSearchIds($ids)
    {
        $this->combinedSearches = $ids;
    }

    /**
     * Get active search id in combined results view.
     *
     * @param string $backend Search class
     *
     * @return array Array of searchClass => searchId elements.
     */
    public function getCombinedSearchId($backend)
    {
        return isset($this->combinedSearches[$backend])
            ? $this->combinedSearches[$backend] : null
        ;
    }

    /**
     * Get all active search ids in combined results view.
     *
     * @return array Array of searchClass => searchId elements.
     */
    public function getCombinedSearchIds()
    {
        return $this->combinedSearches;
    }
}
