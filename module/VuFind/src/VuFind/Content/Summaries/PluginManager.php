<?php
/**
 * Summaries content loader plugin manager
 *
 * PHP version 7
 *
 * Copyright (C) The University of Chicago 2017.
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
 * @package  Content
 * @author   John Jung <jej@uchicago.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:hierarchy_components Wiki
 */
namespace VuFind\Content\Summaries;

/**
 * Summaries content loader plugin manager
 *
 * @category VuFind2
 * @package  Content
 * @author   John Jung <jej@uchicago.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:hierarchy_components Wiki
 */
class PluginManager extends \VuFind\ServiceManager\AbstractPluginManager
{
    /**
     * Default plugin aliases.
     *
     * @var array
     */
    protected $aliases = [
        'demo' => 'VuFind\Content\Summaries\Demo',
        'syndetics' => 'VuFind\Content\Summaries\Syndetics',
        'syndeticsplus' => 'VuFind\Content\Summaries\SyndeticsPlus',
    ];

    /**
     * Default plugin factories.
     *
     * @var array
     */
    protected $factories = [
        'VuFind\Content\Summaries\Demo' =>
            'Zend\ServiceManager\Factory\InvokableFactory',
        'VuFind\Content\Summaries\Syndetics' =>
            'VuFind\Content\AbstractSyndeticsFactory',
        'VuFind\Content\Summaries\SyndeticsPlus' =>
            'VuFind\Content\AbstractSyndeticsFactory',
    ];

    /**
     * Return the name of the base class or interface that plug-ins must conform
     * to.
     *
     * @return string
     */
    protected function getExpectedInterface()
    {
        return 'VuFind\Content\AbstractBase';
    }
}
