<?php

/**
 * Channels tab
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2024.
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
 * @package  RecordTabs
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:record_tabs Wiki
 */

namespace VuFind\RecordTab;

use VuFind\ChannelProvider\ChannelLoader;

/**
 * Channels tab
 *
 * @category VuFind
 * @package  RecordTabs
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:record_tabs Wiki
 */
class Channels extends AbstractBase
{
    /**
     * Config sections in channels.ini to use for loading channel settings.
     *
     * @var array
     */
    protected array $configSections = ['recordTab', 'record'];

    /**
     * Constructor
     *
     * @param ChannelLoader $loader  Channel loader
     * @param array         $options Config settings
     */
    public function __construct(protected ChannelLoader $loader, protected array $options = [])
    {
    }

    /**
     * Get the on-screen description for this tab.
     *
     * @return string
     */
    public function getDescription()
    {
        return $this->options['label'] ?? 'Channels';
    }

    /**
     * Can this tab be loaded via AJAX?
     *
     * @return bool
     */
    public function supportsAjax()
    {
        // Due to heavy Javascript in channels, the tab cannot be AJAX-loaded:
        return false;
    }

    /**
     * Return context variables used for rendering the block's template.
     *
     * @return array
     */
    public function getContext()
    {
        $request = $this->getRequest() ?: null;
        $query = $request?->getQuery();
        $driver = $this->getRecordDriver();
        $context = ['displaySearchBox' => false];
        return $context + $this->loader->getRecordContext(
            $driver->getUniqueID(),
            $query?->get('channelToken'),
            $query?->get('channelProvider'),
            $driver->getSearchBackendIdentifier(),
            $this->configSections
        );
    }
}
