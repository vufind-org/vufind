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
use VuFind\Cover\Loader;

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
    protected $loader;

    /**
     * Constructor
     */
    public function __construct()
    {
        // Construct object for loading cover images:
        $this->loader = new Loader();
        parent::__construct();
    }

    /**
     * Send image data for display in the view
     *
     * @return void
     */
    public function showAction()
    {
        $this->loader->loadImage(
            $this->params()->fromQuery('isn'),
            $this->params()->fromQuery('size'),
            $this->params()->fromQuery('contenttype')
        );
        return $this->displayImage();
    }

    /**
     * Return the default 'image not found' information
     *
     * @return void
     */
    public function unavailableAction()
    {
        $this->loader->loadUnavailable();
        return $this->displayImage();
    }

    /**
     * Support method -- update the view to display the image currently found in the
     * \VuFind\Cover\Loader.
     *
     * @return void
     */
    protected function displayImage()
    {
        $response = $this->getResponse();
        $headers = $response->getHeaders();
        $headers->addHeaderLine(
            'Content-type', $this->loader->getContentType()
        );
        $response->setContent($this->loader->getImage());
        return $response;
    }
}

