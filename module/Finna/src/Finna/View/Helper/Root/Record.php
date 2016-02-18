<?php
/**
 * Record driver view helper
 *
 * PHP version 5
 *
 * Copyright (C) Villanova University 2010.
 * Copyright (C) The National Library of Finland 2015.
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
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:developer_manual Wiki
 */
namespace Finna\View\Helper\Root;

/**
 * Record driver view helper
 *
 * @category VuFind2
 * @package  View_Helpers
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:developer_manual Wiki
 */
class Record extends \VuFind\View\Helper\Root\Record
{
    /**
     * Record loader
     *
     * @var \VuFind\RecordLoader
     */
    protected $loader;

    /**
     * Constructor
     *
     * @param \VuFind\RecordLoader $loader Record loader
     * @param \Zend\Config\Config  $config VuFind configuration
     */
    public function __construct($loader, $config = null)
    {
        parent::__construct($config);
        $this->loader = $loader;
    }

    /**
     * Store a record driver object and return this object so that the appropriate
     * template can be rendered.
     *
     * @param \VuFind\RecordDriver\AbstractBase|string $driver Record
     * driver object or record id.
     *
     * @return Record
     */
    public function __invoke($driver)
    {
        if (is_string($driver)) {
            $driver = $this->loader->load($driver);
        }
        return parent::__invoke($driver);
    }

    /**
     * Is commenting allowed.
     *
     * @param object $user Current user
     *
     * @return boolean
     */
    public function commentingAllowed($user)
    {
        if (!$this->ratingAllowed()) {
            return true;
        }
        $comments = $this->driver->getComments();
        foreach ($comments as $comment) {
            if ($comment->user_id === $user->id) {
                return false;
            }
        }
        return true;
    }

    /**
     * Is commenting enabled.
     *
     * @return boolean
     */
    public function commentingEnabled()
    {
        return !isset($this->config->Social->comments)
            || ($this->config->Social->comments
                && $this->config->Social->comments !== 'disabled');
    }

    /**
     * Return record driver
     *
     * @return \VuFind\RecordDriver\AbstractBase
     */
    public function getDriver()
    {
        return $this->driver;
    }

    /**
     * Render the link of the specified type.
     *
     * @param string $type    Link type
     * @param string $lookfor String to search for at link
     * @param array  $params  Optional array of parameters for the link template
     *
     * @return string
     */
    public function getLink($type, $lookfor, $params = [])
    {
        $searchAction = isset($this->getView()->browse) && $this->getView()->browse
            ? 'browse-' . $this->getView()->browse
            : 'search-results'
        ;
        $params = isset($params) ? $params : [];
        $params = array_merge(
            $params,
            ['lookfor' => $lookfor,
             'searchAction' => $searchAction]
        );
        return $this->renderTemplate(
            'link-' . $type . '.phtml', $params
        );
    }

    /**
     * Render an HTML checkbox control for the current record.
     *
     * @param string $idPrefix Prefix for checkbox HTML ids
     * @param bool   $label    Whether to enclose the actual checkbox in a label
     *
     * @return string
     */
    public function getCheckbox($idPrefix = '', $label = false)
    {
        static $checkboxCount = 0;
        $id = $this->driver->getSourceIdentifier() . '|'
            . $this->driver->getUniqueId();
        $context = [
            'id' => $id,
            'count' => $checkboxCount++,
            'prefix' => $idPrefix,
            'label' => $label
        ];
        return $this->contextHelper->renderInContext(
            'record/checkbox.phtml', $context
        );
    }

    /**
     * Return all record images (thumbnail and large).
     *
     * @return array
     */
    public function getAllRecordImages()
    {
        $large = $this->driver->tryMethod('getAllThumbnails', ['large']);
        $large = !empty($large) ? array_keys($large) : [];

        $thumb = $this->driver->tryMethod('getAllThumbnails', ['thumb']);
        $thumb = !empty($thumb) ? array_keys($thumb) : [];

        return array_merge($thumb, $large);
    }

    /**
     * Return record image URL.
     *
     * @param string $size Size of requested image
     *
     * @return mixed
     */
    public function getRecordImage($size)
    {
        $params = $this->driver->tryMethod('getRecordImage', [$size]);
        if (empty($params)) {
            return $this->getThumbnail($size);
        }
        return $params;

    }

    /**
     * Return number of record images.
     *
     * @param string $size Size of requested image
     *
     * @return int
     */
    public function getNumOfRecordImages($size)
    {
        $images = $this->driver->trymethod('getAllThumbnails', [$size]);
        return count($images);
    }

    /**
     * Render online URLs
     *
     * @param string $context Record context ('results', 'record' or 'holdings')
     *
     * @return string
     */
    public function getOnlineUrls($context)
    {
        return $this->renderTemplate(
            'result-online-urls.phtml',
            [
                'driver' => $this->driver,
                'context' => $context
            ]
        );
    }

    /**
     * Render meta tags for use on the record view.
     *
     * @return string
     */
    public function getMetaTags()
    {
        return $this->renderTemplate('meta-tags.phtml');
    }

    /**
     * Render average rating
     *
     * @return string
     */
    public function getRating()
    {
        if ($this->ratingAllowed()
            && $average = $this->driver->trymethod('getAverageRating')
        ) {
            return $this->getView()->render(
                'Helpers/record-rating.phtml',
                ['average' => $average['average'], 'count' => $average['count']]
            );
        }
        return false;
    }

    /**
     * Check if the given array of URLs contain URLs that
     * are not record images.
     *
     * @param array $urls      Array of URLs in the format returned by
     *                         getURLs and getOnlineURLs.
     * @param array $imageURLs Array of record image URLs.
     *
     * @return boolean
     */
    public function containsNonImageURL($urls, $imageURLs)
    {
        foreach ($urls as $url) {
            if (!in_array($url['url'], $imageURLs)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Is rating allowed.
     *
     * @return boolean
     */
    public function ratingAllowed()
    {
        return $this->commentingEnabled()
            && $this->driver->tryMethod('ratingAllowed');
    }
}
