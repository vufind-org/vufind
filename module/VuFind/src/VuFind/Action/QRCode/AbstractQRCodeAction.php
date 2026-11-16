<?php

/**
 * Abstract base class for QRCode actions.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2011.
 * Copyright (C) The National Library of Finland 2026.
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
 * along with this program; if not, see
 * <https://www.gnu.org/licenses/>.
 *
 * @category VuFind
 * @package  Action
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Luke O'Sullivan <l.osullivan@swansea.ac.uk>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */

namespace VuFind\Action\QRCode;

use Psr\Http\Message\ResponseInterface;
use VuFind\Action\AbstractTemplateRenderingAction;
use VuFind\QRCode\Loader;

/**
 * Abstract base class for QRCode actions.
 *
 * @category VuFind
 * @package  Action
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Luke O'Sullivan <l.osullivan@swansea.ac.uk>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
abstract class AbstractQRCodeAction extends AbstractTemplateRenderingAction
{
    /**
     * Constructor.
     *
     * @param protected Loader $qrCodeLoader QRCode loader
     */
    public function __construct(
        protected Loader $qrCodeLoader,
    ) {
        parent::__construct();
    }

    /**
     * Support method -- return the rendered qrcode currently found in QRCode loader.
     *
     * @param ResponseInterface $response Response
     *
     * @return ResponseInterface
     */
    protected function displayQRCode(ResponseInterface $response): ResponseInterface
    {
        $response = $response->withHeader('Content-Type', $this->qrCodeLoader->getContentType());
        $response->getBody()->write($this->qrCodeLoader->getImage());
        return $response;
    }
}
