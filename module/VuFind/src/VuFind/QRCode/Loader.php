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
 * @package  Cover_Generator
 * @author   Andrew S. Nagy <vufind-tech@lists.sourceforge.net>
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Luke O'Sullivan <l.osullivan@swansea.ac.uk>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/use_of_external_content Wiki
 */
namespace VuFind\QRCode;
use \PHPQRCode, Zend\Log\LoggerInterface;

/**
 * Book Cover Generator
 *
 * @category VuFind2
 * @package  QRCode_Generator
 * @author   Andrew S. Nagy <vufind-tech@lists.sourceforge.net>
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Luke O'Sullivan <l.osullivan@swansea.ac.uk>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/use_of_external_content Wiki
 */
class Loader implements \Zend\Log\LoggerAwareInterface
{

    /**
     * property to hold VuFind configuration settings
     *
     * @var \Zend\Config\Config
     */
    protected $config;

    /**
     * HTTP client
     *
     * @var \Zend\Http\Client
     */
    protected $client;

    /**
     * Property for storing raw qrcode data; may be null if image is unavailable
     *
     * @var string
     */
    protected $qrcode = null;

    /**
     * Content type of data in $qrcode property
     *
     * @var string
     */
    protected $contentType = null;

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
    protected $params = array(
        'level' => "L", 'size' => "3", 'margin' => "4"
    );

    /**
     * Logger (or false for none)
     *
     * @var LoggerInterface|bool
     */
    protected $logger = false;

    /**
     * Theme tools
     *
     * @var \VuFindTheme\ThemeInfo
     */
    protected $themeTools;

    /**
     * Constructor
     *
     * @param \Zend\Config\Config    $config VuFind configuration
     * @param \VuFindTheme\ThemeInfo $theme  VuFind theme tools
     * @param \Zend\Http\Client      $client HTTP client
     */
    public function __construct($config, \VuFindTheme\ThemeInfo $theme,
        \Zend\Http\Client $client
    ) {
        $this->config = $config;
        $this->themeTools = $theme;
        $this->client = $client;
    }

    /**
     * Set the logger
     *
     * @param LoggerInterface $logger Logger to use.
     *
     * @return void
     */
    public function setLogger(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    /**
     * Log a debug message.
     *
     * @param string $msg Message to log.
     *
     * @return void
     */
    protected function debug($msg)
    {
        if ($this->logger) {
            $this->logger->debug($msg);
        }
    }

    /**
     * Get the QrCode data (usually called after loadQrCode)
     *
     * @return string
     */
    public function getQRCode()
    {
        // No image loaded?  Use "unavailable" as default:
        if (is_null($this->qrcode)) {
            $this->loadUnavailable();
        }
        return $this->image;
    }

    /**
     * Get the content type of the current image (usually called after loadImage)
     *
     * @return string
     */
    public function getContentType()
    {
        // No content type loaded?  Use "unavailable" as default:
        if (is_null($this->contentType)) {
            $this->loadUnavailable();
        }
        return $this->contentType;
    }

    /**
     * Load an image given an ISBN and/or content type.
     *
     * @param string $text   The QR code text
     * @param array  $params QR code parameters (level/size/margin)
     *
     * @return void
     */
    public function loadQRCode($text,
        $params = array('level' => "L", 'size' => "3", 'margin' => "4")
    ) {
        // Sanitize parameters:
        $this->text = $text;
        $this->params = $params;
        if (!$this->fetchQRCode()) {
            $this->loadUnavailable();
        }
    }

    /**
     * Load bookcover fom URL from cache or remote provider and display if possible.
     *
     * @return bool        True if image displayed, false on failure.
     */
    protected function fetchQRCode()
    {
        if (empty($this->text)) {
            return false;
        }
        $this->contentType = 'image/png';
        $this->qrcode = PHPQRCode\QRcode::PNG(
            $this->text, false,
            $this->params['level'], $this->params['size'], $this->params['margin']
        );
        return true;
    }

    /**
     * Find a file in the themes (return false if no file exists).
     *
     * @param string $path    Relative path of file to find.
     * @param array  $formats Optional array of suffixes to add to $path while
     * searching theme (used to check multiple extensions in each theme).
     *
     * @return string|bool
     */
    protected function searchTheme($path, $formats = array(''))
    {
        // Check all supported image formats:
        $filenames = array();
        foreach ($formats as $format) {
            $filenames[] =  $path . $format;
        }
        $fileMatch = $this->themeTools->findContainingTheme($filenames, true);
        return empty($fileMatch) ? false : $fileMatch;
    }

    /**
     * Load the user-specified "cover unavailable" graphic (or default if none
     * specified).
     *
     * @return void
     * @author Thomas Schwaerzler <vufind-tech@lists.sourceforge.net>
     */
    public function loadUnavailable()
    {
        // Get "no qrcode" image from config.ini:
        $noQRCodeImage = isset($this->config->QRCode->noQRCodeAvailableImage )
            ? $this->searchTheme($this->config->QRCode->noQRCodeAvailableImage)
            : null;

        // No setting -- use default, and don't log anything:
        if (empty($noQRCodeImage)) {
            // log?
            return $this->loadDefaultFailImage();
        }

        // If file defined but does not exist, log error and display default:
        if (!file_exists($noQRCodeImage) || !is_readable($noQRCodeImage)) {
            $this->debug("Cannot access file: '$noQRCodeImage'");
            return $this->loadDefaultFailImage();
        }

        // Array containing map of allowed file extensions to mimetypes
        // (to be extended)
        $allowedFileExtensions = array(
            "gif" => "image/gif",
            "jpeg" => "image/jpeg", "jpg" => "image/jpeg",
            "png" => "image/png",
            "tiff" => "image/tiff", "tif" => "image/tiff"
        );

        // Log error and bail out if file lacks a known image extension:
        $parts = explode('.', $noQRCodeImage);
        $fileExtension = strtolower(end($parts));
        if (!array_key_exists($fileExtension, $allowedFileExtensions)) {
            $this->debug(
                "Illegal file-extension '$fileExtension' for image '$noQRCodeImage'"
            );
            return $this->loadDefaultFailImage();
        }

        // Get mime type from file extension:
        $this->contentType = $allowedFileExtensions[$fileExtension];

        // Load the image data:
        $this->image = file_get_contents($noQRCodeImage);
    }

    /**
     * Display the default "qrcode unavailable" graphic and terminate execution.
     *
     * @return void
     */
    protected function loadDefaultFailImage()
    {
        $this->contentType = 'image/gif';
        $this->image = file_get_contents($this->searchTheme('images/noQRCode.gif'));
    }
}
