<?php

/**
 * Header view helper
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2014-2022.
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
 * @package  View_Helpers
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @author   Juha Luoma <juha.luoma@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */

namespace Finna\View\Helper\Root;

use Finna\RecordDriver\RenderContext;
use Laminas\View\Helper\Url;

use function func_get_args;

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
class RecordImage extends \Laminas\View\Helper\AbstractHelper
{
    /**
     * Record view helper
     *
     * @var Record
     */
    protected $record;

    /**
     * Url helper
     *
     * @var Url
     */
    protected $urlHelper;

    /**
     * Constructor.
     *
     * @param Url $urlHelper Url helper.
     */
    public function __construct(Url $urlHelper)
    {
        $this->urlHelper = $urlHelper;
    }

    /**
     * Assign record image URLs to the view and return the view helper.
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
        $index = 0,
        $params = [],
        $canonical = false,
        $includePdf = true
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
        $index = 0,
        $params = [],
        $canonical = false,
        $includePdf = true
    ) {
        $images = $this->record->getAllImages(
            $this->view->layout()->userLang,
            $includePdf
        );
        if (!isset($images[$index])) {
            return false;
        }
        $imageParams = $images[$index]['urls']['large']
            ?? $images[$index]['urls']['medium'];
        $imageParams = array_merge(
            ['source' => $this->record->getDriver()->getSourceIdentifier()],
            $imageParams,
            $params,
        );

        $url = ($this->urlHelper)(
            'cover-show',
            [],
            $canonical ? ['force_canonical' => true] : []
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
        $index = 0,
        $params = [],
        $canonical = false
    ) {
        $images = $this->record->getAllImages($this->view->layout()->userLang);
        if (!isset($images[$index])) {
            return false;
        }
        if (!isset($images[$index]['urls']['master'])) {
            // Fall back to large image
            return $this->getLargeImageWithInfo($index, $params, $canonical);
        }

        $imageParams = array_merge(
            ['source' => $this->record->getDriver()->getSourceIdentifier()],
            $images[$index]['urls']['master'],
            $params
        );

        $url = ($this->urlHelper)(
            'cover-show',
            [],
            $canonical ? ['force_canonical' => true] : []
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
     *
     * @return array
     */
    public function getAllImagesAsCoverLinks(
        $language,
        $params = [],
        $thumbnails = true,
        $includePdf = true
    ) {
        $images = $this->record->getAllImages($language, $thumbnails, $includePdf);
        if (empty($images)) {
            return [];
        }
        $imageParams = $this->getImageParams($params);
        foreach ($images as &$image) {
            foreach (array_keys(array_intersect_key($imageParams, $image['urls'] ?? [])) as $size) {
                $image['urls'][$size] = ($this->urlHelper)('cover-show') . '?' .
                    http_build_query(
                        array_merge(
                            $imageParams[$size],
                            $image['urls'][$size]
                        )
                    );
            }
        }
        unset($image);
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
     *                            - string  $imageClick
     *                            [open, modal, none] Open as a link,
     *                            modal or do nothing
     *                            - string  $imageRightsLabel
     *                            Label for image rights statement
     *                            - array   $numOfImages
     *                            Number of images to show in thumbnail navigation.
     *
     * @deprecated Deprecated, use renderImage.
     *
     * @return string
     */
    public function render(
        $type = 'list',
        $params = null,
        $source = 'Solr',
        $extraParams = []
    ) {
        return $this->renderImage($type, $params, $extraParams);
    }

    /**
     * Return rendered record image HTML.
     *
     * @param string $type        Page type (list, record).
     * @param array  $params      Optional array of image parameters as
     *                            an associative array of parameter =>
     *                            value pairs: - w  Width - h  Height
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
    public function renderImage($type = 'list', $params = null, $extraParams = [])
    {
        $disableModal = isset($extraParams['disableModal'])
            && $extraParams['disableModal'] ? 'none' : 'modal';
        $imageClick = $extraParams['imageClick'] ?? $disableModal;
        $imageRightsLabel = $extraParams['imageRightsLabel'] ?? 'Image Rights';
        $numOfImages = $extraParams['numOfImages'] ?? null;
        $displayIcon = $extraParams['displayIcon'] ?? false;
        $imageToRecord = $extraParams['imageToRecord'] ?? false;

        $view = $this->getView();
        $renderContext = RenderContext::fromView($type);
        $this->record->getDriver()->tryMethod('setRenderContext', [$renderContext->value]);
        $images = $this->getAllImagesAsCoverLinks(
            $view->layout()->userLang,
            $params,
            true,
            true
        );
        // Get plausible model data
        if (
            $renderContext === RenderContext::RECORD
            && $this->record->getDriver()->tryMethod('getModels')
        ) {
            $images = $this->mergeModelDataToImages($images);
        }
        if ($images && $view->layout()->templateDir === 'combined') {
            // Limit combined results to a single image
            $images = [reset($images)];
        }
        $context = [
            'type' => $type,
            'images' => $images,
            'imageClick' => $imageClick,
            'imageToRecord' => $imageToRecord,
            'imageRightsLabel' => $imageRightsLabel,
            'numOfImages' => $numOfImages,
            'displayIcon' => $displayIcon,
        ];

        return $this->record->renderTemplate('record-image.phtml', $context);
    }

    /**
     * Return all models in a presentative format.
     *
     * @return array
     */
    protected function getAllModelsAsRepresentations(): array
    {
        $models = $this->record->getDriver()->tryMethod('getModels', [], []);
        if (!$models) {
            return [];
        }

        $result = [];
        $uniqueID = $this->record->getDriver()->getUniqueID();
        $source = $this->record->getDriver()->getSourceIdentifier();
        $bgImage
            = $this->view->plugin('imageSrc')->getSourceAddress('3d-bg.jpg', true);
        $template = [
            // Mimic representation of an image.
            'urls' => [
                'small' => null,
                'medium' => null,
                'large' => $bgImage,
                'master' => null,
            ],
            // Model only settings
            'type' => 'model',
            'scripts' => '/themes/finna2/js/vendor/',
            'texture' => '/themes/finna2/images/',
            'models' => [],
        ];
        foreach ($models as $index => $object) {
            foreach ($object['models'] as &$model) {
                if ('preview' !== $model['type']) {
                    continue;
                }
                $model['params'] = http_build_query([
                        'method' => 'getModel',
                        'id' => $uniqueID,
                        'index' => $index,
                        'format' => $model['format'],
                        'source' => $source,
                    ]);
            }
            unset($model);
            $result[$index] = array_merge($template, $object);
        }
        return $result;
    }

    /**
     * Function to combine model data with image data.
     *
     * @param array $images Images from getAllImagesAsCoverLinks
     *
     * @return array
     */
    protected function mergeModelDataToImages(array $images): array
    {
        $models = $this->getAllModelsAsRepresentations();
        $modelSettings
            = $this->record->getDriver()->tryMethod('getModelSettings', [], []);
        foreach ($models as $ind => $model) {
            if (!isset($images[$ind])) {
                $images[$ind] = [
                    'rights' => [],
                ];
            }
            if ($modelSettings['previewImages'] ?? false) {
                $images[$ind] = array_merge($model, $images[$ind]);
            } else {
                $images[$ind] = array_merge($images[$ind], $model);
            }
            $images[$ind]['type'] = 'model';
        }
        return $images;
    }

    /**
     * Get image with index as cover links
     *
     * @param int $index Index of the image array to get.
     *
     * @return array
     */
    public function getImageAsCoverLinks(int $index): array
    {
        $image
            = $this->record->getAllImages($this->view->layout()->userLang)[$index]
            ?? [];
        if (empty($image)) {
            return [];
        }
        $imageParams = $this->getImageParams();
        foreach (array_keys(array_intersect_key($imageParams, $image['urls'] ?? [])) as $size) {
            $image['urls'][$size] = ($this->urlHelper)('cover-show') . '?' .
                http_build_query(
                    array_merge(
                        $imageParams[$size],
                        $image['urls'][$size]
                    )
                );
        }
        return $image;
    }

    /**
     * Function to return images for a bot.
     * 4 images maximum and prefers [large, medium, small] sizes.
     *
     * @param array $images Images to render
     *
     * @return array
     */
    public function getImagesForBotUser(array $images = []): array
    {
        $results = [];

        $i = 0;
        $image = [];
        do {
            foreach (['large', 'medium', 'small'] as $size) {
                if (empty($image['urls'][$size])) {
                    continue;
                }
                $results[] = [
                    'url' => $image['urls'][$size],
                    'pdf' => !empty($image['pdf']),
                    'id' => $i++,
                ];
                break;
            }
        } while ($i < 4 && $image = array_shift($images));
        return $results;
    }

    /**
     * Returns image params to be used when creating cover links.
     *
     * @param array $params Extra parameters for image. Width, height.
     *
     * @return array ['source' => n, ...]
     */
    protected function getImageParams(array $params = []): array
    {
        $imageParams = [
            'small' => [],
            'medium' => [],
            'large' => [],
            'master' => [],
        ];
        $source = $this->record->getDriver()->getSourceIdentifier();
        foreach ($imageParams as $size => &$value) {
            if (!empty($params[$size])) {
                $value = array_merge($params[$size], $value);
            }
            $value['source'] = $source;
        }
        return $imageParams;
    }
}
