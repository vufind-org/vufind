<?php
/**
 * Barcode Controller
 *
 * PHP version 7
 *
 * Copyright (C) The National Library of Finland 2017.
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
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
namespace Finna\Controller;

/**
 * Generates barcodes
 *
 * @category VuFind
 * @package  Controller
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
class BarcodeController extends \VuFind\Controller\AbstractBase
{
    /**
     * Send barcode data for display in the view
     *
     * @return \Laminas\Http\Response
     */
    public function showAction()
    {
        $this->disableSessionWrites();  // avoid session write timing bug

        $htmlGenerator = new \Picqer\Barcode\BarcodeGeneratorHTML();
        $code = $this->getRequest()->getQuery('code', '');
        $type = $this->getRequest()->getQuery('type', $htmlGenerator::TYPE_CODE_39);

        return $this->createViewModel(
            [
                'code' => $code,
                'type' => $type,
                'html' => $htmlGenerator->getBarcode($code, $type, 3, 60)
            ]
        );
    }
}
