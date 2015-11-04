<?php
/**
 * Header view helper
 *
 * PHP version 5
 *
 * Copyright (C) The National Library of Finland 2014.
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
 * @package  View_Helpers
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
namespace Finna\View\Helper\Root;

/**
 * Header view helper
 *
 * @category VuFind2
 * @package  View_Helpers
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
class RecordImage extends \Zend\View\Helper\AbstractHelper
{
    /**
     * Image parameters
     *
     * @var array
     */
    protected $params;

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
        $this->params['small']
            = $this->params['medium']
                = $this->params['large']
                    = [];
        $this->record = $record;

        return $this;
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
        $cnt = $this->record->getNumOfRecordImages('large');
        $urlHelper = $this->getView()->plugin('url');
        $imageParams = $this->record->getRecordImage('large');
        unset($imageParams['url']);

        $imageParams['index'] = $cnt > 0 ? $index : 0;
        $imageParams = array_merge($imageParams, $this->params['large']);
        $imageParams = array_merge($imageParams, $params);

        return $urlHelper(
            'cover-show', [], $canonical ? ['force_canonical' => true] : []
        ) . '?' . http_build_query($imageParams);
    }

    /**
     * Return rendered record image HTML.
     *
     * @param string $type   Page type (list, record).
     * @param array  $params Optional array of image parameters as
     *                       an associative array of parameter => value pairs:
     *                         'w'    Width
     *                         'h'    Height
     *
     * @return string
     */
    public function render($type = 'list', $params = null)
    {
        if ($params) {
            foreach ($params as $size => $sizeParams) {
                $this->params[$size]
                    = array_merge($this->params[$size], $sizeParams);
            }
        }

        $view = $this->getView();
        $view->type = $type;

        $view = $this->getView();
        $urlHelper = $this->getView()->plugin('url');
        $numOfImages = $this->record->getNumOfRecordImages('large');
        if ($view->layout()->templateDir === 'combined') {
            $numOfImages = min(1, $numOfImages);
        }

        $params = $this->record->getRecordImage('small');
        if (is_array($params)) {
            unset($params['url']);
            unset($params['size']);
            $view->smallImage = $urlHelper('cover-show') . '?' .
                http_build_query(array_merge($params, $this->params['small']));
        } else {
            $view->smallImage = $params;
        }

        $params = $this->record->getRecordImage('large');
        if (is_array($params)) {
            unset($params['url']);
            unset($params['size']);

            $view->mediumImage = $urlHelper('cover-show') . '?' .
                http_build_query(array_merge($params, $this->params['medium']));

            $view->largeImage = $urlHelper('cover-show') . '?' .
                http_build_query(array_merge($params, $this->params['large']));
        } else {
            $view->mediumImage = $view->largeImage = $params;
        }

        $images = [];
        if ($numOfImages > 1) {
            for ($i = 0; $i < $numOfImages; $i++) {
                $params['index'] = $i;
                $images[] = [
                    'small' => $urlHelper('cover-show') . '?' .
                        http_build_query(
                            array_merge($params, $this->params['small'])
                        ),

                    'medium' => $urlHelper('cover-show') . '?' .
                        http_build_query(
                            array_merge($params, $this->params['medium'])
                        ),

                    'large' => $urlHelper('cover-show') . '?' .
                        http_build_query(
                            array_merge($params, $this->params['large'])
                        )
                ];
            }
        }
        $view->allImages = $images;

        return $view->render('RecordDriver/SolrDefault/record-image.phtml');
    }
}
