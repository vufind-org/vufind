<?php

/**
 * QR Code Generator
 *
 * PHP version 8
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
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
 *
 * @category VuFind
 * @package  QRCode_Generator
 * @author   Andrew S. Nagy <vufind-tech@lists.sourceforge.net>
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Luke O'Sullivan <l.osullivan@swansea.ac.uk>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/configuration:external_content Wiki
 */

namespace VuFind\QRCode;

use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;

use function intval;
use function strlen;

/**
 * QR Code Generator
 *
 * @category VuFind
 * @package  QRCode_Generator
 * @author   Andrew S. Nagy <vufind-tech@lists.sourceforge.net>
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Luke O'Sullivan <l.osullivan@swansea.ac.uk>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/configuration:external_content Wiki
 */
class Loader extends \VuFind\ImageLoader
{
    /**
     * The default params used to generate the QRCode
     *
     * @var string
     */
    protected $defaultParams = ['level' => 'L', 'size' => '3', 'margin' => '4'];

    /**
     * Constructor
     *
     * @param \Laminas\Config\Config $config VuFind configuration
     * @param \VuFindTheme\ThemeInfo $theme  VuFind theme tools
     */
    public function __construct($config, \VuFindTheme\ThemeInfo $theme)
    {
        $this->setThemeInfo($theme);
        $this->configuredFailImage
            = $config->QRCode->noQRCodeAvailableImage ?? null;
        $this->defaultFailImage = 'images/noQRCode.gif';
    }

    /**
     * Get default parameters.
     *
     * @return array
     */
    public function getDefaults()
    {
        return $this->defaultParams;
    }

    /**
     * Set up a QR code image
     *
     * @param string $text      The QR code text
     * @param array  $rawParams QR code parameters (level/size/margin)
     *
     * @return void
     */
    public function loadQRCode($text, $rawParams = [])
    {
        // Fill in defaults:
        $params = $rawParams + $this->defaultParams;

        // Normalize parameters; when the size setting is less than 30 pixels,
        // do some math to try to map old PHPQRCode-style settings to new
        // Endroid\QrCode equivalents. When the size setting is 30 or higher,
        // treat 'size' and 'margin' as literal pixel sizes.
        $size = intval($params['size']);
        $margin = intval($params['margin']);
        $level = $this->mapErrorLevel($params['level']);
        if ($size < 30) {
            // In the old system, the margin was multiplied by the size....
            $margin *= $size;

            // Do some magic math to adjust the QR code size to accommodate the
            // length of the text and the quality level. This is probably not the
            // smartest way to do this, but it seems good enough for VuFind's
            // limited needs.
            $sizeIncrement = ceil(ceil(sqrt(strlen($text))) / 10);
            if ($level == ErrorCorrectionLevel::High) {
                $sizeIncrement *= 38;
            } elseif ($level == ErrorCorrectionLevel::Quartile) {
                $sizeIncrement *= 34;
            } else {
                $sizeIncrement *= 30;
            }

            // Put it all together:
            $size = $size * $sizeIncrement - $params['margin'];
        }

        // Fetch image:
        if (!$this->fetchQRCode($text, $size, $margin, $level)) {
            $this->loadUnavailable();
        }
    }

    /**
     * Map an incoming error correction level parameter to a valid constant.
     *
     * @param string $level Error correction level parameter
     *
     * @return ErrorCorrectionLevel
     */
    protected function mapErrorLevel($level): ErrorCorrectionLevel
    {
        switch (strtoupper(substr($level, 0, 1))) {
            case '3':
            case 'H':
                return ErrorCorrectionLevel::High;
            case '2':
            case 'Q':
                return ErrorCorrectionLevel::Quartile;
            case '1':
            case 'M':
                return ErrorCorrectionLevel::Medium;
            case '0':
            case 'L':
            default:
                return ErrorCorrectionLevel::Low;
        }
    }

    /**
     * Generate a QR code image
     *
     * @param string                        $text   The QR code text
     * @param int                           $size   QR code width/height (in pixels)
     * @param int                           $margin QR code margin (in pixels)
     * @param ErrorCorrectionLevelInterface $level  Error correction level object
     *
     * @return bool True if image displayed, false on failure.
     */
    protected function fetchQRCode($text, $size, $margin, $level)
    {
        if (strlen(trim($text)) == 0) {
            return false;
        }

        // Build the code:
        try {
            $code = new QrCode($text);
            $code->setMargin($margin);
            $code->setErrorCorrectionLevel($level);
            $code->setSize($size);
            $code->setEncoding(new \Endroid\QrCode\Encoding\Encoding('UTF-8'));
            $code->setRoundBlockSizeMode(\Endroid\QrCode\RoundBlockSizeMode::None);

            // Save the values.
            $writer = new PngWriter();
            $result = $writer->write($code);
            $this->contentType = $result->getMimeType();
            $this->image = $result->getString();
        } catch (\Exception $e) {
            return false;
        }
        return true;
    }
}
