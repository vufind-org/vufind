<?php

/**
 * Generates record images.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2011.
 * Copyright (C) The National Library of Finland 2015-2020.
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License version 2,
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.    See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA    02111-1307    USA
 *
 * @category VuFind
 * @package  Controller
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @author   Kalle Pyykkönen <kalle.pyykkonen@helsinki.fi>
 * @author   Juha Luoma <juha.luoma@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */

namespace Finna\Controller;

use Finna\Cover\Loader;
use VuFind\Cover\CachingProxy;
use VuFind\Db\Service\AccessTokenService;
use VuFind\RecordDriver\Missing;
use VuFind\Session\Settings as SessionSettings;

use function in_array;

/**
 * Generates record images.
 *
 * @category VuFind
 * @package  Controller
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @author   Kalle Pyykkönen <kalle.pyykkonen@helsinki.fi>
 * @author   Juha Luoma <juha.luoma@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
class CoverController extends \VuFind\Controller\CoverController
{
    /**
     * Data source configuration
     *
     * @var \VuFind\Config\Config
     */
    protected $datasourceConfig;

    /**
     * Record loader
     *
     * @var VuFind\Record\Loader
     */
    protected $recordLoader;

    /**
     * Constructor
     *
     * @param Loader                $loader             Cover loader
     * @param CachingProxy          $proxy              Proxy loader
     * @param SessionSettings       $ss                 Session settings
     * @param \VuFind\Config\Config $datasources        Data source settings
     * @param \VuFind\Record\Loader $recordLoader       Record loader
     * @param array                 $config             Main config
     * @param \Finna\File\Loader    $fileLoader         File loader
     * @param AccessTokenService    $accessTokenService Access token service
     */
    public function __construct(
        Loader $loader,
        CachingProxy $proxy,
        SessionSettings $ss,
        \VuFind\Config\Config $datasources,
        \VuFind\Record\Loader $recordLoader,
        array $config,
        protected \Finna\File\Loader $fileLoader,
        protected AccessTokenService $accessTokenService
    ) {
        parent::__construct($loader, $proxy, $ss, $config);
        $this->datasourceConfig = $datasources;
        $this->recordLoader = $recordLoader;
    }

    /**
     * Send image data for display in the view
     *
     * @return \Laminas\Http\Response
     */
    public function showAction()
    {
        $this->sessionSettings->disableWrite(); // avoid session write timing bug

        $params = $this->params();

        $width = (int)$params->fromQuery('w');
        $height = (int)$params->fromQuery('h');
        $size = $params->fromQuery('fullres')
            ? 'large' : $params->fromQuery('size');

        if ($size && !in_array($size, ['master', 'large', 'medium', 'small'])) {
            $response = $this->getResponse();
            $response->setStatusCode(400);
            return $response;
        }

        $this->loader->setParams($width, $height, $size);

        // Cover image configuration for current datasource
        $datasourceCovers = '';
        if ($recordId = $params->fromQuery('recordid')) {
            $datasourceId = strtok($recordId, '.');
            $datasourceCovers = $this->datasourceConfig->$datasourceId->coverimages
                ?? '';
        }
        $this->loader->setDatasourceConfig($datasourceCovers);

        if ($id = $params->fromQuery('id')) {
            $driver = $this->recordLoader->load(
                $id,
                $params->fromQuery('source') ?? DEFAULT_SEARCH_BACKEND
            );
            $index = (int)$params->fromQuery('index');
            $this->loader->loadRecordImage($driver, $index, $size);
            $response = parent::displayImage();
        } else {
            // Redirect book covers to VuFind's cover controller
            $response = parent::showAction();
        }

        // Add a filename to the headers so that saving an image defaults to a
        // sensible filename
        if ($response instanceof \Laminas\Http\PhpEnvironment\Response) {
            $headers = $response->getHeaders();
            $contentType = $headers->get('Content-Type');
            if ($contentType && $contentType->match('image/jpeg')) {
                $params = $this->getImageParams();
                if (!empty($params['isbns'])) {
                    $filename = reset($params['isbns']);
                } elseif (!empty($params['issn'])) {
                    $filename = $params['issn'];
                } elseif (isset($driver)) {
                    if ($isbn = $driver->tryMethod('getCleanISBN')) {
                        $filename = $isbn;
                    } elseif ($issn = $driver->tryMethod('getCleanISSN')) {
                        $filename = $issn;
                    } else {
                        // Strip the data source prefix
                        $parts = explode('.', $driver->getUniqueID(), 2);
                        $filename = end($parts);
                        // Remove beginning of the url from filename by exploding
                        // it by %2F. Assign last part of it to the filename
                        $parts = explode('%2F', $filename);
                        $filename = end($parts);
                    }
                } elseif (!empty($params['title'])) {
                    $filename = $params['title'];
                }
                if (isset($filename)) {
                    // Remove any existing extension
                    $filename = preg_replace('/\.jpe?g/', '', $filename);
                    // Replace some characters for cleaner filenames and hopefully
                    // something that can be found with the search
                    $filename = preg_replace('/[^\w_ -]/', '_', $filename);
                    $filename .= '.jpg';
                    $headers->addHeaderLine(
                        'Content-Disposition',
                        "inline; filename=$filename"
                    );
                }
            }
        }
        return $response;
    }

    /**
     * Pipe an image from provider, without caching. Requires permissions to be used.
     * Permission must be granted for the datasource in datasources.ini.
     *
     * @return \Laminas\Http\Response
     */
    public function pipeAction(): \Laminas\Http\Response
    {
        $this->sessionSettings->disableWrite(); // avoid session write timing bug
        $key = $this->params()->fromHeader('X-API-KEY');
        $response = $this->getResponse();
        // TODO: temporary way of implementing api-key functionality
        // After permissions and api-keys have been implemented, adjust this to match
        // the new functionality
        if (!$key || !$this->accessTokenService->isApiKeyActive($key->getFieldValue())) {
            $response->setStatusCode(401);
            return $response;
        }
        $params = $this->params();
        $id = $params->fromQuery('id');
        if (!$id) {
            $response->setStatusCode(400);
            return $response;
        }
        $driver = $this->recordLoader->load(
            $id,
            $params->fromQuery('source') ?? DEFAULT_SEARCH_BACKEND,
            true
        );
        if ($driver instanceof Missing) {
            $response->setStatusCode(404);
            return $response;
        }
        $datasource = $driver->getDatasource();
        $datasourceAllowsPiping = $this->datasourceConfig[$datasource]['permissions']['image_piping'] ?? false;
        if (!$datasourceAllowsPiping) {
            $response->setStatusCode(403);
            return $response;
        }
        $size = $this->params()->fromQuery('size');
        $index = $this->params()->fromQuery('index');
        $image = $driver->tryMethod('getRecordImage', [$size, $index]);
        if (!isset($image['url'])) {
            $response->setStatusCode(404);
            return $response;
        }
        $format = $this->params()->fromQuery('format', 'jpg');
        $formedFilename = "$id-$index.$format";
        $success = $this->fileLoader->proxyFileLoad($image['url'], $formedFilename, $format);
        if (!$success) {
            $response->setStatusCode(500);
        }
        return $response;
    }

    /**
     * Convert image parameters into an array for use by the image loader.
     *
     * @return array
     */
    protected function getImageParams()
    {
        $params = parent::getImageParams();
        $params['invisbn'] =  $this->params()->fromQuery('invisbn');
        return $params;
    }
}
