<?php
/**
 * QRCode Controller
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
 * @author   Luke O'Sullivan <l.osullivan@swansea.ac.uk>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
namespace VuFind\Controller;
use VuFind\QRCode\Loader;

/**
 * Generates qrcodes
 *
 * @category VuFind
 * @package  Controller
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Luke O'Sullivan <l.osullivan@swansea.ac.uk>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
class QRCodeController extends AbstractBase
{
    /**
     * QR Code loader
     *
     * @var Loader
     */
    protected $loader = false;

    /**
     * Get the cover loader object
     *
     * @return Loader
     */
    protected function getLoader()
    {
        // Construct object for QRCodes if it does not already exist:
        if (!$this->loader) {
            $this->loader = new Loader(
                $this->getConfig(),
                $this->serviceLocator->get('VuFindTheme\ThemeInfo')
            );
            \VuFind\ServiceManager\Initializer::initInstance(
                $this->loader, $this->serviceLocator
            );
        }
        return $this->loader;
    }

    /**
     * Send QRCode data for display in the view
     *
     * @return \Zend\Http\Response
     */
    public function showAction()
    {
        $this->disableSessionWrites();  // avoid session write timing bug

        $this->getLoader()->loadQRCode(
            $this->params()->fromQuery('text'),
            [
                'level' => $this->params()->fromQuery('level', "L"),
                'size' => $this->params()->fromQuery('size', "3"),
                'margin' => $this->params()->fromQuery('margin', "4"),
            ]
        );
        return $this->displayQRCode();
    }

    /**
     * Return the default 'qrcode not found' information
     *
     * @return \Zend\Http\Response
     */
    public function unavailableAction()
    {
        $this->disableSessionWrites();  // avoid session write timing bug
        $this->getLoader()->loadUnavailable();
        return $this->displayQRCode();
    }

    /**
     * Support method -- update the view to display the qrcode currently found in the
     * \VuFind\QRCode\Loader.
     *
     * @return \Zend\Http\Response
     */
    protected function displayQRCode()
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
