<?php
/**
 * Header view helper
 *
 * PHP version 5
 *
 * Copyright (C) The National Library of Finland 2014-2017.
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
 * @category VuFind
 * @package  View_Helpers
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
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
     * @param int   $index     Record image index.
     * @param array $params    Optional array of image parameters.
     *                         See RecordImage::render.
     * @param bool  $canonical Whether to return a canonical URL instead of relative
     *
     * @return mixed string URL or false if no
     * image with the given index was found.
     */
    public function getLargeImage($index = 0, $params = [], $canonical = false)
    {
        $images = $this->record->getAllImages('');
        if (!isset($images[$index])) {
            return false;
        }
        $urlHelper = $this->getView()->plugin('url');
        $imageParams = isset($images[$index]['urls']['large'])
            ? $images[$index]['urls']['large'] : $images[$index]['urls']['medium'];
        $imageParams = array_merge($imageParams, $params);

        return $urlHelper(
            'cover-show', [], $canonical ? ['force_canonical' => true] : []
        ) . '?' . http_build_query($imageParams);
    }

    /**
     * Get all images as Cover links
     *
     * @param string $language   Language for copyright information
     * @param array  $params     Optional array of image parameters as an
     *                           associative array of parameter => value pairs:
     *                             - w  Width
     *                             - h  Height
     * @param bool   $thumbnails Whether to include thumbnail links if no image links
     * are found
     *
     * @return array
     */
    public function getAllImagesAsCoverLinks($language, $params = [],
        $thumbnails = true
    ) {
        $imageParams = [
            'small' => [],
            'medium' => [],
            'large' => []
        ];
        foreach ($params as $size => $sizeParams) {
            $imageParams[$size] = $sizeParams;
        }

        $urlHelper = $this->getView()->plugin('url');

        $imageTypes = ['small', 'medium', 'large'];

        $images = $this->record->getAllImages($language, $thumbnails);
        foreach ($images as $idx => &$image) {
            foreach ($imageTypes as $imageType) {
                if (!isset($image['urls'][$imageType])) {
                    continue;
                }
                $params = $image['urls'][$imageType];
                $image['urls'][$imageType] = $urlHelper('cover-show') . '?' .
                    http_build_query(
                        array_merge($params, $imageParams[$imageType])
                    );
            }
        }
        return $images;
    }

    /**
     * Return rendered record image HTML.
     *
     * @param string $type   Page type (list, record).
     * @param array  $params Optional array of image parameters as
     *                       an associative array of parameter => value pairs:
     *                         - w  Width
     *                         - h  Height
     *
     * @return string
     */
    public function render($type = 'list', $params = null)
    {
        $view = $this->getView();
        $images = $this->getAllImagesAsCoverLinks(
            $view->layout()->userLang, $params
        );
        if ($images && $view->layout()->templateDir === 'combined') {
            // Limit combined results to a single image
            $images = [$images[0]];
        }

        $view->type = $type;
        $view->images = $images;

        return $view->render('RecordDriver/SolrDefault/record-image.phtml');
    }
}
