<?php

/**
 * Cover Controller
 *
 * PHP version 7
 *
 * Copyright (C) Villanova University 2011.
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
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
 *
 * @category VuFind
 * @package  Controller
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */

namespace VuFind\Controller;

use VuFind\Cover\CachingProxy;
use VuFind\Cover\Loader;
use VuFind\Session\Settings as SessionSettings;

/**
 * Generates covers for book entries
 *
 * @category VuFind
 * @package  Controller
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
class CoverController extends \Laminas\Mvc\Controller\AbstractActionController
{
    /**
     * Cover loader
     *
     * @var Loader
     */
    protected $loader;

    /**
     * Proxy loader
     *
     * @var CachingProxy
     */
    protected $proxy;

    /**
     * Session settings
     *
     * @var SessionSettings
     */
    protected $sessionSettings = null;

    /**
     * Constructor
     *
     * @param Loader          $loader Cover loader
     * @param CachingProxy    $proxy  Proxy loader
     * @param SessionSettings $ss     Session settings
     */
    public function __construct(
        Loader $loader,
        CachingProxy $proxy,
        SessionSettings $ss
    ) {
        $this->loader = $loader;
        $this->proxy = $proxy;
        $this->sessionSettings = $ss;
    }

    /**
     * Convert image parameters into an array for use by the image loader.
     *
     * @return array
     */
    protected function getImageParams()
    {
        $params = $this->params();  // shortcut for readability
        $isbns = null;
        // Legacy support for "isn", "isbn" param which has been superseded by isbns:
        foreach (['isbns', 'isbn', 'isn'] as $identification) {
            if ($isbns = $params()->fromQuery($identification)) {
                $isbns = (array)$isbns;
                break;
            }
        }
        return [
            'isbns' => $isbns,
            'size' => $params()->fromQuery('size'),
            'type' => $params()->fromQuery('contenttype'),
            'title' => $params()->fromQuery('title'),
            'author' => $params()->fromQuery('author'),
            'callnumber' => $params()->fromQuery('callnumber'),
            'issn' => $params()->fromQuery('issn'),
            'oclc' => $params()->fromQuery('oclc'),
            'upc' => $params()->fromQuery('upc'),
            'recordid' => $params()->fromQuery('recordid'),
            'source' => $params()->fromQuery('source'),
            'nbn' => $params()->fromQuery('nbn'),
            'ismn' => $params()->fromQuery('ismn'),
        ];
    }

    /**
     * Send image data for display in the view
     *
     * @return \Laminas\Http\Response
     */
    public function showAction()
    {
        $this->sessionSettings->disableWrite(); // avoid session write timing bug

        // Special case: proxy a full URL:
        $url = $this->params()->fromQuery('proxy');
        if (!empty($url)) {
            try {
                $image = $this->proxy->fetch($url);
                return $this->displayImage(
                    $image->getHeaders()->get('content-type')->getFieldValue(),
                    $image->getContent()
                );
            } catch (\Exception $e) {
                // If an exception occurs, drop through to the standard case
                // to display an image unavailable graphic.
            }
        }

        // Default case -- use image loader:
        $this->loader->loadImage($this->getImageParams());
        return $this->displayImage();
    }

    /**
     * Return the default 'image not found' information
     *
     * @return \Laminas\Http\Response
     */
    public function unavailableAction()
    {
        $this->sessionSettings->disableWrite(); // avoid session write timing bug
        $this->loader->loadUnavailable();
        return $this->displayImage();
    }

    /**
     * Support method -- update the view to display the image currently found in the
     * \VuFind\Cover\Loader.
     *
     * @param string $type  Content type of image (null to access loader)
     * @param string $image Image data (null to access loader)
     *
     * @return \Laminas\Http\Response
     */
    protected function displayImage($type = null, $image = null)
    {
        $response = $this->getResponse();
        $headers = $response->getHeaders();
        $headers->addHeaderLine(
            'Content-type',
            $type ?: $this->loader->getContentType()
        );

        // Send proper caching headers so that the user's browser
        // is able to cache the cover images and not have to re-request
        // then on each page load. Default TTL set at 14 days

        $coverImageTtl = (60 * 60 * 24 * 14); // 14 days
        $headers->addHeaderLine(
            'Cache-Control',
            "maxage=" . $coverImageTtl
        );
        $headers->addHeaderLine(
            'Pragma',
            'public'
        );
        $headers->addHeaderLine(
            'Expires',
            gmdate('D, d M Y H:i:s', time() + $coverImageTtl) . ' GMT'
        );

        $response->setContent($image ?: $this->loader->getImage());
        return $response;
    }
}
