<?php
/**
 * Related Record Module Factory Class
 *
 * PHP version 5
 *
 * Copyright (C) Villanova University 2014.
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
 * @package  Related_Records
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:hierarchy_components Wiki
 */
namespace VuFind\Related;
use Zend\ServiceManager\ServiceManager;

/**
 * Related Record Module Factory Class
 *
 * @category VuFind2
 * @package  Related_Records
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:hierarchy_components Wiki
 *
 * @codeCoverageIgnore
 */
class Factory
{
    /**
     * Factory for Editions module.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return Editions
     */
    public static function getEditions(ServiceManager $sm)
    {
        return new Editions(
            $sm->getServiceLocator()->get('VuFind\SearchResultsPluginManager'),
            $sm->getServiceLocator()->get('VuFind\WorldCatUtils')
        );
    }

    /**
     * Factory for Similar module.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return Similar
     */
    public static function getSimilar(ServiceManager $sm)
    {
        return new Similar($sm->getServiceLocator()->get('VuFind\Search'));
    }

    /**
     * Factory for WorldCatEditions module.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return WorldCatEditions
     */
    public static function getWorldCatEditions(ServiceManager $sm)
    {
        return new WorldCatEditions(
            $sm->getServiceLocator()->get('VuFind\SearchResultsPluginManager'),
            $sm->getServiceLocator()->get('VuFind\WorldCatUtils')
        );
    }

    /**
     * Factory for WorldCatSimilar module.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return WorldCatSimilar
     */
    public static function getWorldCatSimilar(ServiceManager $sm)
    {
        return new WorldCatSimilar($sm->getServiceLocator()->get('VuFind\Search'));
    }
}