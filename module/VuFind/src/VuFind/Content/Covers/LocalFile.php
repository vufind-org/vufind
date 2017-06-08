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
     * VuFind configuration
     *
     * @var Config
     */
    protected $config;

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
        // Check if key is set
        if (!isset($key)) {
            return false;
        }
        // Get filepath for cover images
        $basePath = $key;

        // Check base path for $VUFIND env variables
        $vufindHome =  getenv('VUFIND_HOME');
        $vufindLocal =  getenv('VUFIND_LOCAL_DIR') 
        $basePath = str_replace("\$VUFIND_HOME", $vufindHome, $basePath);
        $basePath = str_replace("\$VUFIND_LOCAL_DIR", $vufindLocal, $basePath);

        // convert file path tokens to id array values
        foreach ($ids as $key => $val) {
            $tokens[] = '%' . $key . '%';
            $replacements[] = $val;
        }
        $fileName = str_replace($tokens, $replacements, $basePath);

        // convert file extension tokens to values
        $allowed_imageTypes = [
             "anyimage", "gif", "jpg", "jpeg", "png", "tif", "tiff"
        ];
        if (preg_match("/(.*)(\%.*\%)/", $fileName, $matches) !== 1) {
             return false;
        } else {
             $imageType = substr($matches[2], 1, -1);
        }

        // make sure image type is allowed
        if (!in_array($imageType, $allowed_imageTypes)) {
            throw new \Exception(
                "Illegal file-extension '$imageType' for image '$fileName'"
            );
            return false;
        }
        // Replace token with value
        foreach ($allowed_imageTypes as $val) {
            $tokens[] = '%' . $val . '%';
            $replacements[] = $val;
        }
        $fileName = str_replace($tokens, $replacements, $fileName);

        // If anyimage is specified, then we loop through
        // all image extensions to find the right filename
        if ($imageType == "anyimage") {
            $check_imageTypes = array_slice($allowed_imageTypes, 1);
            $checkPath = substr($fileName, 0, -8);
            foreach ($check_imageTypes as $val) {
                $checkFile = $checkPath . $val;
                if (file_exists($checkFile)) {
                     $fileName = $checkFile;
                     $imageType = $val;
                }
            }
        }
        // Check if Mime type and file extension match.
        $allowedFileExtensions = [
             "gif" => "image/gif",
             "jpeg" => "image/jpeg", "jpg" => "image/jpeg",
             "png" => "image/png",
             "tiff" => "image/tiff", "tif" => "image/tiff"
         ];
        $mimeType = mime_content_type($fileName);
        if ($allowedFileExtensions[$imageType] !== $mimeType) {
            return false;
        }
        $filePath="file://" . $fileName;
        return $filePath;
    }
}

