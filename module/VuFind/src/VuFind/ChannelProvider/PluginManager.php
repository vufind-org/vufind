<?php
/**
 * Channel provider plugin manager
 *
 * PHP version 7
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
        'newilsitems' => 'VuFind\ChannelProvider\NewILSItems',
        'random' => 'VuFind\ChannelProvider\Random',
        'recentlyreturned' => 'VuFind\ChannelProvider\RecentlyReturned',
        'similaritems' => 'VuFind\ChannelProvider\SimilarItems',
        'trendingilsitems' => 'VuFind\ChannelProvider\TrendingILSItems',
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
        'VuFind\ChannelProvider\NewILSItems' =>
            'VuFind\ChannelProvider\AbstractILSChannelProviderFactory',
        'VuFind\ChannelProvider\Random' =>
            'VuFind\ChannelProvider\Factory::getRandom',
        'VuFind\ChannelProvider\RecentlyReturned' =>
            'VuFind\ChannelProvider\AbstractILSChannelProviderFactory',
        'VuFind\ChannelProvider\SimilarItems' =>
            'VuFind\ChannelProvider\Factory::getSimilarItems',
        'VuFind\ChannelProvider\TrendingILSItems' =>
            'VuFind\ChannelProvider\AbstractILSChannelProviderFactory',
    ];

    /**
     * Constructor
     *
     * Make sure plugins are properly initialized.
     *
     * @param mixed $configOrContainerInstance Configuration or container instance
     * @param array $v3config                  If $configOrContainerInstance is a
     * container, this value will be passed to the parent constructor.
     */
    public function __construct($configOrContainerInstance = null,
        array $v3config = []
    ) {
        $this->addInitializer('VuFind\ChannelProvider\RouterInitializer');
        parent::__construct($configOrContainerInstance, $v3config);
    }

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
