<?php
/**
 * Factory class for constructing authentication modules.
 *
 * PHP version 5
 *
 * Copyright (C) Villanova University 2010.
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
 * @package  Authentication
 * @author   Franck Borel <franck.borel@gbv.de>
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/building_an_authentication_handler Wiki
 */
namespace VuFind\Auth;
use VuFind\Exception\Auth as AuthException;

/**
 * Factory class for constructing authentication modules.
 *
 * @category VuFind2
 * @package  Authentication
 * @author   Franck Borel <franck.borel@gbv.de>
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/building_an_authentication_handler Wiki
 */
class Factory
{
    /**
     * Initialize an authentication module.
     *
     * @param string $authNHandler The name of the module to initialize.
     * @param object $config       Optional configuration object to pass through
     * (loads default configuration if none specified).
     *
     * @throws AuthException
     * @return object
     */
    static function getAuth($authNHandler, $config = null)
    {
        // Special handling for authentication classes that don't conform to the
        // standard pattern (for legacy support):
        if ($authNHandler == 'DB') {
            $authNHandler = 'Database';
        } else if ($authNHandler == 'SIP') {
            $authNHandler = 'SIP2';
        }

        // Load up the handler if a legal name has been supplied.
        $className = 'VuFind\\Auth\\' . $authNHandler;
        if (@class_exists($className)) {
            return new $className($config);
        } else {
            throw new AuthException(
                'Authentication handler ' . $authNHandler . ' does not exist!'
            );
        }
    }
}