<?php

/**
 * Record tab view helper
 *
 * PHP version 8
 *
 * Copyright (C) Hebis Verbundzentrale 2025.
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
 * @package  View_Helpers
 * @author   Thomas Wagener <wagener@hebis.uni-frankfurt.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */

namespace VuFind\View\Helper\Root;

use VuFind\RecordTab\TabManager;

use function in_array;

/**
 * Record tab view helper
 *
 * @category VuFind
 * @package  View_Helpers
 * @author   Thomas Wagener <wagener@hebis.uni-frankfurt.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class RecordTabs extends \Laminas\View\Helper\AbstractHelper
{
    /**
     * Constructor
     *
     * @param array      $config     Config
     * @param TabManager $tabManager Tab Manager
     */
    public function __construct(protected array $config, protected TabManager $tabManager)
    {
    }

    /**
     * Render record tabs.
     *
     * @param \VuFind\RecordDriver\AbstractBase $driver    Record driver
     * @param array                             $tabs      Tabs
     * @param string                            $activeTab Active tab
     *
     * @return array
     */
    public function __invoke(
        \VuFind\RecordDriver\AbstractBase $driver,
        array $tabs,
        string $activeTab
    ): array {
        $recordLinker = $this->getView()->recordLinker();
        $ajaxTabUrl = $recordLinker->getTabUrl($driver, 'AjaxTab');
        $loadInitialTabWithAjax = (bool)($this->config['Site']['loadInitialTabWithAjax'] ?? false);
        $backgroundTabs = $this->tabManager->getBackgroundTabNames($driver);
        $tabArray = [];
        foreach ($tabs as $tab => $obj) {
            $tabItem = [];
            $tabName = preg_replace("/\W/", '-', strtolower($tab));
            $tabItem['description'] = $obj->getDescription();
            $tabItem['visible'] = $obj->isVisible();
            $tabItem['buttonAttributes'] = [
                'class' => 'record-tab-button',
            ];

            $loadContent = (($activeTab === $tab) && !$loadInitialTabWithAjax) || !$obj->supportsAjax();
            $tabItem['content'] = $loadContent ? $this->getView()->record($driver)->getTab($obj) : '';
            $tabItem['paneAttributes'] = [
                'class' => 'record-tab-pane',
                'data-tab-url' => $recordLinker->getTabUrl($driver, $tab),
                'data-ajax-url' => $ajaxTabUrl,
            ];
            if ($loadContent) {
                $tabItem['paneAttributes']['data-init'] = 'true';
            } else {
                $tabItem['paneAttributes']['data-init'] = 'false';
                if (in_array($tab, $backgroundTabs)) {
                    $tabItem['paneAttributes']['data-background'] = 'true';
                }
            }
            $tabArray[$tabName] = $tabItem;
        }
        return $tabArray;
    }
}
