<?php
/**
 * VuFind Factory for controller plugins
 *
 * PHP version 5
 *
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
 * @package  Plugin
 * @author   Joni Nevalainen <joni.nevalainen@gofore.com>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
namespace Finna\Controller\Plugin;

use Zend\ServiceManager\ServiceManager;

/**
 * Factory for controller plugins.
 *
 * @category VuFind
 * @package  Plugin
 * @author   Joni Nevalainen <joni.nevalainen@gofore.com>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/
 *
 * @codeCoverageIgnore
 */
class Factory
{
    /**
     * Construct the Recaptcha plugin.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return Recaptcha
     */
    public static function getRecaptcha(ServiceManager $sm)
    {
        $config = $sm->getServiceLocator()->get('VuFind\Config')->get('config');
        return new Recaptcha(
            $sm->getServiceLocator()->get('VuFind\Recaptcha'),
            $config,
            $sm->getServiceLocator()->get('VuFind\AuthManager')
        );
    }
}
