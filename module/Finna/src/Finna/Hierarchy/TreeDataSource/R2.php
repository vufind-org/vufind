<?php
/**
 * Hierarchy Tree Data Source (R2)
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
 * @package  HierarchyTree_DataSource
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:hierarchy_components Wiki
 */
namespace Finna\Hierarchy\TreeDataSource;

use Finna\Service\RemsService;

/**
 * Hierarchy Tree Data Source (Solr)
 *
 * This is a base helper class for producing hierarchy Trees.
 *
 * @category VuFind
 * @package  HierarchyTree_DataSource
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:hierarchy_components Wiki
 */
class R2 extends \VuFind\Hierarchy\TreeDataSource\Solr
{
    /**
     * Collection page route.
     *
     * @var string
     */
    protected $collectionRoute = 'r2collection';

    /**
     * Record page route.
     *
     * @var string
     */
    protected $recordRoute = 'r2record';

    /**
     * Hierarchy cache file prefix.
     *
     * @var string
     */
    protected $cachePrefix = 'R2';

    /**
     * REMS service
     *
     * @var RemsService
     */
    protected $rems = null;

    /**
     * Set REMS service
     *
     * @param RemsService $rems REMS service
     *
     * @return void
     */
    public function setRems($rems)
    {
        $this->rems = $rems;
    }

    /**
     * Get formatted data for the specified hierarchy ID.
     *
     * @param string $id            Hierarchy ID.
     * @param string $format        Name of formatter service to use.
     * @param array  $options       Additional options for JSON generation.
     * (Currently one option is supported: 'refresh' may be set to true to
     * bypass caching).
     * @param string $cacheTemplate Template for cache filenames
     *
     * @return string
     */
    public function getFormattedData($id, $format, $options = [],
        $cacheTemplate = 'tree_%s'
    ) {
        if ($this->rems && $this->rems->hasUserAccess()) {
            $cacheTemplate = "restricted_$cacheTemplate";
        }

        return parent::getFormattedData($id, $format, $options, $cacheTemplate);
    }
}
