<?php
/**
 * Factory for instantiating Session Manager
 *
 * PHP version 5
 *
 * Copyright (C) The National Library of Finland 2018.
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
 * @package  Session_Handlers
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
namespace Finna\Session;

use Zend\ServiceManager\ServiceLocatorInterface;

/**
 * Factory for instantiating Session Manager
 *
 * @category VuFind
 * @package  Session_Handlers
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 *
 * @codeCoverageIgnore
 */
class ManagerFactory extends \VuFind\Session\ManagerFactory
{
    /**
     * Create service
     *
     * @param ServiceLocatorInterface $sm Service manager
     *
     * @return mixed
     */
    public function createService(ServiceLocatorInterface $sm)
    {
        $sessionManager = parent::createService($sm);
        $storage = new \Zend\Session\Container('SessionState', $sessionManager);
        if (empty($storage->sessionStartTime)) {
            $storage->sessionStartTime = time();
        }
        return $sessionManager;
    }
}
