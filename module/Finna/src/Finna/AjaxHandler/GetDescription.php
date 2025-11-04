<?php

/**
 * GetDescription AJAX handler
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2015-2023.
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
 * @package  AJAX
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */

namespace Finna\AjaxHandler;

use Finna\Content\Description\PluginManager as DescriptionPluginManager;
use Laminas\Mvc\Controller\Plugin\Params;
use VuFind\Cache\Manager as CacheManager;
use VuFind\Config\Config;
use VuFind\I18n\Translator\TranslatorAwareInterface;
use VuFind\Record\Loader;
use VuFind\Session\Settings as SessionSettings;

/**
 * GetDescription AJAX handler
 *
 * @category VuFind
 * @package  AJAX
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class GetDescription extends \VuFind\AjaxHandler\AbstractBase implements
    TranslatorAwareInterface
{
    use \VuFind\Config\Feature\ExplodeSettingTrait;
    use \VuFind\I18n\Translator\TranslatorAwareTrait;

    /**
     * Cache manager
     *
     * @var CacheManager
     */
    protected $cacheManager;

    /**
     * Config
     *
     * @var Config
     */
    protected $config;

    /**
     * Record loader
     *
     * @var Loader
     */
    protected $recordLoader;

    /**
     * Data source configuration
     *
     * @var Config
     */
    protected $dataSourceConfig;

    /**
     * Description provider plugin manager
     *
     * @var DescriptionPluginManager
     */
    protected $descriptionPluginManager;

    /**
     * Language code
     *
     * @var string
     */
    protected $langCode;

    /**
     * Constructor
     *
     * @param SessionSettings          $ss       Session settings (for disableSessionWrites)
     * @param CacheManager             $cm       Cache manager
     * @param Loader                   $loader   Record loader
     * @param DescriptionPluginManager $dpm      Description provider plugin manager
     * @param array                    $config   Main configuration
     * @param array                    $dsConfig Data source configuration
     * @param string                   $langCode Current language code
     */
    public function __construct(
        SessionSettings $ss,
        CacheManager $cm,
        Loader $loader,
        DescriptionPluginManager $dpm,
        array $config,
        array $dsConfig,
        string $langCode
    ) {
        $this->sessionSettings = $ss;
        $this->cacheManager = $cm;
        $this->recordLoader = $loader;
        $this->descriptionPluginManager = $dpm;
        $this->config = $config;
        $this->dataSourceConfig = $dsConfig;
        $this->langCode = $langCode;
    }

    /**
     * Handle a request.
     *
     * @param Params $params Parameter helper from controller
     *
     * @return array [response data, HTTP status code]
     */
    public function handleRequest(Params $params)
    {
        $this->disableSessionWrites();  // avoid session write timing bug

        if (!($id = $params->fromPost('id') ?? $params->fromQuery('id'))) {
            return $this->formatResponse('', self::STATUS_HTTP_BAD_REQUEST);
        }
        $source = $params->fromPost('source') ?? $params->fromQuery('source') ?? 'Solr';

        $cacheDir = $this->cacheManager->getCache('description')->getOptions()->getCacheDir();
        $localFile = "$cacheDir/" . urlencode($id) . '_' . $this->langCode . '.txt';
        $maxAge = $this->config->Content->summarycachetime ?? 1440;

        if (
            is_readable($localFile)
            && time() - filemtime($localFile) < $maxAge * 60
        ) {
            // Load local cache if available
            if (($html = file_get_contents($localFile)) !== false) {
                return $this->formatResponse(compact('html'));
            } else {
                return $this->formatResponse('', self::STATUS_HTTP_ERROR);
            }
        }

        // Try each description provider:
        $driver = $this->recordLoader->load($id, $source);
        $dataSourceId = strtok($id, '.');
        $html = '';
        foreach ($this->getProviders($dataSourceId) as $providerConfig) {
            $provider = $this->descriptionPluginManager->get($providerConfig['id']);
            if ($html = $provider->get($providerConfig['key'], $driver)) {
                break;
            }
        }
        file_put_contents($localFile, $html);
        return $this->formatResponse(compact('html'));
    }

    /**
     * Get a list of active description providers
     *
     * @param string $sourceId Record source ID
     *
     * @return array
     */
    protected function getProviders(string $sourceId): array
    {
        $providers = $this->explodeSetting($this->dataSourceConfig[$sourceId]['descriptions'] ?? '', true, ',');
        $sharedProviders = $this->explodeSetting($this->config['Content']['descriptions'] ?? '', true, ',');

        if ($providers) {
            if (false !== ($offset = array_search('shared', $providers))) {
                // Splice in the common providers:
                array_splice($providers, $offset, 1, $sharedProviders);
            }
        } else {
            $providers = $sharedProviders;
        }

        return array_map(
            function ($s) {
                $parts = explode(':', $s, 2);
                return [
                    'id' => $parts[0],
                    'key' => $parts[1] ?? '',
                ];
            },
            array_values(array_unique(array_filter($providers)))
        );
    }
}
