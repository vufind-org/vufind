<?php
/**
 * VuFind Plugin Initializer
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
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
 *
 * @category VuFind
 * @package  ServiceManager
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
namespace VuFind\ServiceManager;

use Zend\ServiceManager\ServiceLocatorInterface;

/**
 * VuFind Plugin Initializer
 *
 * @category VuFind
 * @package  ServiceManager
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class VuFindPluginInitializer extends ZendPluginInitializer
{
    /**
     * Given an instance and a Plugin Manager, initialize the instance.
     *
     * @param object                  $instance Instance to initialize
     * @param ServiceLocatorInterface $manager  Plugin manager
     *
     * @return object
     */
    public function initialize($instance, ServiceLocatorInterface $manager)
    {
        if (method_exists($instance, 'setPluginManager')) {
            $instance->setPluginManager($manager);
        }
        return parent::initialize($instance, $manager);
    }
}
