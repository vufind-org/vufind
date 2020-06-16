<?php
/**
 * GetDescription AJAX handler
 *
 * PHP version 7
 *
 * Copyright (C) The National Library of Finland 2015-2019.
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
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
namespace Finna\AjaxHandler;

use Laminas\Config\Config;
use Laminas\Mvc\Controller\Plugin\Params;
use Laminas\View\Renderer\RendererInterface;
use VuFind\Cache\Manager as CacheManager;
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
class GetDescription extends \VuFind\AjaxHandler\AbstractBase
    implements TranslatorAwareInterface, \VuFindHttp\HttpServiceAwareInterface
{
    use \VuFind\I18n\Translator\TranslatorAwareTrait;
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
     * Record loader
     *
     * @var Loader
     */
    protected $recordLoader;

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
     * @param Loader            $loader   Record loader
     * @param RendererInterface $renderer View renderer
     */
    public function __construct(SessionSettings $ss, CacheManager $cm,
        Config $config, Loader $loader, RendererInterface $renderer
    ) {
        $this->sessionSettings = $ss;
        $this->cacheManager = $cm;
        $this->config = $config;
        $this->recordLoader = $loader;
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

        $id = $params->fromPost('id', $params->fromQuery('id'));

        if (!$id) {
            return $this->formatResponse('', self::STATUS_HTTP_BAD_REQUEST);
        }

        $cacheDir = $this->cacheManager->getCache('description')->getOptions()
            ->getCacheDir();

        $localFile = "$cacheDir/" . urlencode($id) . '.txt';

        $maxAge = isset($this->config->Content->summarycachetime)
            ? $this->config->Content->summarycachetime : 1440;

        if (is_readable($localFile)
            && time() - filemtime($localFile) < $maxAge * 60
        ) {
            // Load local cache if available
            if (($content = file_get_contents($localFile)) !== false) {
                return $this->formatResponse(['html' => $content]);
            } else {
                return $this->formatResponse('', self::STATUS_HTTP_ERROR);
            }
        } else {
            // Get URL
            $driver = $this->recordLoader->load($id, 'Solr');
            $url = $driver->getDescriptionURL();
            // Get, manipulate, save and display content if available
            if ($url) {
                $result = $this->httpService->get($url, [], 60);
                if ($result->isSuccess() && ($content = $result->getBody())) {
                    $encoding = mb_detect_encoding(
                        $content, ['UTF-8', 'ISO-8859-1']
                    );
                    if ('UTF-8' !== $encoding) {
                        $content = utf8_encode($content);
                    }
                    // Remove head tag, so no titles will be printed.
                    $content = preg_replace(
                        '/<head[^>]*>(.*?)<\/head>/si',
                        '',
                        $content
                    );

                    $content = preg_replace('/.*<.B>(.*)/', '\1', $content);
                    $content = strip_tags($content, '<br>');

                    // Trim leading and trailing whitespace
                    $content = trim($content);

                    // Replace line breaks with <br>
                    $content = preg_replace(
                        '/(\r\n|\n|\r){3,}/', '<br><br>', $content
                    );

                    file_put_contents($localFile, $content);

                    return $this->formatResponse(['html' => $content]);
                }
            }
            $language = $this->translator->getLocale();
            if ($summary = $driver->getSummary($language)) {
                $summary = implode("\n\n", $summary);

                // Replace double hash with a <br>
                $summary = str_replace('##', "\n\n", $summary);

                // Process markdown
                $summary = $this->renderer->plugin('markdown')->toHtml($summary);

                return $this->formatResponse(['html' => $summary]);
            }
        }
        return $this->formatResponse(['html' => '']);
    }
}
