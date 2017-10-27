<?php

/**
 * Factory for EDS backends.
 *
 * PHP version 5
 *
 * Copyright (C) Villanova University 2013.
 * Copyright (C) The National Library of Finland 2017.
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
 * @package  Search
 * @author   David Maus <maus@hab.de>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
namespace Finna\Search\Factory;

use VuFindSearch\Backend\EDS\Backend;
use VuFindSearch\Backend\EDS\Zend2 as Connector;
use Zend\Console\Console;

/**
 * Factory for EDS backends.
 *
 * @category VuFind
 * @package  Search
 * @author   David Maus <maus@hab.de>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
class EdsBackendFactory extends \VuFind\Search\Factory\EdsBackendFactory
{
    /**
     * Create the EDS backend.
     *
     * @param Connector $connector Connector
     *
     * @return Backend
     */
    protected function createBackend(Connector $connector)
    {
        $auth = $this->serviceLocator->get('ZfcRbac\Service\AuthorizationService');
        $isGuest = Console::isConsole()
            || !$auth->isGranted('access.EDSExtendedResults');
        $session = new \Zend\Session\Container(
            'EBSCO', $this->serviceLocator->get('VuFind\SessionManager')
        );
        $backend = new Backend(
            $connector, $this->createRecordCollectionFactory(),
            $this->serviceLocator->get('VuFind\CacheManager')->getCache('object'),
            $session, $this->edsConfig, $isGuest
        );
        $backend->setAuthManager($this->serviceLocator->get('VuFind\AuthManager'));
        $backend->setLogger($this->logger);
        $backend->setQueryBuilder($this->createQueryBuilder());
        return $backend;
    }
}
