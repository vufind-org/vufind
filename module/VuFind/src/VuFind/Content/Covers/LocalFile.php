<?php
/**
 * LocalFile cover content loader.
 *
 * PHP version 5
 *
 * Copyright (C) Villanova University 2010.
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
 * @package  Content
 * @author   Leila Gonzales <lmg@agiweb.org>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
namespace VuFind\Content\Covers;

/**
 * Local file cover content loader.
 *
 * @category VuFind
 * @package  Content
 * @author   Leila Gonzales <lmg@agiweb.org>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class LocalFile extends \VuFind\Content\AbstractCover
{

    /**
     * Base filepath for LocalFile
     *
     * @var string
     */
    protected $basePath;

    /**
     * Filename for LocalFile
     *
     * @var string
     */
    protected $fileName;

    /**
     * Image type for LocalFile
     *
     * @var string
     */
    protected $imageType;

    /**
     * Does this plugin support the provided ID array?
     *
     * @param array $ids IDs that will later be sent to load() -- see below.
     *
     * @return bool
     */
    public function supports($ids)
    {
        return true;
    }

    /**
     * VuFind configuration
     *
     * @var Config
     */
    protected $config;

    /**
     * Constructor
     *
     * @param Config $config VuFind configuration
     */
    public function __construct($config)
    {
        $this->config = $config;
    }

    /**
     * Get image location from local file storage.
     *
     * @param string $key  local file directory path
     * @param string $size Size of image to load (small/medium/large)
     * @param array  $ids  Associative array of identifiers (keys may include 'isbn'
     * pointing to an ISBN object and 'issn' pointing to a string)
     *
     * @return string|bool
     */
    public function getUrl($key, $size, $ids)
    {
        //Get settings from config.ini file
        $config = $this->config;
        $basePath = isset($config->$key->filepath)
            ? $config->$key->filepath : null;
        $fileNameToken = isset($config->$key->filename)
            ? $config->$key->filename : null;
        $imageType = isset($config->LocalFile->imagetype)
            ? $config->$key->imagetype : null;

        if (!isset($basePath) || !isset($fileNameToken) || !isset($imageType)) {
            return false;
        }

        // Check base path for $VUFIND env variables
        $vufindHome =  getenv('VUFIND_HOME');
        $vufindLocal =  getenv('VUFIND_LOCAL_DIR') 
        $basePath = str_replace("\$VUFIND_HOME", $vufindHome, $basePath);
        $basePath = str_replace("\$VUFIND_LOCAL_DIR", $vufindLocal, $basePath);

        // Add slash to basePath if it doesn’t exist
        if (substr($basePath, -1) != "/") {
            $basePath = $basePath."/";
        }

        // match fileName to information in $ids array
        if (isset($ids[$fileNameToken])) {
            $fileName = $ids[$fileNameToken];
        } else {
            return false;
        }
        // strip punctuation from imageType
        $imageType = strtolower(preg_replace("/(\.)(.*)/", "$0 --> $2", $imageType));

        // validate that imageType is allowed
        $allowed_imageTypes = [ "gif", "jpg", "jpeg", "png", "tif", "tiff" ];
        if (!in_array($imageType, $allowed_imageTypes)) {
            throw new \Exception(
                "Illegal file-extension '$imageType' for image '$fileName'"
            );
            return false;
        }

        $filepath="file:" . $basePath . $fileName . "." . $imageType;
        return $filepath;
    }
}

