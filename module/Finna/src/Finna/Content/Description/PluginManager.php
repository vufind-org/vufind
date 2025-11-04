<?php

/**
 * Description provider plugin manager
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2023.
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
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:hierarchy_components Wiki
 */

namespace Finna\Content\Description;

use Laminas\ServiceManager\Factory\InvokableFactory;

/**
 * Description provider plugin manager
 *
 * @category VuFind
 * @package  Content
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
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
        'Kirjavalitys' => Kirjavalitys::class,
        'NatLibFi' => NatLibFi::class,
        'Record' => Record::class,
        'RecordUrl' => RecordUrl::class,
    ];

    /**
     * Default plugin factories.
     *
     * @var array
     */
    protected $factories = [
        Kirjavalitys::class => InvokableFactory::class,
        NatLibFi::class => InvokableFactory::class,
        Record::class => RecordFactory::class,
        RecordUrl::class => InvokableFactory::class,
    ];

    /**
     * Return the name of the base class or interface that plug-ins must conform
     * to.
     *
     * @return string
     */
    protected function getExpectedInterface()
    {
        return DescriptionProviderInterface::class;
    }
}
