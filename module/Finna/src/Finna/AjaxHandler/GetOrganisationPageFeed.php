<?php
/**
 * GetOrganisationPageFeed AJAX handler
 *
 * PHP version 7
 *
 * Copyright (C) The National Library of Finland 2016-2018.
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

use Finna\Feed\Feed as FeedService;
use Laminas\Config\Config;
use Laminas\Mvc\Controller\Plugin\Params;
use Laminas\Mvc\Controller\Plugin\Url;
use Laminas\View\Renderer\RendererInterface;
use VuFind\Session\Settings as SessionSettings;

/**
 * GetOrganisationPageFeed AJAX handler
 *
 * @category VuFind
 * @package  AJAX
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class GetOrganisationPageFeed extends \VuFind\AjaxHandler\AbstractBase
    implements \Laminas\Log\LoggerAwareInterface
{
    use FeedTrait;
    use \VuFind\Log\LoggerAwareTrait;

    /**
     * Organisation page RSS configuration
     *
     * @var Config
     */
    protected $config;

    /**
     * Feed service
     *
     * @var FeedService
     */
    protected $feedService;

    /**
     * View renderer
     *
     * @var RendererInterface
     */
    protected $renderer;

    /**
     * URL helper
     *
     * @var Url
     */
    protected $url;

    /**
     * Constructor
     *
     * @param SessionSettings   $ss       Session settings
     * @param Config            $config   Organisation page RSS configuration
     * @param FeedService       $fs       Feed service
     * @param RendererInterface $renderer View renderer
     * @param Url               $url      URL helper
     */
    public function __construct(SessionSettings $ss, Config $config,
        FeedService $fs, RendererInterface $renderer, Url $url
    ) {
        $this->sessionSettings = $ss;
        $this->config = $config;
        $this->feedService = $fs;
        $this->renderer = $renderer;
        $this->url = $url;
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
        $config = $this->config;
        $id = $params->fromPost('id', $params->fromQuery('id'));
        $url = $params->fromPost('url', $params->fromQuery('url'));
        if (!$id || !$url) {
            return $this->formatResponse('', self::STATUS_HTTP_BAD_REQUEST);
        }

        $url = urldecode($url);
        try {
            $feedConfig = ['url' => $url];

            if (isset($config[$id])) {
                $feedConfig['result'] = $config[$id]->toArray();
            } else {
                $feedConfig['result'] = ['items' => 5];
            }
            $feedConfig['result']['type'] = 'grid';
            $feedConfig['result']['active'] = 1;

            $serverHelper = $this->renderer->plugin('serverurl');
            $homeUrl = $serverHelper($this->url->fromRoute('home'));

            $feed = $this->feedService->readFeedFromUrl(
                $id,
                $url,
                $feedConfig,
                $homeUrl
            );
        } catch (\Exception $e) {
            return $this->handleError(
                "getOrganisationPageFeed: error reading feed from url: {$url}",
                $e->getMessage()
            );
        }

        if (!$feed) {
            return $this->handleError(
                "getOrganisationPageFeed: error reading feed from url: {$url}"
            );
        }

        return $this->formatResponse(
            $this->formatFeed($feed, $this->config, $this->renderer, $url)
        );
    }

    /**
     * Return an error response in JSON format and log the error message.
     *
     * @param string $outputMsg  Message to include in the JSON response.
     * @param string $logMsg     Message to output to the error log.
     * @param int    $httpStatus HTTPs status of the JSOn response.
     *
     * @return \Laminas\Http\Response
     */
    protected function handleError($outputMsg, $logMsg = '', $httpStatus = 400)
    {
        $this->logError(
            $outputMsg . ($logMsg ? " ({$logMsg})" : null)
        );

        return $this->formatResponse($outputMsg, $httpStatus);
    }
}
