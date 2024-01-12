<?php

/**
 * QRCode Controller
 *
 * PHP version 8
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
use VuFind\Session\Settings as SessionSettings;

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
class QRCodeController extends \Laminas\Mvc\Controller\AbstractActionController
{
    /**
     * QR Code loader
     *
     * @var Loader
     */
    protected $loader = false;

    /**
     * Session settings
     *
     * @var SessionSettings
     */
    protected $sessionSettings = null;

    /**
     * Constructor
     *
     * @param Loader          $loader QR Code Loader
     * @param SessionSettings $ss     Session settings
     */
    public function __construct(Loader $loader, SessionSettings $ss)
    {
        $this->loader = $loader;
        $this->sessionSettings = $ss;
    }

    /**
     * Send QRCode data for display in the view
     *
     * @return \Laminas\Http\Response
     */
    public function showAction()
    {
        $this->sessionSettings->disableWrite(); // avoid session write timing bug

        $params = [];
        foreach ($this->loader->getDefaults() as $param => $default) {
            $params[$param] = $this->params()->fromQuery($param, $default);
        }
        $this->loader->loadQRCode($this->params()->fromQuery('text'), $params);
        return $this->displayQRCode();
    }

    /**
     * Return the default 'qrcode not found' information
     *
     * @return \Laminas\Http\Response
     */
    public function unavailableAction()
    {
        $this->sessionSettings->disableWrite(); // avoid session write timing bug
        $this->loader->loadUnavailable();
        return $this->displayQRCode();
    }

    /**
     * Support method -- update the view to display the qrcode currently found in the
     * \VuFind\QRCode\Loader.
     *
     * @return \Laminas\Http\Response
     */
    protected function displayQRCode()
    {
        $response = $this->getResponse();
        $response->getHeaders()->addHeaderLine(
            'Content-type',
            $this->loader->getContentType()
        );
        $response->setContent($this->loader->getImage());
        return $response;
    }
}
