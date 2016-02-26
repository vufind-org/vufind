<?php
/**
 * Search box view helper
 *
 * PHP version 5
 *
 * Copyright (C) The National Library of Finland 2015.
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
 * @category VuFind
 * @package  View_Helpers
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:developer_manual Wiki
 */
namespace Finna\View\Helper\Root;

/**
 * Search box view helper
 *
 * @category VuFind
 * @package  View_Helpers
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:developer_manual Wiki
 */
class SearchBox extends \VuFind\View\Helper\Root\SearchBox
{
    /**
     * Configuration for search tabs
     *
     * @var array
     */
    protected $tabConfig;

    /**
     * Set configuration for search tabs
     *
     * @param array $config Configuration
     *
     * @return void
     */
    public function setTabConfig($config)
    {
        if (isset($config['SearchTabs'])) {
            $this->tabConfig = $config['SearchTabs']->toArray();
        }
    }

    /**
     * Are combined handlers enabled?
     *
     * @return bool
     */
    public function combinedHandlersActive()
    {
        if (!empty($this->tabConfig)) {
            if (!isset($this->config['General']['combinedHandlers'])
                || !$this->config['General']['combinedHandlers']
            ) {
                throw new \Exception(
                    'Combined handlers must be enabled when search tabs are used'
                );
            }
            return true;
        }
        return false;
    }

    /**
     * Support method for getHandlers() -- load combined settings.
     *
     * @param string $activeSearchClass Active search class ID
     * @param string $activeHandler     Active search handler
     *
     * @return array
     */
    protected function getCombinedHandlers($activeSearchClass, $activeHandler)
    {
        if (isset($this->config['CombinedHandlers'])) {
            $handlers = [];
            foreach ($this->config['CombinedHandlers'] as $type => $label) {
                $handlers[] = [
                   'value' => $type,
                   'label' => $label,
                   'indent' => false,
                   'selected' => ($activeHandler == $type)
                ];
            }
            return $handlers;
        }
        return parent::getCombinedHandlers($activeSearchClass, $activeHandler);
    }

    /**
     * Support method for getCombinedHandlers() -- retrieve/validate configuration.
     *
     * @param string $activeSearchClass Active search class ID
     *
     * @return array
     */
    protected function getCombinedHandlerConfig($activeSearchClass)
    {
        if (!isset($this->cachedConfigs[$activeSearchClass])) {
            $this->cachedConfigs[$activeSearchClass]
                = $this->getCombinedHandlers($activeSearchClass, null);
        }

        return $this->cachedConfigs[$activeSearchClass];
    }

    /**
     * Is autocomplete enabled for the current context?
     *
     * @param string $activeSearchClass Active search class ID
     *
     * @return bool
     */
    public function autocompleteEnabled($activeSearchClass)
    {
        $options = $this->optionsManager->get($activeSearchClass);
        return $options->autocompleteEnabled();
    }
}
