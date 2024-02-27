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
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
 *
 * @category VuFind
 * @package  View_Helpers
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */

namespace VuFind\View\Helper\Root;

use VuFind\Config\PluginManager as ConfigManager;
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
     * Config manager
     *
     * @var ConfigManager
     */
    protected $configManager;

    /**
     * Plugin manager for search options.
     *
     * @var OptionsManager
     */
    protected $optionsManager;

    /**
     * Plugin manager for related record modules.
     *
     * @var RelatedManager
     */
    protected $pluginManager;

    /**
     * Constructor
     *
     * @param RelatedManager $pluginManager Plugin manager for related record modules
     * @param ConfigManager  $cm            Configuration manager
     * @param OptionsManager $om            Search options manager
     */
    public function __construct(
        RelatedManager $pluginManager,
        ConfigManager $cm,
        OptionsManager $om
    ) {
        $this->pluginManager = $pluginManager;
        $this->configManager = $cm;
        $this->optionsManager = $om;
    }

    /**
     * Given a record source ID, return the appropriate related record configuration.
     *
     * @param string $source Source identifier
     *
     * @return array
     */
    protected function getConfigForSource($source)
    {
        $options = $this->optionsManager->get($source);
        $configName = $options->getSearchIni();
        // Special case -- default Solr stores [Record] section in config.ini
        if ($configName === 'searches') {
            $configName = 'config';
        }
        $config = $this->configManager->get($configName);
        return $config->Record->related ?? [];
    }

    /**
     * Get a list of related records modules.
     *
     * @param \VuFind\RecordDriver\AbstractBase $driver Record driver
     *
     * @return array
     */
    public function getList(\VuFind\RecordDriver\AbstractBase $driver)
    {
        $retVal = [];
        $config = $this->getConfigForSource($driver->getSearchBackendIdentifier());
        foreach ($config as $current) {
            $parts = explode(':', $current, 2);
            $type = $parts[0];
            $params = $parts[1] ?? null;
            if ($this->pluginManager->has($type)) {
                $plugin = $this->pluginManager->get($type);
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
    public function render($related)
    {
        $template = 'Related/%s.phtml';
        $className = $related::class;
        $context = ['related' => $related];
        return $this->renderClassTemplate($template, $className, $context);
    }
}
