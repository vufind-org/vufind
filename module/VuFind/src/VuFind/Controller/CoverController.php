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
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA    02111-1307    USA
 *
 * @category VuFind2
 * @package  Controller
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://www.vufind.org  Main Page
 */
namespace VuFind\Controller;
use VuFind\Config\Reader as ConfigReader, VuFind\Cover\Loader;

/**
 * Generates covers for book entries
 *
 * @category VuFind2
 * @package  Controller
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://www.vufind.org  Main Page
 */
class CoverController extends AbstractBase
{
    protected $loader = false;

    /**
     * Get the cover loader object
     *
     * @return Loader
     */
    protected function getLoader()
    {
        // Construct object for loading cover images if it does not already exist:
        if (!$this->loader) {
            $this->loader = new Loader(
                ConfigReader::getConfig(),
                $this->getServiceLocator()->get('VuFind\Http')->createClient(),
                $this->getServiceLocator()->get('VuFind\CacheManager')->getCacheDir()
            );
            \VuFind\ServiceManager\Initializer::initInstance(
                $this->loader, $this->getServiceLocator()
            );
        }
        return $this->loader;
    }

    /**
     * Send image data for display in the view
     *
     * @return \Zend\Http\Response
     */
    public function showAction()
    {
        $this->writeSession();  // avoid session write timing bug
        $this->getLoader()->loadImage(
            $this->params()->fromQuery('isn'),
            $this->params()->fromQuery('size'),
            $this->params()->fromQuery('contenttype')
        );
        return $this->displayImage();
    }

    /**
     * Return the default 'image not found' information
     *
     * @return \Zend\Http\Response
     */
    public function unavailableAction()
    {
        $this->writeSession();  // avoid session write timing bug
        $this->getLoader()->loadUnavailable();
        return $this->displayImage();
    }

    /**
     * Support method -- update the view to display the image currently found in the
     * \VuFind\Cover\Loader.
     *
     * @return \Zend\Http\Response
     */
    protected function displayImage()
    {
        $response = $this->getResponse();
        $headers = $response->getHeaders();
        $headers->addHeaderLine(
            'Content-type', $this->getLoader()->getContentType()
        );
        $response->setContent($this->getLoader()->getImage());
        return $response;
    }
}

