<?php
/**
 * Generates record images.
 *
 * PHP Version 5
 *
 * Copyright (C) Villanova University 2011.
 * Copyright (C) The National Library of Finland 2015-2016.
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
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
namespace Finna\Controller;
use Finna\Cover\Loader;

/**
 * Generates record images.
 *
 * @category VuFind
 * @package  Controller
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
class CoverController extends \VuFind\Controller\CoverController
{
    /**
     * Send image data for display in the view
     *
     * @return \Zend\Http\Response
     */
    public function showAction()
    {
        $this->disableSessionWrites();  // avoid session write timing bug
        $width = $this->params()->fromQuery('w');
        $height = $this->params()->fromQuery('h');
        // Use full-resolution image?
        $fullRes = $this->params()->fromQuery('fullres');

        $loader = $this->getLoader();
        $loader->setParams($width, $height, $fullRes);

        if ($id = $this->params()->fromQuery('id')) {
            $driver = $this->getRecordLoader()->load($id, 'Solr');
            $index = $this->params()->fromQuery('index');

            $this->getLoader()->loadRecordImage($driver, $index ? $index : 0);
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
                        $filename = end(explode('.', $driver->getUniqueID(), 2));
                    }
                } elseif (!empty($params['title'])) {
                    $filename = $params['title'];
                }
                if (isset($filename)) {
                    // Remove any existing extension
                    $filename = preg_replace('/\.jpe?g/', '', $filename);
                    // Replace some characters for cleaner filenames and hopefully
                    // something that can be found with the search
                    $filename = preg_replace('/[^\w_ -]/', ' ', $filename);
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
     * Get the cover loader object
     *
     * @return Loader
     */
    protected function getLoader()
    {
        // Construct object for loading cover images if it does not already exist:
        if (!$this->loader) {
            $cacheDir = $this->getServiceLocator()->get('VuFind\CacheManager')
                ->getCache('cover')->getOptions()->getCacheDir();
            $this->loader = new Loader(
                $this->getConfig(),
                $this->getServiceLocator()->get('VuFind\ContentCoversPluginManager'),
                $this->getServiceLocator()->get('VuFindTheme\ThemeInfo'),
                $this->getServiceLocator()->get('VuFind\Http')->createClient(),
                $cacheDir
            );
            \VuFind\ServiceManager\Initializer::initInstance(
                $this->loader, $this->getServiceLocator()
            );
        }
        return $this->loader;
    }
}
