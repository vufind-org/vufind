<?php

/**
 * Reviews content loader plugin manager
 *
 * PHP version 8
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
 * along with this program; if not, see
 * <https://www.gnu.org/licenses/>.
 *
 * @category VuFind
 * @package  Content
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:hierarchy_components Wiki
 */

namespace VuFind\Content\Reviews;

use VuFind\Content\Deprecated;

/**
 * Reviews content loader plugin manager
 *
 * @category VuFind
 * @package  Content
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
        Amazon::class => Deprecated::class,
        AmazonEditorial::class => Deprecated::class,
        Booksite::class => Deprecated::class,
        'amazon' => Deprecated::class,
        'amazoneditorial' => Deprecated::class,
        'booksite' => Deprecated::class,
        'demo' => Demo::class,
        'guardian' => Guardian::class,
        'syndetics' => Syndetics::class,
        'syndeticsplus' => 'syndetics',
    ];

    /**
     * Default plugin factories.
     *
     * @var array
     */
    protected $factories = [
        Demo::class => \Laminas\ServiceManager\Factory\InvokableFactory::class,
        Deprecated::class => \Laminas\ServiceManager\Factory\InvokableFactory::class,
        Guardian::class => \Laminas\ServiceManager\Factory\InvokableFactory::class,
        Syndetics::class => \VuFind\Content\AbstractSyndeticsFactory::class,
    ];

    /**
     * Return the name of the base class or interface that plug-ins must conform
     * to.
     *
     * @return string
     */
    protected function getExpectedInterface()
    {
        return \VuFind\Content\AbstractBase::class;
    }
}
