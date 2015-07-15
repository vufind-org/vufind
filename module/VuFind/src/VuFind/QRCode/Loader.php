<?php
/**
 * QR Code Generator
 *
 * PHP version 5
 *
 * Copyright (C) Villanova University 2007.
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
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 *
 * @category VuFind2
 * @package  QRCode_Generator
 * @author   Andrew S. Nagy <vufind-tech@lists.sourceforge.net>
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Luke O'Sullivan <l.osullivan@swansea.ac.uk>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/use_of_external_content Wiki
 */
namespace VuFind\QRCode;
use PHPQRCode;

/**
 * QR Code Generator
 *
 * @category VuFind2
 * @package  QRCode_Generator
 * @author   Andrew S. Nagy <vufind-tech@lists.sourceforge.net>
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Luke O'Sullivan <l.osullivan@swansea.ac.uk>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/use_of_external_content Wiki
 */
class Loader extends \VuFind\ImageLoader
{
    /**
     * VuFind configuration settings
     *
     * @var \Zend\Config\Config
     */
    protected $config;

    /**
     * The text used to generate the QRCode
     *
     * @var string
     */
    protected $text = null;

    /**
     * The params used to generate the QRCode
     *
     * @var string
     */
    protected $params = [
        'level' => "L", 'size' => "3", 'margin' => "4"
    ];

    /**
     * Constructor
     *
     * @param \Zend\Config\Config    $config VuFind configuration
     * @param \VuFindTheme\ThemeInfo $theme  VuFind theme tools
     */
    public function __construct($config, \VuFindTheme\ThemeInfo $theme)
    {
        $this->config = $config;
        $this->setThemeInfo($theme);
        $this->configuredFailImage
            = isset($this->config->QRCode->noQRCodeAvailableImage)
            ? $this->config->QRCode->noQRCodeAvailableImage : null;
        $this->defaultFailImage = 'images/noQRCode.gif';
    }

    /**
     * Set up a QR code image
     *
     * @param string $text   The QR code text
     * @param array  $params QR code parameters (level/size/margin)
     *
     * @return void
     */
    public function loadQRCode($text,
        $params = ['level' => "L", 'size' => "3", 'margin' => "4"]
    ) {
        // Sanitize parameters:
        $this->text = $text;
        $this->params = $params;
        if (!$this->fetchQRCode()) {
            $this->loadUnavailable();
        }
    }

    /**
     * Generate a QR code image
     *
     * @return bool True if image displayed, false on failure.
     */
    protected function fetchQRCode()
    {
        if (empty($this->text)) {
            return false;
        }
        $this->contentType = 'image/png';
        $this->image = PHPQRCode\QRcode::PNG(
            $this->text, false,
            $this->params['level'], $this->params['size'], $this->params['margin']
        );
        return true;
    }
}
