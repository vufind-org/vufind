<?php

/**
 * Tabs view helper
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

/**
 * Tabs tab view helper
 *
 * @category VuFind
 * @package  View_Helpers
 * @author   Thomas Wagener <wagener@hebis.uni-frankfurt.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class Tabs extends \Laminas\View\Helper\AbstractHelper
{
    /**
     * Render tabs.
     *
     * @param array  $tabs         Tabs
     * @param string $activeTab    Active tab
     * @param string $idSuffix     Id prefix (Optional)
     * @param array  $extraClasses Associative array with extra classes for elements.
     * Possible key are "tabNav", "tabContent" and "tabPane".
     *
     * @return string
     */
    public function __invoke(
        array $tabs,
        string $activeTab,
        string $idSuffix = '',
        array $extraClasses = []
    ) {
        $view = $this->getView();
        $renderedButtons = [];
        $renderedPanes = [];
        foreach ($tabs as $tabName => $tab) {
            $isActiveTab = ($activeTab == $tabName);
            $buttonId = 'tab-button-' . $tabName . $idSuffix;
            $paneId = 'tab-pane-' . $tabName . $idSuffix;

            $buttonLiAttributes = $view->htmlAttributes(
                ['role' => 'presentation']
            );
            $buttonAttributes = $view->htmlAttributes([
            'id' => $buttonId,
            'class' => $tabName . '-tab-button',
            'role' => 'tab',
            'type' => 'button',
            'aria-controls' => $paneId,
            ]);

            if ($isActiveTab) {
                $buttonAttributes->add('class', 'active');
                $buttonAttributes->add('aria-selected', 'true');
            } else {
                $buttonAttributes->add('aria-selected', 'false');
            }

            foreach ($tab['buttonAttributes'] ?? [] as $key => $value) {
                $buttonAttributes->add($key, $value);
            }

            if (!($tab['visible'] ?? true)) {
                $buttonLiAttributes->add('class', 'hidden');
            }

            $tabDescription = $tab['description'];
            $renderedButtons[] = $view->render(
                'Helpers/tab-button.phtml',
                compact('tabName', 'tabDescription', 'buttonLiAttributes', 'buttonAttributes', 'paneId')
            );

            $tabPaneAttributes = $view->htmlAttributes([
                'id' => $paneId,
                'class' => $tabName . '-tab',
                'role' => 'tabpanel',
                'aria-labelledby' => $buttonId,
                'data-tab-name' => $tabName,
            ]);
            foreach ($tab['paneAttributes'] ?? [] as $key => $value) {
                $tabPaneAttributes->add($key, $value);
            }
            foreach ($extraClasses['tabPane'] ?? [] as $value) {
                $tabPaneAttributes->add('class', $value);
            }

            if ($isActiveTab) {
                $tabPaneAttributes->add('class', 'active');
            }
            $tabContent = $tab['content'];
            $renderedPanes[] = $view->render(
                'Helpers/tab-pane.phtml',
                compact('tabPaneAttributes', 'tabContent')
            );
        }

        return $view->render(
            'Helpers/tabs.phtml',
            [
                'tabNavClasses' => $extraClasses['tabNav'] ?? [],
                'buttons' => $renderedButtons,
                'tabContentClasses' => $extraClasses['tabContent'] ?? [],
                'panes' => $renderedPanes,
            ]
        );
    }
}
