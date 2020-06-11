<?php
/**
 * Versions tab
 *
 * PHP version 7
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
 * @link     http://vufind.org/wiki/vufind2:record_tabs Wiki
 */
namespace Finna\RecordTab;

use VuFind\I18n\Translator\TranslatorAwareInterface;
use VuFind\I18n\Translator\TranslatorAwareTrait;
use VuFind\View\Helper\Root\SearchMemory;

/**
 * Versions tab
 *
 * @category VuFind
 * @package  RecordTabs
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:record_tabs Wiki
 */
class Versions extends \VuFind\RecordTab\AbstractBase
    implements TranslatorAwareInterface
{
    use TranslatorAwareTrait;

    /**
     * Main configuration
     *
     * @var \Zend\Config\Config
     */
    protected $config;

    /**
     * Search memory plugin
     *
     * @var SearchMemory
     */
    protected $searchMemory;

    /**
     * Constructor
     *
     * @param \Zend\Config\Config $config       Configuration
     * @param SearchMemory        $searchMemory Search memory view helper
     */
    public function __construct(\Zend\Config\Config $config,
        SearchMemory $searchMemory
    ) {
        $this->config = $config;
        $this->searchMemory = $searchMemory;
    }

    /**
     * Is this tab active?
     *
     * @return bool
     */
    public function isActive()
    {
        if (!empty($this->config->Record->display_versions)) {
            return $this->getOtherVersionCount() > 0;
        }
        return false;
    }

    /**
     * Get the on-screen description for this tab.
     *
     * @return string
     */
    public function getDescription()
    {
        $count = $this->getOtherVersionCount();
        return $this->translate('other_versions_title', ['%%count%%' => $count]);
    }

    /**
     * Get other version count
     *
     * @return int
     */
    protected function getOtherVersionCount()
    {
        $driver = $this->getRecordDriver();
        $sourceId = $driver->getSourceIdentifier();
        $params = $this->searchMemory->getLastSearchParams($sourceId);

        return $driver->tryMethod('getOtherVersionCount', [$params], 0);
    }
}
