<?php
/**
 * Search Options Object Factory Class
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
 * @package  Search
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:hierarchy_components Wiki
 */
namespace VuFind\Search\Options;
use Zend\ServiceManager\ServiceManager;

/**
 * Search Options Object Factory Class
 *
 * @category VuFind2
 * @package  Search
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:hierarchy_components Wiki
 *
 * @codeCoverageIgnore
 */
class Factory
{
    /**
     * Factory for Solr results object.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return Solr
     */
    public static function getEDS(ServiceManager $sm)
    {
        $config = $sm->getServiceLocator()->get('VuFind\Config');
        $container = new \Zend\Session\Container('EBSCO');
        // No API info in session? Re-establish connection:
        if (!isset($container->info)) {
            $backend = $sm->getServiceLocator()->get('VuFind\Search\BackendManager')
                ->get('EDS');
            $backend->getSessionToken();
        }
        $eds = new \VuFind\Search\EDS\Options($config, $container->info);
        return $eds;
    }
}