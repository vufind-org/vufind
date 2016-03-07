<?php
/**
 * Recommendation Module Factory Class
 *
 * PHP version 5
 *
 * Copyright (C) Villanova University 2014.
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
 * @package  Recommendations
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:recommendation_modules Wiki
 */
namespace Finna\Recommend;
use Zend\ServiceManager\ServiceManager;

/**
 * Recommendation Module Factory Class
 *
 * @category VuFind
 * @package  Recommendations
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:recommendation_modules Wiki
 *
 * @codeCoverageIgnore
 */
class Factory
{
    /**
     * Factory for CollectionSideFacets module.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return CollectionSideFacets
     */
    public static function getCollectionSideFacets(ServiceManager $sm)
    {
        return new CollectionSideFacets(
            $sm->getServiceLocator()->get('VuFind\Config'),
            $sm->getServiceLocator()->get('VuFind\HierarchicalFacetHelper')
        );
    }

    /**
     * Factory for SideFacets module.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return SideFacets
     */
    public static function getSideFacets(ServiceManager $sm)
    {
        return new SideFacets(
            $sm->getServiceLocator()->get('VuFind\Config'),
            $sm->getServiceLocator()->get('VuFind\HierarchicalFacetHelper')
        );
    }

    /**
     * Factory for SideFacetsDeferred module.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return SideFacets
     */
    public static function getSideFacetsDeferred(ServiceManager $sm)
    {
        return new SideFacetsDeferred(
            $sm->getServiceLocator()->get('VuFind\Config')
        );
    }
}
