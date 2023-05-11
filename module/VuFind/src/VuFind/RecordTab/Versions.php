<?php

/**
 * Versions tab
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2019-2020.
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
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:record_tabs Wiki
 */

namespace VuFind\RecordTab;

use VuFind\I18n\Translator\TranslatorAwareInterface;
use VuFind\I18n\Translator\TranslatorAwareTrait;

/**
 * Versions tab
 *
 * @category VuFind
 * @package  RecordTabs
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:record_tabs Wiki
 */
class Versions extends \VuFind\RecordTab\AbstractBase implements TranslatorAwareInterface
{
    use TranslatorAwareTrait;

    /**
     * Main configuration
     *
     * @var \Laminas\Config\Config
     */
    protected $config;

    /**
     * Search options plugin manager
     *
     * @var \VuFind\Search\Options\PluginManager
     */
    protected $searchOptionsManager;

    /**
     * Constructor
     *
     * @param \Laminas\Config\Config               $config Configuration
     * @param \VuFind\Search\Options\PluginManager $som    Search options plugin
     * manager
     */
    public function __construct(
        \Laminas\Config\Config $config,
        \VuFind\Search\Options\PluginManager $som
    ) {
        $this->config = $config;
        $this->searchOptionsManager = $som;
    }

    /**
     * Is this tab active?
     *
     * @return bool
     */
    public function isActive()
    {
        $options = $this->searchOptionsManager
            ->get($this->getRecordDriver()->getSourceIdentifier());
        return $options->getVersionsAction()
            && $this->getRecordDriver()->tryMethod('getOtherVersionCount') > 0;
    }

    /**
     * Get the on-screen description for this tab.
     *
     * @return string
     */
    public function getDescription()
    {
        $count = $this->getRecordDriver()->tryMethod('getOtherVersionCount');
        return $this->translate('other_versions_title', ['%%count%%' => $count]);
    }
}
