<?php

/**
 * Record driver view helper
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2010.
 * Copyright (C) The National Library of Finland 2015-2023.
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

use Finna\Form\Form;
use Finna\RecordDriver\Feature\ContainerFormatInterface;
use Finna\RecordDriver\SolrAipa;
use Finna\RecordTab\TabManager;
use Finna\Search\Solr\AuthorityHelper;
use Finna\Service\UserPreferenceService;
use VuFind\Config\Config;
use VuFind\Record\Loader;
use VuFind\Search\UrlQueryHelper;
use VuFind\Tags\TagsService;
use VuFind\View\Helper\Root\Url;

use function array_key_exists;
use function count;
use function in_array;
use function intval;
use function is_array;
use function is_string;

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
     * @var Loader
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
     * @var RecordImage
     */
    protected $recordImageHelper;

    /**
     * Authority helper
     *
     * @var AuthorityHelper
     */
    protected $authorityHelper;

    /**
     * Url helper
     *
     * @var Url
     */
    protected $urlHelper;

    /**
     * Record link helper
     *
     * @var RecordLinker
     */
    protected $recordLinker;

    /**
     * Local cache
     *
     * @var array
     */
    protected $cache = [];

    /**
     * Tab Manager
     *
     * @var TabManager
     */
    protected $tabManager;

    /**
     * Form
     *
     * @var Form
     */
    protected $form;

    /**
     * User preference service
     *
     * @var UserPreferenceService
     */
    protected $userPreferenceService;

    /**
     * Callback to get encapsulated records results
     *
     * @var callable
     */
    protected $getEncapsulatedResults;

    /**
     * Counter used to ensure unique ID attributes when several sets of encapsulated
     * records are displayed
     *
     * @var int
     */
    protected $indexStart = 1000;

    /**
     * Constructor
     *
     * @param TagsService           $tagsService            Tags service
     * @param Config                $config                 VuFind config
     * @param Loader                $loader                 Record loader
     * @param RecordImage           $recordImage            Record image helper
     * @param AuthorityHelper       $authorityHelper        Authority helper
     * @param Url                   $urlHelper              Url helper
     * @param RecordLinker          $recordLinker           Record link helper
     * @param TabManager            $tabManager             Tab manager
     * @param Form                  $form                   Form
     * @param UserPreferenceService $userPreferenceService  User preference
     *                                                      service
     * @param callable              $getEncapsulatedResults Callback to get
     *                                                      encapsulated
     *                                                      records results
     */
    public function __construct(
        TagsService $tagsService,
        Config $config,
        Loader $loader,
        RecordImage $recordImage,
        AuthorityHelper $authorityHelper,
        Url $urlHelper,
        RecordLinker $recordLinker,
        TabManager $tabManager,
        Form $form,
        UserPreferenceService $userPreferenceService,
        callable $getEncapsulatedResults
    ) {
        parent::__construct($tagsService, $config);
        $this->loader = $loader;
        $this->recordImageHelper = $recordImage;
        $this->authorityHelper = $authorityHelper;
        $this->urlHelper = $urlHelper;
        $this->recordLinker = $recordLinker;
        $this->tabManager = $tabManager;
        $this->form = $form;
        $this->userPreferenceService = $userPreferenceService;
        $this->getEncapsulatedResults = $getEncapsulatedResults;
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
     *
     * @deprecated
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
     *
     * @deprecated Not needed anymore since commenting is always allowed when enabled
     */
    public function commentingAllowed($user)
    {
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
     * Is repository library request form enabled for this record.
     *
     * @param string $context Context
     *
     * @return bool
     */
    public function repositoryLibraryRequestEnabled(string $context = ''): bool
    {
        if (!isset($this->config->Record->repository_library_request_sources)) {
            return false;
        }
        $enabled = in_array(
            $this->driver->tryMethod('getDataSource'),
            $this->config->Record->repository_library_request_sources->toArray()
        ) && $this->getRepositoryLibraryRequestFormId();

        if (!$enabled) {
            return false;
        }
        if (!$context) {
            // Context not specified, check for any:
            foreach (['holdings', 'organisation_info', 'results'] as $ctx) {
                $setting = "repository_library_request_in_$ctx";
                if ($this->config->Record->$setting ?? false) {
                    return true;
                }
            }
            return false;
        }
        $setting = "repository_library_request_in_$context";
        return $this->config->Record->$setting ?? false;
    }

    /**
     * Get repository library request form id.
     *
     * @return string|null
     */
    public function getRepositoryLibraryRequestFormId()
    {
        $formId = $this->config->Record->repository_library_request_form ?? null;
        if ($formId) {
            try {
                if ($this->form->getFormId() !== $formId) {
                    $this->form->setFormId($formId);
                }
                if (!$this->form->isEnabled()) {
                    $formId = null;
                }
            } catch (\VuFind\Exception\RecordMissing $e) {
                $formId = null;
            }
        }
        return $formId;
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
     * @param ?array $params            Optional array of parameters for the
     * link template
     * @param bool   $withInfo          return an array with link HTML and
     * returned linktype.
     * @param bool   $searchTabsFilters Include search tabs hiddenFilters in
     * the URL (needed when the link performs a search, but not when linking
     * to authority page).
     * @param bool   $switchType        Whether to switch to authority search
     * automatically
     *
     * @return string
     */
    public function getLink(
        $type,
        $lookfor,
        $params = [],
        $withInfo = false,
        $searchTabsFilters = true,
        $switchType = true
    ) {
        if (is_array($lookfor)) {
            $lookfor = $lookfor['name'];
        }
        $searchAction = !empty($this->getView()->browse)
            ? 'browse-' . $this->getView()->browse
            : ($params['searchAction'] ?? null);
        $params ??= [];

        $linkType = $params['linkType'] ?? $this->getAuthorityLinkType($type);
        $authId = null;
        if (isset($params['id'])) {
            // For BC: put non-mangled id into localId and keep the prefixed id in
            // 'id' element.
            $params['localId'] = $params['id'];
            // Add namespace to id
            $authId = $params['id'] = $this->driver->tryMethod('getAuthorityId', [$params['id'], $type], $params['id']);
        }

        // Attempt to switch Author search link to Authority link.
        if (
            $switchType
            && null !== $linkType
            && in_array($type, ['author', 'author-id', 'subject'])
            && $authId
        ) {
            $type = "authority-$linkType";
        }

        $params = array_merge(
            $params,
            [
                'driver' => $this->driver,
                'lookfor' => $lookfor,
                'searchAction' => $searchAction,
            ]
        );
        $link = $this->renderTemplate(
            'link-' . $type . '.phtml',
            $params
        );

        if ($link && $searchTabsFilters && !in_array($type, ['cites', 'citedBy'])) {
            $prepend = (!str_contains($link, '?')) ? '?' : '&amp;';

            $hiddenFilters = null;
            // Try to get hidden filters for the current search:
            if ($this->searchMemory) {
                $searchId = $this->driver->getExtraDetail('searchId')
                    ?? $this->getView()->plugin('searchMemory')->getLastSearchId();
                if ($searchId && ($search = $this->searchMemory->getSearchById($searchId))) {
                    $filters = UrlQueryHelper::buildQueryString(
                        [
                            'hiddenFilters' => $search->getParams()->getHiddenFiltersAsQueryParams(),
                        ]
                    );
                    $hiddenFilters = $filters ? $prepend . $filters : '';
                }
            }
            // If we couldn't get hidden filters for the current search, use last filters:
            if (null === $hiddenFilters) {
                $hiddenFilters = $this->getView()->plugin('searchTabs')
                    ->getCurrentHiddenFilterParams(
                        $this->driver->getSearchBackendIdentifier(),
                        false,
                        $prepend
                    );
            }

            $link .= $hiddenFilters;
        }

        return $withInfo ? [$link, $type] : $link;
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
     * Render a linked field.
     *
     * @param string $type    Link type
     * @param string $lookfor Link label or string to search for
     * @param array  $data    Additional link data
     * @param array  $params  Optional array of parameters for the link template
     *
     * @return string HTML
     */
    public function getLinkedFieldElement(
        string $type,
        string $lookfor,
        array $data,
        array $params = []
    ): string {
        if (empty($this->config->LinkPopovers->enabled)) {
            return $this->getAuthorityLinkElement($type, $lookfor, $data, $params);
        }

        $id = $data['id'] ?? '';
        $ids = $data['ids'] ?? [$id];
        $links = $this->config->LinkPopovers->links->{$type}
            ?? $this->config->LinkPopovers->links->{'*'}
            ?? 'search_as_keyword:keyword';

        $fieldLinks = [];
        foreach ($links ? explode('|', $links) : [] as $linkDefinition) {
            [$linkText, $linkType] = explode(':', "$linkDefinition:");
            // Early bypass of authority-page if we don't have an id to avoid all the
            // getLink processing:
            if (!$id && 'authority-page' === $linkType) {
                continue;
            }
            // Discard search tabs hiddenFilters when jumping to Authority page
            $preserveSearchTabsFilters = 'authority-page' !== $linkType;

            [$escapedUrl, $urlType] = $this->getLink(
                $linkType,
                $lookfor,
                $params + compact('id', 'ids', 'linkType'),
                true,
                $preserveSearchTabsFilters,
                false
            );
            if (!$escapedUrl) {
                continue;
            }

            $fieldLinks[]
                = compact('linkText', 'linkType', 'urlType', 'escapedUrl');
        }

        $authorityType = $params['authorityType'] ?? 'Personal Name';
        $authorityType
            = $this->config->Authority->typeMap->{$authorityType} ?? $authorityType;

        $externalLinks = [];
        $language = $this->getView()->layout()->userLang;
        foreach ($this->config->LinkPopovers->external_links ?? [] as $link) {
            $linkConfig = explode('||', $link);
            if (!isset($linkConfig[4])) {
                continue;
            }
            foreach ($ids as $id) {
                if (preg_match('/' . $linkConfig[2] . '/', $id, $matches)) {
                    $url = preg_replace_callback(
                        '/\{\d\}/',
                        function ($m) use ($matches) {
                            $index = intval(trim($m[0], '{}'));
                            return $matches[$index] ?? '';
                        },
                        $linkConfig[3]
                    );
                    $url = str_replace('{lang}', $language, $url);
                    $displayId = '';
                    if ($linkConfig[4]) {
                        if ('full' === $linkConfig[4]) {
                            $displayId = $id;
                        } else {
                            $index = intval(trim($linkConfig[4], '{}'));
                            $displayId = $matches[$index] ?? '';
                        }
                    }
                    $externalLinks[] = [
                        'text' => $linkConfig[0],
                        'title' => $linkConfig[1],
                        'url' => $url,
                        'displayId' => $displayId,
                    ];
                }
            }
        }

        static $fieldIndex = 0;
        ++$fieldIndex;
        $elementParams = [
            'driver' => $this->driver,
            'searchAction' => $params['searchAction'] ?? null,
            'label' => $lookfor,
            'ids' => array_filter($ids),
            'authIds' => array_filter(
                array_map(
                    function ($s) use ($type) {
                        return $this->driver
                            ->tryMethod('getAuthorityId', [$s, $type], '');
                    },
                    $ids
                )
            ),
            'authorityLink' => $id && $this->isAuthorityLinksEnabled(),
            'type' => $type,
            'authorityType' => $authorityType,
            'title' => $params['title'] ?? null,
            'classes' => $params['class'] ?? [],
            'fieldLinks' => $fieldLinks,
            'externalLinks' => $externalLinks,
            'fieldIndex' => $fieldIndex,
        ];
        if ($additionalData = $this->composeAdditionalData($data, $params)) {
            $elementParams['additionalDataHtml'] = $additionalData;
        }
        if (!empty($params['description']) && !empty($data['description'])) {
            $elementParams['description'] = $data['description'];
        }

        return $this->renderTemplate('popover-link-element.phtml', $elementParams);
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
        $type,
        $lookfor,
        $data,
        $params = []
    ) {
        $id = $data['id'] ?? null;
        $linkType = $this->getAuthorityLinkType($type);

        // Discard search tabs hiddenFilters when jumping to Authority page
        $preserveSearchTabsFilters = $linkType !== AuthorityHelper::LINK_TYPE_PAGE;

        [$escapedUrl, $urlType] = $this->getLink(
            $type,
            $lookfor,
            $params + compact('id', 'linkType'),
            true,
            $preserveSearchTabsFilters
        );

        $authId = $this->driver->tryMethod('getAuthorityId', [$id, $type], '');
        $authorityType = $params['authorityType'] ?? '';
        $authorityType
            = $this->config->Authority->typeMap->{$authorityType} ?? $authorityType;

        $elementParams = [
           'escapedUrl' => trim($escapedUrl),
           'record' => $this->driver,
           'searchAction' => $params['searchAction'] ?? null,
           'label' => $params['label'] ?? $lookfor,
           'id' => $authId,
           'authorityLink' => $id && $this->isAuthorityLinksEnabled(),
           'showInlineInfo' => false,
           'recordSource' => $this->driver->tryMethod('getDataSource'),
           'type' => $type,
           'authorityType' => $authorityType,
           'title' => $params['title'] ?? null,
           'classes' => $params['class'] ?? [],
        ];
        if ($additionalData = $this->composeAdditionalData($data, $params)) {
            $elementParams['additionalData'] = $additionalData;
        }
        if (!empty($params['description']) && !empty($data['description'])) {
            $elementParams['description'] = $data['description'];
        }

        return $this->renderTemplate('authority-link-element.phtml', $elementParams);
    }

    /**
     * Is authority functionality enabled?
     *
     * @return bool
     */
    public function isAuthorityEnabled()
    {
        return
            $this->config->Authority
            && (bool)$this->config->Authority->enabled ?? false;
    }

    /**
     * Get comments associated with the current record.
     *
     * @return CommentsEntityInterface[]
     */
    public function getComments(): array
    {
        $cacheKey = __FUNCTION__ . "{$this->driver->getUniqueId()}\t{$this->driver->getSourceIdentifier()}";
        if (isset($this->cache[$cacheKey])) {
            return $this->cache[$cacheKey];
        }
        return $this->cache[$cacheKey] = parent::getComments();
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
     * Compose additional data string for a link
     *
     * @param array $data   Link data
     * @param array $params Link params
     *
     * @return string
     */
    protected function composeAdditionalData(array $data, array $params): string
    {
        if (isset($params['additionalData'])) {
            return $params['additionalData'];
        }
        // Additional author information fields:
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
            return $this->getAuthorityLinkAdditionalData($additionalData);
        }
        return '';
    }

    /**
     * Utility function for rendering an author search link element.
     *
     * @param array $data Author data (name, role, date)
     *
     * @return string HTML
     */
    protected function getAuthorLinkElement($data)
    {
        $params = [
           'record' => $this->driver,
           'author' => $data,
        ];

        return trim($this->renderTemplate('author-link-element.phtml', $params));
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
            'label' => $label,
        ];
        if ($formAttr) {
            $context['formAttr'] = $formAttr;
        }
        return $this->contextHelper->renderInContext(
            'record/checkbox.phtml',
            $context
        );
    }

    /**
     * Return if image popup zoom has been enabled in config
     *
     * @return boolean
     */
    public function getImagePopupZoom()
    {
        if (!($this->config->Content->enableImagePopupZoom ?? false)) {
            return false;
        }
        return in_array(
            $this->driver->tryMethod('getRecordFormat'),
            explode(':', $this->config->Content->zoomFormats ?? '')
        );
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
                'rights' => [],
            ];
        }
        return $params;
    }

    /**
     * Allow record image to be downloaded?
     * If record image is converted from PDF, downloading is allowed only
     * for configured record formats.
     *
     * @deprecated Please use downloadable variable found in image array
     *             returned from record->getAllImages().
     *
     * @return bool
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

        $cacheKey = __FUNCTION__ . "$recordId\t" . ($thumbnails ? '1' : '0')
            . ($includePdf ? '1' : '0');
        if (isset($this->cache[$cacheKey])) {
            return $this->cache[$cacheKey];
        }

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
                        'id' => $recordId,
                    ];
                    $params['index'] = 0;
                    $params['size'] = $size;
                    $urls[$size] = $params;
                }
            }
            if ($urls) {
                // Make sure we have all sizes:
                if (!isset($urls['small'])) {
                    $urls['small'] = $urls['medium']
                        ?? $urls['large'];
                }
                if (!isset($urls['medium'])) {
                    $urls['medium'] = $urls['large']
                        ?? $urls['small'];
                }

                $images[] = [
                    'urls' => $urls,
                    'description' => '',
                    'rights' => [],
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
                        'size' => $size,
                    ];
                    $image['urls'][$size] = $params;
                }
                if (!empty($image['highResolution'])) {
                    foreach ($image['highResolution'] as $size => &$values) {
                        foreach ($values as $key => &$data) {
                            $data['params'] = [
                                'id' => $recordId,
                                'index' => $idx,
                                'size' => $size,
                                'format' => $data['format'] ?? 'jpg',
                                'key' => $key,
                                'type' => 'highresimg',
                            ];
                        }
                    }
                }
            }
        }
        return $this->cache[$cacheKey] = $images;
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
                'context' => $context,
            ]
        );
    }

    /**
     * Render citation links
     *
     * @return string
     */
    public function getCitationLinks()
    {
        $searchOptions = $this->getView()->plugin('searchOptions');
        if (
            !$searchOptions($this->driver->getSourceIdentifier())->displayCitationLinksInResults()
            || !($links = $this->driver->tryMethod('getCitations'))
        ) {
            return '';
        }

        return $this->renderTemplate(
            'result-citation-links.phtml',
            [
                'driver' => $this->driver,
                'links' => $links,
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
     *
     * @deprecated Use upstream rating support
     */
    public function getRating()
    {
        return '';
    }

    /**
     * This is here for backwards compability.
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
     * This is here for backwards compability.
     * Return all record image urls as array keys.
     *
     * @return array
     */
    public function getAllRecordImageUrls()
    {
        $images = $this->driver->tryMethod('getAllImages', ['', false]);
        $urls = [];
        foreach ($images as $image) {
            $urls = [...$urls, ...array_values($image['urls'])];
        }
        return array_flip($urls);
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
     * @return bool
     */
    public function ratingAllowed()
    {
        return $this->driver->tryMethod('isRatingAllowed');
    }

    /**
     * Set rendered URLs
     *
     * @param array $urls Array of rendered URLs
     *
     * @return void
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
     *
     * @deprecated Use getLabelList instead
     */
    public function getSourceIdElement()
    {
        return $this->getLabelList();
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
        $tabs = array_keys($this->tabManager->getTabsForRecord($this->driver));
        $summary = [
            'author' => [
                // cnt is no longer available beforehand. Use
                // $this->authority()->getCountAsAuthor($driver->getUniqueID()) when
                // needed.
                'cnt' => null,
                'tabUrl' => in_array('AuthorityRecordsAuthor', $tabs)
                    ? $this->recordLinker->getTabUrl(
                        $this->driver,
                        'AuthorityRecordsAuthor'
                    )
                    : null,
            ],
            'topic' => [
                // cnt is no longer available beforehand. Use
                // $this->authority()->getCountAsTopic($driver->getUniqueID()) when
                // needed.
                'cnt' => null,
                'tabUrl' => in_array('AuthorityRecordsTopic', $tabs)
                    ? $this->recordLinker->getTabUrl(
                        $this->driver,
                        'AuthorityRecordsTopic'
                    )
                    : null,
            ],
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

    /**
     * Returns additional information related to external material links.
     *
     * @return string
     */
    public function getExternalLinkAdditionalInfo()
    {
        $translator = $this->getView()->plugin('translate');
        $externalLinkText = $translator('external_link');
        switch ($this->driver->getDataSource()) {
            case 'aoe':
                $source = ' aoe.fi';
                break;
            default:
                $source = '';
        }
        return '(' . $externalLinkText . $source . ')';
    }

    /**
     * Returns a data source specific material disclaimer.
     *
     * @return string
     */
    public function getMaterialDisclaimer()
    {
        try {
            return $this->renderTemplate(
                'material-disclaimer-' . $this->driver->getDataSource() . '.phtml',
                ['externalLink' => $this->driver->tryMethod('getExternalLink')]
            );
        } catch (\Laminas\View\Exception\RuntimeException $e) {
            // Template does not exist.
            return '';
        }
    }

    /**
     * Returns a rendered external rating link or false if there is no link.
     *
     * @return string|false
     */
    public function getExternalRatingLink()
    {
        if (!$url = $this->driver->tryMethod('getExternalRatingLink')) {
            return false;
        }
        try {
            return $this->renderTemplate(
                'external-rating-link-' . $this->driver->getDataSource() . '.phtml',
                ['externalRatingLink' => $url]
            );
        } catch (\Laminas\View\Exception\RuntimeException $e) {
            // Data source specific template does not exist.
            return $this->renderTemplate(
                'external-rating-link.phtml',
                ['externalRatingLink' => $url]
            );
        }
    }

    /**
     * Returns a translated copyright text.
     *
     * @param string $copyright Copyright
     *
     * @return string HTML-escaped translation
     */
    public function translateCopyright(string $copyright): string
    {
        $transEsc = $this->getView()->plugin('transEsc');

        $label = $transEsc($copyright);
        if ($copyright === 'Luvanvarainen käyttö / ei tiedossa') {
            $label = $transEsc('usage_F');
        } else {
            $translationEmpty = $this->getView()->plugin('translationEmpty');
            if (!$translationEmpty("rightsstatement_$copyright")) {
                $label = $transEsc("rightsstatement_$copyright");
            }
        }
        return $label;
    }

    /**
     * Check if full width layout should be used for the record
     *
     * @return bool
     */
    public function hasFullWidthLayout(): bool
    {
        if ($this->driver instanceof SolrAipa) {
            return true;
        }
        return false;
    }

    /**
     * Check if large image layout should be used for the record
     *
     * @return bool
     */
    public function hasLargeImageLayout(): bool
    {
        if ($this->driver->tryMethod('getModels')) {
            return true;
        }
        $language = $this->getView()->layout()->userLang;

        $imageTypes = ['small', 'medium', 'large', 'master'];
        $images = $this->getAllImages($language, false, false);
        $hasValidImages = false;
        foreach ($images as $image) {
            if (array_intersect(array_keys($image['urls'] ?? []), $imageTypes)) {
                $hasValidImages = true;
                break;
            }
        }
        if (!$hasValidImages) {
            return false;
        }

        // Check for record formats that always use large image layout:
        $largeImageRecordFormats
            = isset($this->config->Record->large_image_record_formats)
            ? $this->config->Record->large_image_record_formats->toArray()
            : ['lido', 'forward', 'forwardAuthority'];
        $recordFormat = $this->driver->tryMethod('getRecordFormat');
        if (in_array($recordFormat, $largeImageRecordFormats)) {
            return true;
        }

        // Check for formats that use large image layout:
        $largeImageFormats
            = isset($this->config->Record->large_image_formats)
            ? $this->config->Record->large_image_formats->toArray()
            : [
                '0/Image/',
                '0/PhysicalObject/',
                '0/WorkOfArt/',
                '0/Video/',
            ];
        $formats = $this->driver->tryMethod('getFormats');
        if (array_intersect($formats, $largeImageFormats)) {
            return true;
        }

        return false;
    }

    /**
     * Get the organisation menu position for the record
     *
     * @return string|false 'sidebar', 'inline' or false for no menu
     */
    public function getOrganisationMenuPosition()
    {
        $localSources = ['Solr', 'SolrAuth', 'L1'];
        $source = $this->driver->getSourceIdentifier();
        if (!in_array($source, $localSources)) {
            return false;
        }
        return $this->hasLargeImageLayout() ? 'inline' : 'sidebar';
    }

    /**
     * Get preferred record source for deduplicated records.
     *
     * Note: This works with DeduplicationListener, so make sure to
     * update both as necessary.
     *
     * @return string
     */
    public function getPreferredSource(): string
    {
        if (null === $this->driver) {
            return '';
        }
        // Is selecting a datasource mandatory? If not, we can just rely on
        // DeduplicationListener having selected the correct one.
        if (empty($this->config->Record->select_dedup_holdings_library)) {
            return $this->driver->tryMethod('getDataSource', [], '');
        }
        if ($params = $this->getView()->params) {
            $filterList = $params->getFilterList();
            if (!empty($filterList['Organisation'])) {
                return $this->driver->tryMethod('getDataSource', [], '');
            }
        }
        $dedupData = $this->driver->tryMethod('getDedupData', [], []);
        // Return driver's datasource if deduplication data is not set or
        // the count of deduplication data is 1.
        // There cannot be any other sources in this case.
        if (count($dedupData) <= 1) {
            return $this->driver->tryMethod('getDataSource', [], '');
        }
        $preferredSources = $this->userPreferenceService->getPreferredDataSources();
        foreach ($preferredSources as $source) {
            if (!empty($dedupData[$source])) {
                return $source;
            }
        }
        return '';
    }

    /**
     * Get Similar Items Carousel tab
     *
     * @return \VuFind\RecordTab\SimilarItemsCarousel
     */
    public function getSimilarItemsCarousel(): \VuFind\RecordTab\SimilarItemsCarousel
    {
        return $this->tabManager->getSimilarItemsCarouselTab($this->driver);
    }

    /**
     * Get container js classes if the driver supports ajax status and/or has
     * preferred source.
     *
     * @return string
     */
    public function getContainerJsClasses(): string
    {
        $classes = [];
        if (
            !empty($this->driver)
            && ($this->driver->supportsAjaxStatus()
            || $this->getView()->plugin('identifierLinker')($this->driver, 'results') !== '')
        ) {
            $classes[] = 'ajaxItem';
        }
        if (!$this->getPreferredSource()) {
            $classes[] = 'js-item-done';
        }
        return implode(' ', $classes);
    }

    /**
     * Returns HTML for encapsulated records.
     *
     * @param array $opt        Options
     * @param ?int  $offset     Record offset
     *                          (used when loading more results via AJAX)
     * @param ?int  $indexStart Result item offset in DOM
     *                          (used when loading more results via AJAX)
     *
     * @return string
     */
    public function renderEncapsulatedRecords(
        array $opt = [],
        ?int $offset = null,
        ?int $indexStart = null
    ): string {
        foreach (array_keys($opt) as $key) {
            if (
                !in_array(
                    $key,
                    [
                    'limit',
                    'page',
                    'showAllLink',
                    'view',
                    ]
                )
            ) {
                unset($opt[$key]);
            }
        }

        $id = $opt['id'] = $this->driver->getUniqueID();

        // Check for an encapsulated record ID
        $parts = explode(
            ContainerFormatInterface::ENCAPSULATED_RECORD_ID_SEPARATOR,
            $id,
            2
        );
        if ($id !== $parts[0] && $parts[0] === '0') {
            // Special case for preview records.
            // Always request all remaining encapsulated records because the load
            // more AJAX handler currently has no access to the previewed record.
            $opt['limit'] = null;
        }

        $loadMore = (int)$offset > 0;

        // null is an accepted limit value (no limit)
        if (!array_key_exists('limit', $opt)) {
            $opt['limit'] = 6;
        }
        $opt['showAllLink'] ??= true;
        $view = $opt['view'] ??= 'grid';

        $resultsCopy = ($this->getEncapsulatedResults)($opt);
        $resultsCopy->setContainerRecord($this->driver);

        $total = $resultsCopy->getResultTotal();
        if (!$loadMore) {
            $idStart = $this->indexStart;
            $this->indexStart += $total;
        } else {
            // Load more results using given $indexStart and $offset
            $idStart = $indexStart;
            $resultsCopy->overrideStartRecord($offset);
        }

        $resultsCopy->performAndProcessSearch();

        $html = $this->renderTemplate(
            'encapsulated-records.phtml',
            [
                'id' => $id,
                'results' => $resultsCopy,
                'params' => $resultsCopy->getParams(),
                'indexStart' => $idStart,
                'view' => $view,
                'total' => $total,
                'showAllLink' =>
                    $opt['showAllLink']
                    && null !== $opt['limit']
                    && $opt['limit'] < $total,
            ]
        );

        return $html;
    }

    /**
     * Conditionally render a full width banner.
     *
     * @return string
     */
    public function getBanner(): string
    {
        if ($this->hasFullWidthLayout()) {
            return $this->renderTemplate('banner.phtml');
        }
        return '';
    }

    /**
     * Get notes associated with this record in user lists.
     *
     * @param int $list_id ID of list to load tags from (null for all lists)
     * @param int $user_id ID of user to load tags from (null for all users)
     *
     * @return string[]
     */
    public function getListNotes($list_id = null, $user_id = null)
    {
        // TODO: handle notes for different list types more properly
        if ($this->getView()->layout()->templateDir === 'reservationlist') {
            return [];
        }
        return parent::getListNotes($list_id, $user_id);
    }
}
