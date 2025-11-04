<?php

/**
 * File Loader.
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2021.
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
 * @package  File
 * @author   Juha Luoma <juha.luoma@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */

namespace Finna\File;

use GuzzleHttp\RequestOptions;
use Psr\Http\Message\ResponseInterface;
use VuFind\Cache\Manager as CacheManager;
use VuFind\Config\Config;
use VuFind\Http\GuzzleService;

/**
 * File loader
 *
 * @category VuFind
 * @package  File
 * @author   Juha Luoma <juha.luoma@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
class Loader implements \VuFindHttp\HttpServiceAwareInterface
{
    use \VuFindHttp\HttpServiceAwareTrait;
    use \VuFind\Log\LoggerAwareTrait;

    /**
     * Constructor
     *
     * @param CacheManager  $cacheManager  Cache Manager
     * @param Config        $config        Main configuration
     * @param GuzzleService $guzzleService Guzzle HTTP Service
     */
    public function __construct(
        protected CacheManager $cacheManager,
        protected Config $config,
        protected GuzzleService $guzzleService
    ) {
    }

    /**
     * Convert format to mime
     *
     * @param string $format Format to convert.
     *
     * @return string
     */
    protected function getMimeType(string $format): string
    {
        $detector = new \League\MimeTypeDetection\FinfoMimeTypeDetector();
        $mimeType = $detector->detectMimeTypeFromPath("foo.$format");
        return $mimeType ?: 'application/octet-stream';
    }

    /**
     * Download a file to cache
     *
     * @param string $url           Url to download
     * @param string $fileName      Name of the file to save
     * @param string $configSection Section of the configuration to get cacheTime from
     * @param string $cacheId       Cache to use
     *
     * @return array
     */
    public function getFile(
        string $url,
        string $fileName,
        string $configSection,
        string $cacheId
    ): array {
        $cacheDir = $this->cacheManager->getCache($cacheId)->getOptions()->getCacheDir();
        $path = "$cacheDir/$fileName";
        $maxAge = $this->config->$configSection->cacheTime ?? 43200;
        $result = true;
        $error = '';
        if (!file_exists($path) || time() - filemtime($path) > $maxAge * 60) {
            $client = $this->httpService->createClient(
                $url,
                \Laminas\Http\Request::METHOD_GET,
                300
            );
            $client->setStream($path);
            $client->setOptions(['useragent' => 'VuFind']);
            $adapter = new \Laminas\Http\Client\Adapter\Curl();
            $client->setAdapter($adapter);
            $response = $client->send();

            if (!$response->isSuccess()) {
                $error = "Failed to retrieve file from $url: "
                    . $response->getStatusCode() . ' ' . $response->getReasonPhrase();
                $this->debug($error);
                $result = false;
            }
        }

        return compact('result', 'path', 'error');
    }

    /**
     * Proxy a file and set proper headers, useful if download has no information
     *
     * @param string $url      Url to load the file from
     * @param string $fileName Display name of the file to download
     * @param string $format   File format
     *
     * @return bool True if success, false if not
     */
    public function proxyFileLoad(
        string $url,
        string $fileName,
        string $format
    ): bool {
        $stdoutStream = new StdoutStream();
        $client = $this->guzzleService->createClient($url, 300);
        $response = $client->request(
            'GET',
            $url,
            [
                RequestOptions::SINK => $stdoutStream,
                RequestOptions::ON_HEADERS => function (ResponseInterface $response) use (
                    &$stdoutStream,
                    $format,
                    $fileName
                ): void {
                    // Send headers and start output when the correct status code is received:
                    if ($response->getStatusCode() === 200) {
                        $contentType = $response->getHeader('Content-Type');
                        if (!$contentType) {
                            $contentType = [$this->getMimeType($format)];
                        }
                        if (ob_get_level()) {
                            ob_end_clean();
                        }
                        header('Pragma: public');
                        header("Content-Type: {$contentType[0]}");
                        header("Content-disposition: attachment; filename=\"{$fileName}\"");
                        header('Cache-Control: public');
                        $stdoutStream->setOutputActive(true);
                    }
                },
            ],
        );
        if ($response->getStatusCode() !== 200) {
            $this->logError(
                "Failed to retrieve file from $url: " . $response->getStatusCode() . ' ' . $response->getReasonPhrase()
            );
            return false;
        }
        return true;
    }
}
