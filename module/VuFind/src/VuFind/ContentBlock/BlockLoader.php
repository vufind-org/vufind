<?php

/**
 * Content block loader
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

use Laminas\Config\Config;
use VuFind\Config\PluginManager as ConfigManager;
use VuFind\ContentBlock\PluginManager as BlockManager;
use VuFind\Search\Base\Options;
use VuFind\Search\Options\PluginManager as OptionsManager;

/**
 * Content block plugin manager
 *
 * @category VuFind
 * @package  ContentBlock
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:recommendation_modules Wiki
 */
class BlockLoader
{
    /**
     * Options manager.
     *
     * @var OptionsManager
     */
    protected $optionsManager;

    /**
     * Config manager.
     *
     * @var ConfigManager
     */
    protected $configManager;

    /**
     * Block manager.
     *
     * @var BlockManager
     */
    protected $blockManager;

    /**
     * Constructor
     *
     * @param OptionsManager $om Options manager
     * @param ConfigManager  $cm Config manager
     * @param BlockManager   $bm Block manager
     */
    public function __construct(
        OptionsManager $om,
        ConfigManager $cm,
        BlockManager $bm
    ) {
        $this->optionsManager = $om;
        $this->configManager = $cm;
        $this->blockManager = $bm;
    }

    /**
     * Fetch blocks using a search class ID.
     *
     * @param string $searchClassId Search class ID
     *
     * @return array
     */
    public function getFromSearchClassId($searchClassId)
    {
        $options = $this->optionsManager->get($searchClassId);
        return $this->getFromOptions($options);
    }

    /**
     * Fetch blocks using an Options object.
     *
     * @param Options $options Options object
     *
     * @return array
     */
    public function getFromOptions(Options $options)
    {
        return $this->getFromConfig($options->getSearchIni());
    }

    /**
     * Fetch blocks using a configuration name
     *
     * @param string $name    Configuration name
     * @param string $section Section to load from object
     * @param string $setting Setting to load from section
     *
     * @return array
     */
    public function getFromConfig(
        $name,
        $section = 'HomePage',
        $setting = 'content'
    ) {
        $config = $this->configManager->get($name);
        return $this->getFromConfigObject($config, $section, $setting);
    }

    /**
     * Fetch blocks using Config object.
     *
     * @param Config $config  Configuration object
     * @param string $section Section to load from object
     * @param string $setting Setting to load from section
     *
     * @return array
     */
    public function getFromConfigObject(
        Config $config,
        $section = 'HomePage',
        $setting = 'content'
    ) {
        $blocks = [];
        if (isset($config->$section->$setting)) {
            foreach ($config->$section->$setting as $current) {
                $parts = explode(':', $current, 2);
                $block = $this->blockManager->get($parts[0]);
                $block->setConfig($parts[1] ?? '');
                $blocks[] = $block;
            }
        }
        return $blocks;
    }
}
