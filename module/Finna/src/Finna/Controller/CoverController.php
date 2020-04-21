<?php
/**
 * Generates record images.
 *
 * PHP version 7
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

use VuFind\Cover\CachingProxy;
use VuFind\Cover\Loader;
use VuFind\Session\Settings as SessionSettings;

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
     * @var \Zend\Config\Config
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
     * @param Loader                $loader       Cover loader
     * @param CachingProxy          $proxy        Proxy loader
     * @param SessionSettings       $ss           Session settings
     * @param \Zend\Config\Config   $datasources  Data source settings
     * @param \VuFind\Record\Loader $recordLoader Record loader
     */
    public function __construct(Loader $loader, CachingProxy $proxy,
        SessionSettings $ss, \Zend\Config\Config $datasources,
        \VuFind\Record\Loader $recordLoader
    ) {
        parent::__construct($loader, $proxy, $ss);
        $this->datasourceConfig = $datasources;
        $this->recordLoader = $recordLoader;
    }

    /**
     * Function to download images from the provider instead of cache
     *
     * @return \Zend\Http\Response
     */
    public function downloadAction()
    {
        $this->sessionSettings->disableWrite(); // avoid session write timing bug
        $allowedSizes = ['original', 'master'];
        $params = $this->params();
        $size = $params->fromQuery('size');
        $format = $params->fromQuery('format', 'jpg');
        $response = $this->getResponse();

        if (($id = $params->fromQuery('id')) && in_array($size, $allowedSizes)) {
            $driver = $this->recordLoader->load(
                $id, $params->fromQuery('source') ?? DEFAULT_SEARCH_BACKEND
            );
            $index = (int)$params->fromQuery('index');
            $images = $driver->getAllImages();
            $highResolution = $images[$index]['highResolution'] ?? [];
            if (isset($highResolution[$size][$format]['url'])) {
                $url = $highResolution[$size][$format]['url'];
                $res = $this->loader->loadExternalImage(
                    $url, $format, "{$id}_{$index}_{$size}.{$format}"
                );
                if (!$res) {
                    $response->setStatusCode(500);
                }
            } else {
                $response->setStatusCode(404);
            }
        } else {
            $response->setStatusCode(400);
        }

        return $response;
    }

    /**
     * Send image data for display in the view
     *
     * @return \Zend\Http\Response
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
        $recordId = $params->fromQuery('recordid');
        $datasourceId = strtok($recordId, '.');
        $datasourceCovers
            = isset($this->datasourceConfig->$datasourceId->coverimages)
                ? $this->datasourceConfig->$datasourceId->coverimages
                : null;
        $this->loader->setDatasourceConfig($datasourceCovers);

        if ($id = $params->fromQuery('id')) {
            $driver = $this->recordLoader->load(
                $id, $params->fromQuery('source') ?? DEFAULT_SEARCH_BACKEND
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
        if ($response instanceof \Zend\Http\PhpEnvironment\Response) {
            $headers = $response->getHeaders();
            $contentType = $headers->get('Content-Type');
            if ($contentType && $contentType->match('image/jpeg')) {
                $params = $this->getImageParams();
                if (!empty($params['isbn'])) {
                    $filename = $params['isbn'];
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
                        'Content-Disposition', "inline; filename=$filename"
                    );
                }
            }
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
        $params['invalid_isbn'] =  $this->params()->fromQuery('invisbn');
        return $params;
    }
}
