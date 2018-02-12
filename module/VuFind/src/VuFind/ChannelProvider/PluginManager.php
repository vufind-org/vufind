<?php
/**
 * Channel provider plugin manager
 *
 * PHP version 5
 *
 * Copyright (C) Villanova University 2016.
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
 * @package  Channels
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
namespace VuFind\ChannelProvider;

/**
 * Channel provider plugin manager
 *
 * @category VuFind
 * @package  Channels
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class PluginManager extends \VuFind\ServiceManager\AbstractPluginManager
{
    /**
     * Default plugin aliases.
     *
     * @var array
     */
    protected $aliases = [
        'alphabrowse' => 'VuFind\ChannelProvider\AlphaBrowse',
        'facets' => 'VuFind\ChannelProvider\Facets',
        'listitems' => 'VuFind\ChannelProvider\ListItems',
        'random' => 'VuFind\ChannelProvider\Random',
        'similaritems' => 'VuFind\ChannelProvider\SimilarItems',
    ];

    /**
     * Default plugin factories.
     *
     * @var array
     */
    protected $factories = [
        'VuFind\ChannelProvider\AlphaBrowse' =>
            'VuFind\ChannelProvider\Factory::getAlphaBrowse',
        'VuFind\ChannelProvider\Facets' =>
            'VuFind\ChannelProvider\Factory::getFacets',
        'VuFind\ChannelProvider\ListItems' =>
            'VuFind\ChannelProvider\Factory::getListItems',
        'VuFind\ChannelProvider\Random' =>
            'VuFind\ChannelProvider\Factory::getRandom',
        'VuFind\ChannelProvider\SimilarItems' =>
            'VuFind\ChannelProvider\Factory::getSimilarItems',
    ];

    /**
     * Return the name of the base class or interface that plug-ins must conform
     * to.
     *
     * @return string
     */
    protected function getExpectedInterface()
    {
        return 'VuFind\ChannelProvider\ChannelProviderInterface';
    }
}
