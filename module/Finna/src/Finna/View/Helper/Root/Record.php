<?php
/**
 * Record driver view helper
 *
 * PHP version 7
 *
 * Copyright (C) Villanova University 2010.
 * Copyright (C) The National Library of Finland 2015-2020.
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
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @author   Juha Luoma <juha.luoma@helsinki.fi>
 * @author   Aleksi Peebles <aleksi.peebles@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:developer_manual Wiki
 */
namespace Finna\View\Helper\Root;

use Finna\Search\Solr\AuthorityHelper;

/**
 * Record driver view helper
 *
 * @category VuFind
 * @package  View_Helpers
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @author   Juha Luoma <juha.luoma@helsinki.fi>
 * @author   Aleksi Peebles <aleksi.peebles@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:developer_manual Wiki
 */
class Record extends \VuFind\View\Helper\Root\Record
{
    /**
     * Record loader
     *
     * @var \VuFind\Record\Loader
     */
    protected $loader;

    /**
     * Rendered URLs
     *
     * @var array
     */
    protected $renderedUrls = [];

    /**
     * Record image helper
     *
     * @var \Finna\View\Helper\Root\RecordImage
     */
    protected $recordImageHelper;

    /**
     * Authority helper
     *
     * @var \Finna\Search\Solr\AuthorityHelper
     */
    protected $authorityHelper;

    /**
     * Url helper
     *
     * @var \VuFind\View\Helper\Root\Url
     */
    protected $urlHelper;

    /**
     * Record link helper
     *
     * @var \VuFind\View\Helper\Root\RecordLink
     */
    protected $recordLinkHelper;

    /**
     * Image cache
     *
     * @var array
     */
    protected $cachedImages = [];

    /**
     * Cached id of old record
     *
     * @var string
     */
    protected $cachedId = null;

    /**
     * Tab Manager
     *
     * @var \VuFind\RecordTab\TabManager
     */
    protected $tabManager;

    /**
     * Constructor
     *
     * @param \Laminas\Config\Config              $config           VuFind config
     * @param \VuFind\Record\Loader               $loader           Record loader
     * @param \Finna\View\Helper\Root\RecordImage $recordImage      Record image
     * helper
     * @param \Finna\Search\Solr\AuthorityHelper  $authorityHelper  Authority helper
     * @param \VuFind\View\Helper\Root\Url        $urlHelper        Url helper
     * @param \VuFind\View\Helper\Root\RecordLink $recordLinkHelper Record link
     * helper
     * @param \VuFind\RecordTab\TabManager        $tabManager       Tab manager
     */
    public function __construct(
        \Laminas\Config\Config $config,
        \VuFind\Record\Loader $loader,
        \Finna\View\Helper\Root\RecordImage $recordImage,
        \Finna\Search\Solr\AuthorityHelper $authorityHelper,
        \VuFind\View\Helper\Root\Url $urlHelper,
        \VuFind\View\Helper\Root\RecordLink $recordLinkHelper,
        \VuFind\RecordTab\TabManager $tabManager
    ) {
        parent::__construct($config);
        $this->loader = $loader;
        $this->recordImageHelper = $recordImage;
        $this->authorityHelper = $authorityHelper;
        $this->urlHelper = $urlHelper;
        $this->recordLinkHelper = $recordLinkHelper;
        $this->tabManager = $tabManager;
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
     * Deprecated method. Return false for legacy template code.
     *
     * @return boolean
     */
    public function bxRecommendationsEnabled()
    {
        return false;
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
     * Render the record as text for email
     *
     * @return string
     */
    public function getEmail()
    {
        return $this->renderTemplate('result-email.phtml');
    }

    /**
     * Get record format in the requested export format.  For legal values, see
     * the export helper's getFormatsForRecord() method.
     *
     * @param string $format Export format to display
     *
     * @return string        Exported data
     */
    public function getExportFormat($format)
    {
        $format = strtolower($format);
        return $this->renderTemplate('export-' . $format . '-format.phtml');
    }

    /**
     * Render the link of the specified type.
     * Fallbacks from 'authority-page' to 'author' when needed.
     *
     * @param string $type              Link type
     * @param string $lookfor           String to search for at link
     * @param array  $params            Optional array of parameters for the
     * link template
     * @param bool   $withInfo          return an array with link HTML and
     * returned linktype.
     * @param bool   $searchTabsFilters Include search tabs hiddenFilters in
     * the URL (needed when the link performs a search, but not when linking
     * to authority page).
     *
     * @return string
     */
    public function getLink(
        $type, $lookfor, $params = [], $withInfo = false,
        $searchTabsFilters = true
    ) {
        if (is_array($lookfor)) {
            $lookfor = $lookfor['name'];
        }
        $searchAction = !empty($this->getView()->browse)
            ? 'browse-' . $this->getView()->browse : $params['searchAction'] ?? '';
        $params = $params ?? [];
        $filter = null;

        $linkType = $params['linkType'] ?? $this->getAuthorityLinkType($type);
        // Attempt to switch Author search link to Authority link.
        if (null !== $linkType
            && in_array($type, ['author', 'author-id', 'subject'])
            && isset($params['id'])
            && $authId = $this->driver->getAuthorityId(
                $params['id'], $type
            )
        ) {
            $type = "authority-$linkType";
            $filter = $linkType === 'search'
                ? $params['filter']
                    ?? sprintf('%s:"%s"', AuthorityHelper::AUTHOR2_ID_FACET, $authId)
                : $authId;
        }

        $params = array_merge(
            $params,
            [
                'driver' => $this->driver,
                'lookfor' => $lookfor,
                'searchAction' => $searchAction,
                'filter' => $filter
            ]
        );
        $result = $this->renderTemplate(
            'link-' . $type . '.phtml', $params
        );

        if ($searchTabsFilters) {
            $result .= $this->getView()->plugin('searchTabs')
                ->getCurrentHiddenFilterParams($this->driver->getSourceIdentifier());
        }

        return $withInfo ? [$result, $type] : $result;
    }

    /**
     * Render additional data for an authority link.
     *
     * @param array  $additionalData Additional data to render
     * @param string $format         Format (optional)
     *
     * @return string
     */
    public function getAuthorityLinkAdditionalData($additionalData, $format = null)
    {
        if (empty($additionalData)) {
            return '';
        }
        $escaper = $this->getView()->plugin('escapeHtml');
        foreach ($additionalData as $key => &$item) {
            $item = $escaper($item);
            if (!is_numeric($key)) {
                $item = '<span class="author-'
                    . preg_replace('/[^A-Za-z0-9_-]/', '', $key)
                    . '">' . $item . '</span>';
            }
        }
        if ($format) {
            return vsprintf($format, $additionalData);
        } else {
            return ', ' . implode(', ', $additionalData);
        }
    }

    /**
     * Render a authority search link or fallback to Author search.
     *
     * @param string $type    Link type
     * @param string $lookfor Link label or string to search for at link
     *                        when authority functionality id disabled.
     * @param array  $data    Additional link data
     * @param array  $params  Optional array of parameters for the link template
     *
     * @return string HTML
     */
    public function getAuthorityLinkElement(
        $type, $lookfor, $data, $params = []
    ) {
        $id = $data['id'] ?? null;

        // Discard search tabs hiddenFilters when jumping to Authority page
        $preserveSearchTabsFilters
            = $this->getAuthorityLinkType() !== AuthorityHelper::LINK_TYPE_PAGE;

        list($url, $urlType)
            = $this->getLink(
                $type, $lookfor, $params + ['id' => $id], true,
                $preserveSearchTabsFilters
            );

        if (!$this->isAuthorityEnabled()
            || !in_array($urlType, ['authority-search', 'authority-page'])
        ) {
            $author = [
               'name' => $data['name'] ?? null,
               'date' => !empty($data['date']) ? $data['date'] : null,
               'role' => !empty($data['role']) ? $data['role'] : null
            ];
            // NOTE: currently this fallbacks always to a author-link
            // (extend to handle subject/topic fallbacks when needed).
            return $this->getAuthorLinkElement($url, $author);
        }

        $authId = $this->driver->getAuthorityId($id, $type);
        $authorityType = $params['authorityType'] ?? null;
        $authorityType
            = $this->config->Authority->typeMap->{$authorityType} ?? $authorityType;

        $elementParams = [
           'url' => trim($url),
           'record' => $this->driver,
           'searchAction' => $params['searchAction'] ?? null,
           'label' => $lookfor,
           'id' => $authId,
           'authorityLink' => $id && $this->isAuthorityLinksEnabled(),
           'showInlineInfo' => !empty($params['showInlineInfo'])
               && $this->isAuthorityInlineInfoEnabled(),
           'recordSource' => $this->driver->getDataSource(),
           'type' => $type,
           'authorityType' => $authorityType,
           'title' => $params['title'] ?? null,
           'classes' => $params['class'] ?? []
        ];

        if (isset($params['additionalData'])) {
            $elementParams['additionalData'] = $params['additionalData'];
        } else {
            // Special handling for backwards compatibility.
            $additionalData = [];
            if (isset($params['role'])) {
                if (!empty($data['roleName'])) {
                    $additionalData['role'] = $data['roleName'];
                } elseif (!empty($data['role'])) {
                    $translator = $this->getView()->plugin('translate');
                    $additionalData['role']
                        = $translator('CreatorRoles::' . $data['role']);
                }
            }
            if (isset($params['date']) && !empty($data['date'])) {
                $additionalData['date'] = $data['date'];
            }
            if (!empty($additionalData)) {
                $elementParams['additionalData']
                    = $this->getAuthorityLinkAdditionalData($additionalData);
            }
        }

        return $this->renderTemplate('authority-link-element.phtml', $elementParams);
    }

    /**
     * Is authority links enabled?
     * Utility function for rendering an author search link element.
     *
     * @return bool
     */
    protected function isAuthorityLinksEnabled()
    {
        return $this->isAuthorityEnabled()
            && ($this->config->Authority->authority_links ?? false);
    }

    /**
     * Utility function for rendering an author search link element.
     *
     * @param string $url  Link URL
     * @param array  $data Author data (name, role, date)
     *
     * @return string HTML
     */
    protected function getAuthorLinkElement($url, $data)
    {
        $params = [
           'url' => $url,
           'record' => $this->driver,
           'author' => $data
        ];

        return $this->renderTemplate('author-link-element.phtml', $params);
    }

    /**
     * Is authority functionality enabled?
     *
     * @return bool
     */
    protected function isAuthorityEnabled()
    {
        return
            $this->config->Authority
            && (bool)$this->config->Authority->enabled ?? false;
    }

    /**
     * Get authority link type.
     *
     * @param string $type authority type
     *
     * @return Link type (string) or null when authority links are disabled.
     */
    protected function getAuthorityLinkType($type = 'author')
    {
        if (!$this->driver->tryMethod('isAuthorityEnabled')) {
            return null;
        }
        return $this->authorityHelper->getAuthorityLinkType($type);
    }

    /**
     * Is authority inline info enabled?
     *
     * @return bool
     */
    protected function isAuthorityInlineInfoEnabled()
    {
        return $this->driver->tryMethod('isAuthorityEnabled')
            && ($this->config->Authority->authority_info ?? false);
    }

    /**
     * Render an HTML checkbox control for the current record.
     *
     * @param string $idPrefix Prefix for checkbox HTML ids
     * @param string $formAttr ID of form for [form] attribute
     * @param bool   $label    Whether to enclose the actual checkbox in a label
     *
     * @return string
     */
    public function getCheckbox($idPrefix = '', $formAttr = false, $label = false)
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
        if ($formAttr) {
            $context['formAttr'] = $formAttr;
        }
        return $this->contextHelper->renderInContext(
            'record/checkbox.phtml', $context
        );
    }

    /**
     * Return all record image urls as array keys.
     *
     * @return array
     */
    public function getAllRecordImageUrls()
    {
        $images = $this->driver->tryMethod('getAllImages', ['']);
        if (empty($images)) {
            return [];
        }
        $urls = [];
        foreach ($images as $image) {
            $urls[] = $image['urls']['small'];
            $urls[] = $image['urls']['medium'];
            if (isset($image['urls']['large'])) {
                $urls[] = $image['urls']['large'];
            }
        }
        return array_flip($urls);
    }

    /**
     * Return if image popup zoom has been enabled in config
     *
     * @return boolean
     */
    public function getImagePopupZoom()
    {
        return isset($this->config->Content->enableImagePopupZoom)
            && $this->config->Content->enableImagePopupZoom === '1';
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
            $params = [
                'url' => $this->getThumbnail($size),
                'description' => '',
                'rights' => []
            ];
        }
        return $params;
    }

    /**
     * Allow record image to be downloaded?
     * If record image is converted from PDF, downloading is allowed only
     * for configured record formats.
     *
     * @return boolean
     */
    public function allowRecordImageDownload()
    {
        if (!$this->driver->tryMethod('allowRecordImageDownload', [], true)) {
            return false;
        }
        $master = $this->recordImageHelper->getMasterImageWithInfo(0);
        if (!$master['pdf']) {
            return true;
        }
        $formats = $this->config->Content->pdfCoverImageDownload ?? '';
        $formats = explode(',', $formats);
        return array_intersect($formats, $this->driver->getFormats());
    }

    /**
     * Return an array of all record images in all sizes
     *
     * @param string $language   Language for description and rights
     * @param bool   $thumbnails Whether to include thumbnail links if no image links
     *                           are found
     * @param bool   $includePdf Whether to include first PDF file when no image
     *                           links are found
     *
     * @return array
     */
    public function getAllImages($language, $thumbnails = true, $includePdf = true)
    {
        $recordId = $this->driver->getUniqueID();

        if ($this->cachedId === $recordId) {
            return $this->cachedImages;
        }

        $this->cachedId = $recordId;

        $sizes = ['small', 'medium', 'large', 'master'];
        $images = $this->driver->tryMethod('getAllImages', [$language, $includePdf]);
        if (null === $images) {
            $images = [];
        }
        if (empty($images) && $thumbnails) {
            $urls = [];
            foreach ($sizes as $size) {
                if ($thumb = $this->driver->getThumbnail($size)) {
                    $params = is_array($thumb) ? $thumb : [
                        'id' => $recordId
                    ];
                    $params['index'] = 0;
                    $params['size'] = $size;
                    $urls[$size] = $params;
                }
            }
            if ($urls) {
                $images[] = [
                    'urls' => $urls,
                    'description' => '',
                    'rights' => []
                ];
            }
        } else {
            foreach ($images as $idx => &$image) {
                foreach ($sizes as $size) {
                    if (!isset($image['urls'][$size])) {
                        continue;
                    }
                    $params = [
                        'id' => $recordId,
                        'index' => $idx,
                        'size' => $size
                    ];
                    $image['urls'][$size] = $params;
                }
                if (isset($image['highResolution'])
                    && !empty($image['highResolution'])
                ) {
                    foreach ($image['highResolution'] as $size => &$values) {
                        foreach ($values as $format => &$data) {
                            $data['params'] = [
                                'id' => $recordId,
                                'index' => $idx,
                                'size' => $size,
                                'format' => $format ?? 'jpg'
                            ];
                        }
                    }
                }
            }
        }
        return $this->cachedImages = $images;
    }

    /**
     * Return number of record images.
     *
     * @param string $size       Size of requested image
     * @param bool   $includePdf Whether to include first PDF file when no image
     * links are found
     *
     * @return int
     */
    public function getNumOfRecordImages($size, $includePdf = true)
    {
        $images = $this->driver->tryMethod('getAllImages', ['', $includePdf]);
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
            && $average = $this->driver->tryMethod('getAverageRating')
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
     * @param array $imageURLs Array of record image URLs as keys.
     *
     * @return boolean
     */
    public function containsNonImageURL($urls, $imageURLs)
    {
        if (!$urls) {
            return false;
        }
        foreach ($urls as $url) {
            if (!isset($imageURLs[$url['url']])) {
                return true;
            }
        }
        return false;
    }

    /**
     * Check if given array of urls contains pdf links
     *
     * @param array $urls Array of urls in the format returned by
     *                    getUrls and getOnlineUrls
     *
     * @return boolean
     */
    public function containsPdfUrl($urls)
    {
        if (!$urls) {
            return false;
        }
        foreach ($urls as $url) {
            if (strcasecmp(pathinfo($url['url'], PATHINFO_EXTENSION), 'pdf') === 0) {
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

    /**
     * Set rendered URLs
     *
     * @param array $urls Array of rendered URLs
     *
     * @return array
     */
    public function setRenderedUrls($urls)
    {
        $this->renderedUrls = $urls;
    }

    /**
     * Get rendered URLs
     *
     * @return array
     */
    public function getRenderedUrls()
    {
        return $this->renderedUrls;
    }

    /**
     * Render a source id element if necessary
     *
     * @return string
     */
    public function getSourceIdElement()
    {
        $view = $this->getView();
        if (isset($view->results) && is_callable([$view->results, 'getBackendId'])) {
            if ($view->results->getBackendId() === 'Blender') {
                return $this->renderTemplate('source-id-label.phtml');
            }
        }
        return '';
    }

    /**
     * Check if the record driver has a tab (regardless of whether it's active)
     *
     * @param string $tab Tab
     *
     * @return bool
     */
    public function hasTab($tab)
    {
        $tabs = $this->tabManager->getTabServices($this->driver);
        return isset($tabs[$tab]);
    }

    /**
     * Return author birth and death date.
     *
     * @return string HTML
     */
    public function getAuthorityBirthDeath()
    {
        if (!$this->driver->tryMethod('isAuthorityRecord')) {
            return '';
        }
        $birth = $this->driver->getBirthDateAndPlace();
        $death = $this->driver->getDeathDateAndPlace();
        if ($birth) {
            $birth['detail'] = null;
        }
        if ($death) {
            $death['detail'] = null;
        }

        return $this->renderTemplate('birth_death.phtml', compact('birth', 'death'));
    }

    /**
     * Return author birth and death date and place.
     *
     * @return string HTML
     */
    public function getAuthorityBirthDeathWithPlace()
    {
        if (!$this->driver->tryMethod('isAuthorityRecord')) {
            return '';
        }
        $birth = $this->driver->getBirthDateAndPlace();
        $death = $this->driver->getDeathDateAndPlace();

        return $this->renderTemplate('birth_death.phtml', compact('birth', 'death'));
    }

    /**
     * Return number of linked biblio records for an authority record.
     * Returns an array with keys 'author' and 'topic'
     * (number of biblio records where the authority is an author/topic)
     *
     * @param bool $onAuthorityPage Called from authority record page?
     *
     * @return array
     */
    public function getAuthoritySummary($onAuthorityPage = false)
    {
        $id = $this->driver->getUniqueID();
        $authorCnt = $this->authorityHelper->getRecordsByAuthorityId(
            $id, AuthorityHelper::AUTHOR2_ID_FACET, true
        );
        $topicCnt = $this->authorityHelper->getRecordsByAuthorityId(
            $id, AuthorityHelper::TOPIC_ID_FACET, true
        );

        $tabs = array_keys($this->tabManager->getTabsForRecord($this->driver));

        $summary = [
            'author' => [
                'cnt' => $authorCnt,
                'tabUrl' => in_array('AuthorityRecordsAuthor', $tabs)
                    ? $this->recordLinkHelper->getTabUrl(
                        $this->driver, 'AuthorityRecordsAuthor'
                    )
                    : null
            ],
            'topic' => [
                'cnt' => $topicCnt,
                'tabUrl' => in_array('AuthorityRecordsTopic', $tabs)
                    ? $this->recordLinkHelper->getTabUrl(
                        $this->driver, 'AuthorityRecordsTopic'
                    )
                    : null
            ]
        ];

        if ($onAuthorityPage) {
            $summary['author']['title'] = 'authority_records_author';
            $summary['topic']['title'] = 'authority_records_topic';
            $summary['author']['label'] = $summary['topic']['label']
                = 'authority_records_count';
        } else {
            $summary['author']['label'] = 'authority_records_author_count';
            $summary['topic']['label'] = 'authority_records_topic_count';
        }

        return $this->renderTemplate(
            'record-summaries.phtml',
            ['summary' => $summary, 'driver' => $this->driver]
        );
    }
}
