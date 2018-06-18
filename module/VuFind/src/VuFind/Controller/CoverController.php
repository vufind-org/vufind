<?php
/**
 * Cover Controller
 *
 * PHP Version 5
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
use VuFind\Cover\CachingProxy, VuFind\Cover\Loader;

/**
 * Generates covers for book entries
 *
 * @category VuFind
 * @package  Controller
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
class CoverController extends AbstractBase
{
    /**
     * Cover loader
     *
     * @var Loader
     */
    protected $loader = false;

    /**
     * Caching proxy
     *
     * @var CachingProxy
     */
    protected $proxy = false;

    /**
     * Get the cover cache directory
     *
     * @return string
     */
    protected function getCacheDir()
    {
        return $this->serviceLocator->get('VuFind\CacheManager')
            ->getCache('cover')->getOptions()->getCacheDir();
    }

    /**
     * Get the cover loader object
     *
     * @return Loader
     */
    protected function getLoader()
    {
        // Construct object for loading cover images if it does not already exist:
        if (!$this->loader) {
            $cacheDir = $this->getCacheDir();
            $this->loader = new Loader(
                $this->getConfig(),
                $this->serviceLocator->get('VuFind\ContentCoversPluginManager'),
                $this->serviceLocator->get('VuFindTheme\ThemeInfo'),
                $this->serviceLocator->get('VuFind\Http')->createClient(),
                $cacheDir
            );
            \VuFind\ServiceManager\Initializer::initInstance(
                $this->loader, $this->serviceLocator
            );
        }
        return $this->loader;
    }

    /**
     * Get the caching proxy object
     *
     * @return CachingProxy
     */
    protected function getProxy()
    {
        if (!$this->proxy) {
            $client = $this->serviceLocator->get('VuFind\Http')->createClient();
            $cacheDir = $this->getCacheDir() . '/proxy';
            $config = $this->getConfig()->toArray();
            $whitelist = isset($config['Content']['coverproxyCache'])
                ? (array)$config['Content']['coverproxyCache'] : [];
            $this->proxy = new CachingProxy($client, $cacheDir, $whitelist);
        }
        return $this->proxy;
    }

    /**
     * Convert image parameters into an array for use by the image loader.
     *
     * @return array
     */
    protected function getImageParams()
    {
        $params = $this->params();  // shortcut for readability
        return [
            // Legacy support for "isn" param which has been superseded by isbn:
            'isbn' => $params()->fromQuery('isbn') ?: $params()->fromQuery('isn'),
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
        ];
    }

    /**
     * Send image data for display in the view
     *
     * @return \Zend\Http\Response
     */
    public function showAction()
    {
        $this->disableSessionWrites();  // avoid session write timing bug

        // Special case: proxy a full URL:
        $proxy = $this->params()->fromQuery('proxy');
        if (!empty($proxy)) {
            try {
                $image = $this->getProxy()->fetch($proxy);
                return $this->displayImage(
                    $image->getHeaders()->get('contenttype')->getFieldValue(),
                    $image->getContent()
                );
            } catch (\Exception $e) {
                // If an exception occurs, drop through to the standard case
                // to display an image unavailable graphic.
            }
        }

        // Default case -- use image loader:
        $this->getLoader()->loadImage($this->getImageParams());
        return $this->displayImage();
    }

    /**
     * Return the default 'image not found' information
     *
     * @return \Zend\Http\Response
     */
    public function unavailableAction()
    {
        $this->disableSessionWrites();  // avoid session write timing bug
        $this->getLoader()->loadUnavailable();
        return $this->displayImage();
    }

    /**
     * Support method -- update the view to display the image currently found in the
     * \VuFind\Cover\Loader.
     *
     * @param string $type  Content type of image (null to access loader)
     * @param string $image Image data (null to access loader)
     *
     * @return \Zend\Http\Response
     */
    protected function displayImage($type = null, $image = null)
    {
        $response = $this->getResponse();
        $headers = $response->getHeaders();
        $headers->addHeaderLine(
            'Content-type', $type ?: $this->getLoader()->getContentType()
        );

        // Send proper caching headers so that the user's browser
        // is able to cache the cover images and not have to re-request
        // then on each page load. Default TTL set at 14 days

        $coverImageTtl = (60 * 60 * 24 * 14); // 14 days
        $headers->addHeaderLine(
            'Cache-Control', "maxage=" . $coverImageTtl
        );
        $headers->addHeaderLine(
            'Pragma', 'public'
        );
        $headers->addHeaderLine(
            'Expires', gmdate('D, d M Y H:i:s', time() + $coverImageTtl) . ' GMT'
        );

        $response->setContent($image ?: $this->getLoader()->getImage());
        return $response;
    }
}
