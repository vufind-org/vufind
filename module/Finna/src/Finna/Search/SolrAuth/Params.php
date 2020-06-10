<?php
/**
 * SolrAuth Search Parameters
 *
 * PHP version 7
 *
 * Copyright (C) The National Library of Finland 2020.
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
 * @package  Search_Solr
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
namespace Finna\Search\SolrAuth;

/**
 * SolrAuth Search Parameters
 *
 * @category VuFind
 * @package  Search_Solr
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
class Params extends \Finna\Search\Solr\Params
{
    /**
     * Config sections to search for facet labels if no override configuration
     * is set.
     *
     * @var array
     */
    protected $defaultFacetLabelSections = ['Facets'];

    /**
     * Format display text for a author-id filter entry.
     *
     * @param array  $filter Filter
     * @param string $field  Filter field
     * @param string $value  Filter value
     *
     * @return array
     */
    protected function formatAuthorIdFilterListEntry($filter, $field, $value)
    {
        // Author id filters are used only in biblio searches.
        return $filter;
    }
}
