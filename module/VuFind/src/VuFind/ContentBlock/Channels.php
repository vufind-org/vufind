<?php

/**
 * Channels content block.
 *
 * PHP version 8
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
 * @package  ContentBlock
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:recommendation_modules Wiki
 */

namespace VuFind\ContentBlock;

use Laminas\Http\PhpEnvironment\Request;
use VuFind\ChannelProvider\ChannelLoader;

/**
 * Channels content block.
 *
 * @category VuFind
 * @package  ContentBlock
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:recommendation_modules Wiki
 */
class Channels implements ContentBlockInterface
{
    /**
     * Request object
     *
     * @var Request
     */
    protected $request;

    /**
     * Channel loader
     *
     * @var ChannelLoader
     */
    protected $loader;

    /**
     * Data source (null to use default found in channels.ini)
     *
     * @var string
     */
    protected $source = null;

    /**
     * Constructor
     *
     * @param Request       $request Request object
     * @param ChannelLoader $loader  Channel loader
     */
    public function __construct(Request $request, ChannelLoader $loader)
    {
        $this->request = $request;
        $this->loader = $loader;
    }

    /**
     * Store the configuration of the content block.
     *
     * @param string $settings Settings from searches.ini.
     *
     * @return void
     */
    public function setConfig($settings)
    {
        if (!empty($settings)) {
            $this->source = $settings;
        }
    }

    /**
     * Return context variables used for rendering the block's template.
     *
     * @return array
     */
    public function getContext()
    {
        $activeChannel = $this->request->getQuery()->get('channelProvider');
        $token = $this->request->getQuery()->get('channelToken');
        return $this->loader->getHomeContext($token, $activeChannel, $this->source);
    }
}
