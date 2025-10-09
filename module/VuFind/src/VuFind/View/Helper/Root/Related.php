<?php

/**
 * Related records view helper
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2010.
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
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */

namespace VuFind\View\Helper\Root;

use VuFind\Config\ConfigManagerInterface;
use VuFind\Related\PluginManager as RelatedManager;
use VuFind\Search\Options\PluginManager as OptionsManager;

/**
 * Related records view helper
 *
 * @category VuFind
 * @package  View_Helpers
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class Related extends \Laminas\View\Helper\AbstractHelper
{
    use ClassBasedTemplateRendererTrait;

    /**
     * Constructor
     *
     * @param RelatedManager         $relatedPluginManager Plugin manager for related record modules
     * @param ConfigManagerInterface $configManager        Configuration manager
     * @param OptionsManager         $optionsManager       Search options plugin manager
     */
    public function __construct(
        protected RelatedManager $relatedPluginManager,
        protected ConfigManagerInterface $configManager,
        protected OptionsManager $optionsManager
    ) {
    }

    /**
     * Given a record source ID, return the appropriate related record configuration.
     *
     * @param string $source Source identifier
     *
     * @return array
     */
    protected function getConfigForSource(string $source): array
    {
        $options = $this->optionsManager->get($source);
        $configName = $options->getSearchIni();
        // Special case -- default Solr stores [Record] section in config.ini
        if ($configName === 'searches') {
            $configName = 'config';
        }
        $config = $this->configManager->getConfigArray($configName);
        return $config['Record']['related'] ?? [];
    }

    /**
     * Get a list of related records modules.
     *
     * @param \VuFind\RecordDriver\AbstractBase $driver Record driver
     *
     * @return array
     */
    public function getList(\VuFind\RecordDriver\AbstractBase $driver): array
    {
        $retVal = [];
        $config = $this->getConfigForSource($driver->getSearchBackendIdentifier());
        foreach ($config as $current) {
            $parts = explode(':', $current, 2);
            $type = $parts[0];
            $params = $parts[1] ?? null;
            if ($this->relatedPluginManager->has($type)) {
                $plugin = $this->relatedPluginManager->get($type);
                $plugin->init($params, $driver);
                $retVal[] = $plugin;
            } else {
                throw new \Exception("Related module {$type} does not exist.");
            }
        }
        return $retVal;
    }

    /**
     * Render the output of a related records module.
     *
     * @param \VuFind\Related\RelatedInterface $related The related records object to
     * render
     *
     * @return string
     */
    public function render(\VuFind\Related\RelatedInterface $related): string
    {
        $template = 'Related/%s.phtml';
        $className = $related::class;
        $context = ['related' => $related];
        return $this->renderClassTemplate($template, $className, $context);
    }
}
