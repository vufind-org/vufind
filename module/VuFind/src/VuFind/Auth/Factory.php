<?php
/**
 * Factory for authentication services.
 *
 * PHP version 7
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
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
 *
 * @category VuFind
 * @package  Authentication
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
namespace VuFind\Auth;

use Zend\ServiceManager\ServiceManager;

/**
 * Factory for authentication services.
 *
 * @category VuFind
 * @package  Authentication
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 *
 * @codeCoverageIgnore
 */
class Factory
{
    /**
     * Construct the ChoiceAuth plugin.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return ChoiceAuth
     */
    public static function getChoiceAuth(ServiceManager $sm)
    {
        $container = new \Zend\Session\Container(
            'ChoiceAuth', $sm->get('Zend\Session\SessionManager')
        );
        $auth = new ChoiceAuth($container);
        $auth->setPluginManager($sm->get('VuFind\Auth\PluginManager'));
        return $auth;
    }

    /**
     * Construct the Facebook plugin.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return Facebook
     */
    public static function getFacebook(ServiceManager $sm)
    {
        $container = new \Zend\Session\Container(
            'Facebook', $sm->get('Zend\Session\SessionManager')
        );
        return new Facebook($container);
    }

    /**
     * Construct the ILS plugin.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return ILS
     */
    public static function getILS(ServiceManager $sm)
    {
        return new ILS(
            $sm->get('VuFind\ILS\Connection'),
            $sm->get('VuFind\Auth\ILSAuthenticator')
        );
    }

    /**
     * Construct the MultiAuth plugin.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return MultiAuth
     */
    public static function getMultiAuth(ServiceManager $sm)
    {
        $auth = new MultiAuth();
        $auth->setPluginManager($sm->get('VuFind\Auth\PluginManager'));
        return $auth;
    }

    /**
     * Construct the MultiILS plugin.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return MultiILS
     */
    public static function getMultiILS(ServiceManager $sm)
    {
        return new MultiILS(
            $sm->get('VuFind\ILS\Connection'),
            $sm->get('VuFind\Auth\ILSAuthenticator')
        );
    }

    /**
     * Construct the Shibboleth plugin.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return Shibboleth
     */
    public static function getShibboleth(ServiceManager $sm)
    {
        return new Shibboleth(
            $sm->get('Zend\Session\SessionManager')
        );
    }

    /**
     * Construct the AlmaDatabase plugin.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return AlmaDatabase
     */
    public static function getAlmaDatabase(ServiceManager $sm)
    {
        return new AlmaDatabase(
            $sm->get('VuFind\ILS\Connection'),
            $sm->get('VuFind\Auth\ILSAuthenticator')
        );
    }
}
