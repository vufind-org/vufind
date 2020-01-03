<?php
/**
 * Metadata view helper
 *
 * PHP version 7
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
class Metadata extends \Zend\View\Helper\AbstractHelper
{
    /**
     * Metadata configuration entries
     * 
     * @var \Zend\Config\Config
     */
    protected $config;
    
    /**
     * Zend meta helper, used to embed html tags in the generated page
     * 
     * @var \Zend\View\Helper\HeadMeta
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
     * @param \VuFind\MetadataVocabulary\PluginManager $pluginManager
     * @param \Zend\Config\Config                      $config
     * @param \Zend\View\Helper\HeadMeta               $metaHelper
     */
    public function __construct(\VuFind\MetadataVocabulary\PluginManager $pluginManager,
        \Zend\Config\Config $config,
        \Zend\View\Helper\HeadMeta $metaHelper
    ) {
        $this->pluginManager = $pluginManager;
        $this->config = $config;
        $this->metaHelper = $metaHelper;
    }

    /**
     * Generate all metatags for RecordDriver and add to page
     * 
     * Decide which Plugins to load for the given RecordDriver
     * dependant on configuration. (only by class name,
     * namespace will not be considered)
     *
     * @param \VuFind\RecordDriver\AbstractBase $driver
     */
    public function generateMetatags(\VuFind\RecordDriver\AbstractBase $driver)
    {
        $recordDriverConfigurations = $this->config->Vocabularies ?? [];
        foreach ($recordDriverConfigurations as $recordDriverClassName => $metatagTypes) {
            if ($driver instanceof $recordDriverClassName) {
                foreach ($metatagTypes as $metatagType) {
                    $vocabulary = $this->pluginManager->get($metatagType);
                    $mappedFields = $vocabulary->getMappedData($driver);
                    foreach ($mappedFields as $field => $values) {
                        foreach ($values as $value) {
                            $this->metaHelper->appendName($field, $value);
                        }
                    }
                }
                break;
            }
        }
    }
}
