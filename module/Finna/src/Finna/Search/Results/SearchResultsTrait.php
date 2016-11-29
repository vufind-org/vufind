<?php
/**
 * Finna search results trait
 *
 * PHP version 5
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
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 *
 * @category VuFind
 * @package  Search
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
namespace Finna\Search\Results;

use Finna\Search\Factory\UrlQueryHelperFactory;

/**
 * Finna search results trait
 *
 * @category VuFind
 * @package  Search
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
trait SearchResultsTrait
{
    /**
     * Get backend ID
     *
     * @return string
     */
    public function getBackendId()
    {
        return isset($this->backendId) ? $this->backendId : 'Solr';
    }

    /**
     * Get the URL helper for this object.
     *
     * N.B. Identical to the base class but creates a Finna version!
     *
     * @return \VuFind\Search\UrlQueryHelper
     */
    public function getUrlQuery()
    {
        // Set up URL helper:
        if (!isset($this->helpers['urlQuery'])) {
            $factory = new UrlQueryHelperFactory();
            $this->helpers['urlQuery'] = $factory->fromParams(
                $this->getParams(), $this->getUrlQueryHelperOptions()
            );
            if (is_callable([$this->helpers['urlQuery'], 'setSearchId'])) {
                $savedSearches
                    = $this->getServiceLocator()->get('Request')->getQuery('search');
                if ($savedSearches) {
                    $this->helpers['urlQuery']
                        ->setDefaultParameter('search', $savedSearches);
                }
            }
        }
        return $this->helpers['urlQuery'];
    }
}
