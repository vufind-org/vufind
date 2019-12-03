<?php
/**
 * GetPiwikPopularSearches AJAX handler
 *
 * PHP version 7
 *
 * Copyright (C) The National Library of Finland 2018-2019.
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
 * @package  AJAX
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
namespace Finna\AjaxHandler;

use VuFind\Cache\Manager as CacheManager;
use VuFind\I18n\Translator\TranslatorAwareInterface;
use VuFind\Session\Settings as SessionSettings;
use Zend\Config\Config;
use Zend\Mvc\Controller\Plugin\Params;
use Zend\View\Renderer\RendererInterface;

/**
 * GetPiwikPopularSearches AJAX handler
 *
 * @category VuFind
 * @package  AJAX
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class GetPiwikPopularSearches extends \VuFind\AjaxHandler\AbstractBase
    implements TranslatorAwareInterface, \VuFindHttp\HttpServiceAwareInterface,
    \Zend\Log\LoggerAwareInterface
{
    use \VuFind\I18n\Translator\TranslatorAwareTrait;
    use \VuFind\Log\LoggerAwareTrait;
    use \VuFindHttp\HttpServiceAwareTrait;

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
     * View renderer
     *
     * @var RendererInterface
     */
    protected $renderer;

    /**
     * Constructor
     *
     * @param SessionSettings   $ss       Session settings
     * @param CacheManager      $cm       Cache manager
     * @param Config            $config   Main configuration
     * @param RendererInterface $renderer View renderer
     */
    public function __construct(SessionSettings $ss, CacheManager $cm,
        Config $config, RendererInterface $renderer
    ) {
        $this->sessionSettings = $ss;
        $this->cacheManager = $cm;
        $this->config = $config;
        $this->renderer = $renderer;
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

        if (empty($this->config->Piwik->url)
            || empty($this->config->Piwik->site_id)
            || empty($this->config->Piwik->token_auth)
        ) {
            return $this->formatResponse('', self::STATUS_HTTP_BAD_REQUEST);
        }

        $params = [
            'module'       => 'API',
            'format'       => 'json',
            'method'       => 'Actions.getSiteSearchKeywords',
            'idSite'       => $this->config->Piwik->site_id,
            'period'       => 'range',
            'date'         => date('Y-m-d', strtotime('-30 days')) . ',' .
                              date('Y-m-d'),
            'token_auth'   => $this->config->Piwik->token_auth
        ];
        $url = $this->config->Piwik->url;

        $cacheDir = $this->cacheManager->getCache('object')->getOptions()
            ->getCacheDir();

        $cacheFile = "$cacheDir/piwik-popular-searches-"
            . md5(implode('|', $params) . "|$url") . '.html';

        // Minutes
        $maxAge = isset($this->config->Piwik->querycachetime)
            ? $this->config->Piwik->querycachetime : 60;

        if (is_readable($cacheFile)
            && time() - filemtime($cacheFile) < $maxAge * 60
        ) {
            // Load local cache if available
            if (($content = file_get_contents($cacheFile)) !== false) {
                return $this->formatResponse(['html' => $content]);
            }
        }

        // Get URL
        $client = $this->httpService->createClient($url);
        $client->setParameterGet($params);
        $result = $client->send();
        if (!$result->isSuccess()) {
            $this->logError("Piwik request for popular searches failed, url $url");
            return $this->formatResponse('', self::STATUS_HTTP_ERROR);
        }

        $response = json_decode($result->getBody(), true);
        if (isset($response['result']) && $response['result'] == 'error') {
            $this->logError(
                "Piwik request for popular searches failed, url $url, message: "
                . $response['message']
            );
            return $this->formatResponse('', self::STATUS_HTTP_ERROR);
        }
        $searchPhrases = [];
        foreach ($response as $item) {
            $label = $item['label'];
            // Strip index from the terms
            $pos = strpos($label, '|');
            if ($pos > 0) {
                $label = substr($label, $pos + 1);
            }
            $label = trim($label);
            if (strncmp($label, '(', 1) == 0) {
                // Ignore searches that begin with a parenthesis
                // because they are likely to be advanced searches
                continue;
            } elseif ($label === '-' || $label === '') {
                // Ignore empty searches
                continue;
            }
            $searchPhrases[$label]
                = !isset($item['nb_actions']) || null === $item['nb_actions']
                ? $item['nb_visits']
                : $item['nb_actions'];
        }
        // Order by hits
        arsort($searchPhrases);

        $html = $this->renderer->render(
            'ajax/piwik-popular-searches.phtml', ['searches' => $searchPhrases]
        );

        file_put_contents($cacheFile, $html);

        return $this->formatResponse(compact('html'));
    }
}
