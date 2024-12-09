<?php

/**
 * Metadata view helper
 *
 * PHP version 8
 *
 * Copyright (C) University of Tübingen 2019.
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
 * @package  Metadata_Vocabularies
 * @author   Mario Trojan <mario.trojan@uni-tuebingen.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */

namespace VuFind\View\Helper\Root;

/**
 * Metadata view helper
 *
 * @category VuFind
 * @package  Metadata_Vocabularies
 * @author   Mario Trojan <mario.trojan@uni-tuebingen.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class Metadata extends \Laminas\View\Helper\AbstractHelper
{
    /**
     * Metadata configuration entries
     *
     * @var \Laminas\Config\Config
     */
    protected $config;

    /**
     * Laminas meta helper, used to embed html tags in the generated page
     *
     * @var \Laminas\View\Helper\HeadMeta
     */
    protected $metaHelper;

    /**
     * Plugin Manager for vocabularies
     *
     * @var \VuFind\MetadataVocabulary\PluginManager
     */
    protected $pluginManager;

    /**
     * Constructor
     *
     * @param \VuFind\MetadataVocabulary\PluginManager $pm         Plugin manager
     * @param \Laminas\Config\Config                   $config     Configuration
     * @param \Laminas\View\Helper\HeadMeta            $metaHelper Head meta helper
     */
    public function __construct(
        \VuFind\MetadataVocabulary\PluginManager $pm,
        \Laminas\Config\Config $config,
        \Laminas\View\Helper\HeadMeta $metaHelper
    ) {
        $this->pluginManager = $pm;
        $this->config = $config;
        $this->metaHelper = $metaHelper;
    }

    /**
     * Get all active vocabularies for the current record.
     *
     * @param \VuFind\RecordDriver\AbstractBase $driver Record driver
     *
     * @return array
     */
    protected function getVocabularies(\VuFind\RecordDriver\AbstractBase $driver)
    {
        $recordDriverConfigs = isset($this->config->Vocabularies)
            ? $this->config->Vocabularies->toArray() : [];
        $retVal = [];
        foreach ($recordDriverConfigs as $className => $vocabs) {
            if ($driver instanceof $className) {
                $retVal = array_merge($retVal, $vocabs);
            }
        }
        return array_unique($retVal);
    }

    /**
     * Generate all metatags for RecordDriver and add to page
     *
     * Decide which Plugins to load for the given RecordDriver
     * dependent on configuration. (only by class name,
     * namespace will not be considered)
     *
     * @param \VuFind\RecordDriver\AbstractBase $driver Record driver
     *
     * @return void
     */
    public function generateMetatags(\VuFind\RecordDriver\AbstractBase $driver)
    {
        foreach ($this->getVocabularies($driver) as $metatagType) {
            $vocabulary = $this->pluginManager->get($metatagType);
            $mappedFields = $vocabulary->getMappedData($driver);
            foreach ($mappedFields as $field => $values) {
                foreach ($values as $value) {
                    $this->metaHelper->appendName($field, $value);
                }
            }
        }
    }
}
