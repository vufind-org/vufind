<?php
/**
 * Cover layer plugin manager
 *
 * PHP version 7
 *
 * Copyright (C) Villanova University 2018.
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
 * @package  Cover_Generator
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:hierarchy_components Wiki
 */
namespace VuFind\Cover\Layer;

/**
 * Cover layer plugin manager
 *
 * @category VuFind
 * @package  Cover_Generator
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:hierarchy_components Wiki
 */
class PluginManager extends \VuFind\ServiceManager\AbstractPluginManager
{
    /**
     * Default plugin aliases.
     *
     * @var array
     */
    protected $aliases = [
        'defaulttext' => 'VuFind\Cover\Layer\DefaultText',
        'gridbackground' => 'VuFind\Cover\Layer\GridBackground',
        'initialtext' => 'VuFind\Cover\Layer\InitialText',
        'solidbackground' => 'VuFind\Cover\Layer\SolidBackground',
    ];

    /**
     * Default plugin factories.
     *
     * @var array
     */
    protected $factories = [
        'VuFind\Cover\Layer\DefaultText' =>
            'Zend\ServiceManager\Factory\InvokableFactory',
        'VuFind\Cover\Layer\GridBackground' =>
            'Zend\ServiceManager\Factory\InvokableFactory',
        'VuFind\Cover\Layer\InitialText' =>
            'Zend\ServiceManager\Factory\InvokableFactory',
        'VuFind\Cover\Layer\SolidBackground' =>
            'Zend\ServiceManager\Factory\InvokableFactory',
    ];

    /**
     * Return the name of the base class or interface that plug-ins must conform
     * to.
     *
     * @return string
     */
    protected function getExpectedInterface()
    {
        return 'VuFind\Cover\Layer\LayerInterface';
    }
}
