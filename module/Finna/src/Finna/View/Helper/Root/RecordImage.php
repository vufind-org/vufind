<?php
/**
 * Header view helper
 *
 * PHP version 7
 *
 * Copyright (C) The National Library of Finland 2014-2020.
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
 * @package  View_Helpers
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @author   Juha Luoma <juha.luoma@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
namespace Finna\View\Helper\Root;

/**
 * Header view helper
 *
 * @category VuFind
 * @package  View_Helpers
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @author   Juha Luoma <juha.luoma@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
class RecordImage extends \Zend\View\Helper\AbstractHelper
{
    /**
     * Record view helper
     *
     * @var Zend\View\Helper\Record
     */
    protected $record;

    /**
     * Assign record image URLs to the view and return header view helper.
     *
     * @param \Finna\View\Helper\Root\Record $record Record helper.
     *
     * @return Finna\View\Helper\Root\Header
     */
    public function __invoke(\Finna\View\Helper\Root\Record $record)
    {
        $this->record = $record;
        return $this;
    }

    /**
     * Return image rights.
     *
     * @param int $index Record image index.
     *
     * @return array
     */
    public function getImageRights($index = 0)
    {
        $language = $this->getView()->layout()->userLang;
        $images = $this->record->getAllImages($language);
        return isset($images[$index]) ? $images[$index]['rights'] : [];
    }

    /**
     * Return URL to large record image.
     *
     * @param int   $index      Record image index.
     * @param array $params     Optional array of image parameters.
     *                          See RecordImage::render.
     * @param bool  $canonical  Whether to return a canonical URL instead of relative
     * @param bool  $includePdf Whether to include first PDF file when no image
     *                          links are found
     *
     * @return mixed string URL or false if no
     * image with the given index was found.
     */
    public function getLargeImage(
        $index = 0, $params = [], $canonical = false, $includePdf = true
    ) {
        $image = $this->getLargeImageWithInfo(...func_get_args());
        return $image['url'] ?? false;
    }

    /**
     * Return URL to large record image with additional information.
     *
     * Returns an array with keys:
     * - 'url' string Image URL
     * - 'pdf' bool   Whether the image URL is a PDF file
     *
     * @param int   $index      Record image index.
     * @param array $params     Optional array of image parameters.
     *                          See RecordImage::render.
     * @param bool  $canonical  Whether to return a canonical URL instead of relative
     * @param bool  $includePdf Whether to include first PDF file when no image
     *                          links are found
     *
     * @return mixed array with image data or false if no
     * image with the given index was found.
     */
    public function getLargeImageWithInfo(
        $index = 0, $params = [], $canonical = false, $includePdf = true
    ) {
        $images = $this->record->getAllImages(
            $this->view->layout()->userLang, $includePdf
        );
        if (!isset($images[$index])) {
            return false;
        }
        $urlHelper = $this->getView()->plugin('url');
        $imageParams = $images[$index]['urls']['large']
            ?? $images[$index]['urls']['medium'];
        $imageParams = array_merge($imageParams, $params);

        $url = $urlHelper(
            'cover-show', [], $canonical ? ['force_canonical' => true] : []
        ) . '?' . http_build_query($imageParams);
        $pdf = $images[$index]['pdf'] ?? false;

        return compact('url', 'pdf');
    }

    /**
     * Return URL to master record image.
     *
     * @param int   $index     Record image index.
     * @param array $params    Optional array of image parameters.
     *                         See RecordImage::render.
     * @param bool  $canonical Whether to return a canonical URL instead of relative
     *
     * @return mixed string URL or false if no
     * image with the given index was found.
     */
    public function getMasterImage($index = 0, $params = [], $canonical = false)
    {
        $image = $this->getMasterImageWithInfo($index, $params, $canonical);
        return $image['url'] ?? false;
    }

    /**
     * Return URL to master record image with additional information.
     *
     * Returns an array with keys:
     * - 'url' string Image URL
     * - 'pdf' bool   Whether the image URL is a PDF file
     *
     * @param int   $index     Record image index.
     * @param array $params    Optional array of image parameters.
     *                         See RecordImage::render.
     * @param bool  $canonical Whether to return a canonical URL instead of relative
     *
     * @return mixed array with image data or false if no
     * image with the given index was found.
     */
    public function getMasterImageWithInfo(
        $index = 0, $params = [], $canonical = false
    ) {
        $images = $this->record->getAllImages($this->view->layout()->userLang);
        if (!isset($images[$index])) {
            return false;
        }
        if (!isset($images[$index]['urls']['master'])) {
            // Fall back to large image
            return $this->getLargeImageWithInfo($index, $params, $canonical);
        }
        $urlHelper = $this->getView()->plugin('url');

        $imageParams = $images[$index]['urls']['master'];
        $imageParams = array_merge($imageParams, $params);

        $url = $urlHelper(
            'cover-show', [], $canonical ? ['force_canonical' => true] : []
        ) . '?' . http_build_query($imageParams);
        $pdf = $images[$index]['pdf'] ?? false;
        return compact('url', 'pdf');
    }

    /**
     * Returns an array containing all the high resolution images for record image
     *
     * @param int $index Record image index
     *
     * @return array|false
     */
    public function getHighResolutionImages($index)
    {
        $images = $this->record->getAllImages($this->view->layout()->userLang);
        return $images[$index]['highResolution'] ?? false;
    }

    /**
     * Get all images as Cover links
     *
     * @param string $language   Language for copyright information
     * @param array  $params     Optional array of image parameters as an
     *                           associative array of parameter => value pairs:
     *                           - w  Width
     *                           - h  Height
     * @param bool   $thumbnails Whether to include thumbnail links if no image links
     *                           are found
     * @param bool   $includePdf Whether to include first PDF file when no image
     *                           links are found
     * @param string $source     Record source
     *
     * @return array
     */
    public function getAllImagesAsCoverLinks($language, $params = [],
        $thumbnails = true, $includePdf = true, $source = DEFAULT_SEARCH_BACKEND
    ) {
        $imageParams = [
            'small' => [],
            'medium' => [],
            'large' => [],
            'master' => [],
        ];
        foreach ($params as $size => $sizeParams) {
            $imageParams[$size] = $sizeParams;
        }

        $urlHelper = $this->getView()->plugin('url');

        $imageTypes = ['small', 'medium', 'large', 'master'];

        $images = $this->record->getAllImages($language, $thumbnails, $includePdf);
        foreach ($images as $idx => &$image) {
            foreach ($imageTypes as $imageType) {
                if (!isset($image['urls'][$imageType])) {
                    continue;
                }
                $params = $image['urls'][$imageType];
                $image['urls'][$imageType] = $urlHelper('cover-show') . '?' .
                    http_build_query(
                        array_merge(
                            $params,
                            $imageParams[$imageType],
                            ['source' => $source]
                        )
                    );
            }
        }
        return $images;
    }

    /**
     * Return rendered record image HTML.
     *
     * @param string $type        Page type (list, record).
     * @param array  $params      Optional array of image parameters as
     *                            an associative array of parameter =>
     *                            value pairs: - w  Width - h  Height
     * @param string $source      Record source
     * @param array  $extraParams Optional extra parameters:
     *                            - boolean $disableModal
     *                            Whether to disable FinnaPopup modal
     *                            - string  $imageRightsLabel
     *                            Label for image rights statement
     *                            - array   $numOfImages
     *                            Number of images to show in thumbnail navigation.
     *
     * @return string
     */
    public function render(
        $type = 'list', $params = null, $source = 'Solr', $extraParams = []
    ) {
        $disableModal = $extraParams['disableModal'] ?? false;
        $imageRightsLabel = $extraParams['imageRightsLabel'] ?? 'Image Rights';
        $numOfImages = $extraParams['numOfImages'] ?? null;

        $view = $this->getView();
        $images = $this->getAllImagesAsCoverLinks(
            $view->layout()->userLang, $params, true, true, $source
        );
        if ($images && $view->layout()->templateDir === 'combined') {
            // Limit combined results to a single image
            $images = [$images[0]];
        }

        $context = [
            'type' => $type,
            'images' => $images,
            'disableModal' => $disableModal,
            'imageRightsLabel' => $imageRightsLabel,
            'numOfImages' => $numOfImages
        ];

        return $this->record->renderTemplate('record-image.phtml', $context);
    }
}
