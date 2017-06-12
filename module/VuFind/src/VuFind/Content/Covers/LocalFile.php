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
     * MIME types allowed to be loaded from disk.
     *
     * @var array
     */
    protected $allowedMimeTypes = [
        "image/gif", "image/jpeg", "image/png", "image/tiff"
    ];

    /**
     * Does this plugin support the provided ID array?
     *
     * @param array $ids IDs that will later be sent to load() -- see below.
     *
     * @return bool
     */
    public function supports($ids)
    {
        // We won't know what we need until we parse the path string; accept
        // everything at this stage:
        return true;
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
        // convert all of the tokens:
        $fileName = $this->replaceImageTypeTokens(
            $this->replaceEnvironmentAndIdTokens($key, $ids)
        );
        // Validate MIME type if we have a valid file path.
        if ($fileName && file_exists($fileName)) {
            if (in_array(mime_content_type($fileName), $this->allowedMimeTypes)) {
                return "file://" . $fileName;
            }
        }
        // If we got this far, we couldn't find a match.
        return false;
    }

    /**
     * Convert tokens to ids array values.
     *
     * @param string $filePath file path of image file
     * @param array  $ids      Associative array of identifiers
     * (keys may include 'isbn' pointing to an ISBN object and
     * 'issn' pointing to a string)
     *
     * @return string
     */
    protected function replaceEnvironmentAndIdTokens($filePath, $ids)
    {
        // Seed the token array with standard environment variables:
        $tokens = ['%vufind-home%', '%vufind-local-dir%'];
        $replacements = [APPLICATION_PATH, LOCAL_OVERRIDE_DIR];

        // Only create tokens for strings, not objects:
        foreach ($ids as $key => $val) {
            if (is_string($val)) {
                $tokens[] = '%' . $key . '%';
                $replacements[] = $val;
            }
        }
        // Special-case handling for ISBN object:
        if (isset($ids['isbn'])) {
            $tokens[] = '%isbn10%';
            $replacements[] = $ids['isbn']->get10();

            $tokens[] = '%isbn13%';
            $replacements[] = $ids['isbn']->get13();
        }
        return str_replace($tokens, $replacements, $filePath);
    }

    /**
     * Convert tokens to image type file extension.
     *
     * @param string $fileName file path of image file
     *
     * @return bool|string
     */
    protected function replaceImageTypeTokens($fileName)
    {
        // convert file extension tokens to values
        $allowed_imageTypes = ["anyimage", "gif",
        "jpg", "jpeg", "png", "tif", "tiff"
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
        $fName = str_replace($tokens, $replacements, $fileName);

        // If anyimage is specified, then we loop through all
        // image extensions to find the right filename
        if ($imageType == "anyimage") {
            $check_imageTypes = array_slice($allowed_imageTypes, 1);
            $checkPath = substr($fName, 0, -8);
            foreach ($check_imageTypes as $val) {
                $checkFile = $checkPath . $val;
                if (file_exists($checkFile)) {
                     $fName = $checkFile;
                }
            }
        }
        return $fName;
    }
}
