<?php

/**
 * Feed service
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2016-2023.
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
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:developer_manual Wiki
 */

namespace Finna\Feed;

use Finna\OrganisationInfo\OrganisationInfo;
use Laminas\Feed\Reader\Entry\AbstractEntry;
use Laminas\Feed\Reader\Feed\AbstractFeed;
use Laminas\Feed\Reader\Reader;
use Laminas\Mvc\Controller\Plugin\Url;
use Laminas\View\Helper\ServerUrl;
use Psr\Container\ContainerInterface;
use VuFind\Cache\Manager as CacheManager;
use VuFind\Config\Config;
use VuFind\View\Helper\Root\CleanHtml;
use VuFindTheme\View\Helper\ImageLink;

use function in_array;
use function is_object;
use function is_string;
use function strlen;

/**
 * Feed service
 *
 * @category VuFind
 * @package  Content
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:developer_manual Wiki
 */
class Feed implements
    \VuFind\I18n\Translator\TranslatorAwareInterface,
    \VuFindHttp\HttpServiceAwareInterface,
    \Laminas\Log\LoggerAwareInterface
{
    use \VuFind\I18n\Translator\TranslatorAwareTrait;
    use \VuFindHttp\HttpServiceAwareTrait;
    use \VuFind\Log\LoggerAwareTrait;

    /**
     * Main configuration.
     *
     * @var Config
     */
    protected $mainConfig;

    /**
     * Feed configuration.
     *
     * @var Config
     */
    protected $feedConfig;

    /**
     * Feed configuration for the organisation info page.
     *
     * @var Config
     */
    protected $organisationInfoFeedConfig;

    /**
     * Cache manager
     *
     * @var CacheManager
     */
    protected $cacheManager;

    /**
     * URL helper
     *
     * @var Url
     */
    protected $urlHelper;

    /**
     * Server URL helper
     *
     * @var ServerUrl
     */
    protected $serverUrl;

    /**
     * Image link helper
     *
     * @var ImageLink
     */
    protected $imageLinkHelper;

    /**
     * Clean HTML helper
     *
     * @var CleanHtml
     */
    protected $cleanHtml;

    /**
     * Organisation info service
     *
     * @var OrganisationInfo
     */
    protected $organisationInfo;

    /**
     * Constructor.
     *
     * @param Config           $config        Main configuration
     * @param Config           $feedConfig    Feed configuration
     * @param Config           $orgFeedConfig Organisation info page feed
     * configuration
     * @param CacheManager     $cm            Cache manager
     * @param Url              $url           URL helper
     * @param ServerUrl        $serverUrl     Server URL helper
     * @param ImageLink        $imageLink     Image link helper
     * @param CleanHtml        $cleanHtml     Clean HTML helper
     * @param OrganisationInfo $orgInfo       Organisation info service
     */
    public function __construct(
        Config $config,
        Config $feedConfig,
        Config $orgFeedConfig,
        CacheManager $cm,
        Url $url,
        ServerUrl $serverUrl,
        ImageLink $imageLink,
        CleanHTML $cleanHtml,
        OrganisationInfo $orgInfo
    ) {
        $this->mainConfig = $config;
        $this->feedConfig = $feedConfig;
        $this->organisationInfoFeedConfig = $orgFeedConfig;
        $this->cacheManager = $cm;
        $this->urlHelper = $url;
        $this->serverUrl = $serverUrl;
        $this->imageLinkHelper = $imageLink;
        $this->cleanHtml = $cleanHtml;
        $this->organisationInfo = $orgInfo;
    }

    /**
     * Get feed configuration.
     *
     * Returns an array with the keys:
     *   - 'config' VuFind\Config Feed configuration
     *   - 'url'    string        Feed URL
     *
     * @param string $id Feed id
     *
     * @return bool|array
     */
    public function getFeedConfig($id)
    {
        // Check for an organisation info feed:
        $idParts = explode('|', $id);
        if ('organisation-info' === $idParts[0] && isset($idParts[4])) {
            [, $id, $locationId, $type, $feedType] = $idParts;
            $sectors = 'museum' === $type ? ['mus'] : ['lib'];
            $result = $this->organisationInfo->getDetails($sectors, $id, $locationId);

            $url = '';
            foreach ($result['rss'] as $current) {
                if ($feedType === $current['feedType']) {
                    $url = $current['url'];
                    break;
                }
            }
            if (!$url) {
                $this->logError("Missing feed URL (id $id)");
                return false;
            }

            $feedConfig = ['url' => $url];

            $key = "organisation-info-$feedType";
            if (isset($this->organisationInfoFeedConfig[$key])) {
                $resultConfig = $this->organisationInfoFeedConfig[$key]->toArray();
            } else {
                $resultConfig = ['items' => 5];
            }
            $resultConfig['type'] = 'grid';
            $resultConfig['active'] = 1;
            $feedConfig['result'] = new Config($resultConfig);
            return $feedConfig;
        }

        if (!isset($this->feedConfig[$id])) {
            $this->logError("Missing configuration (id $id)");
            return false;
        }

        $result = $this->feedConfig[$id];
        if (!$result->active) {
            $this->logError("Feed inactive (id $id)");
            return false;
        }

        if (empty($result->url) && !isset($result->ilsList)) {
            $this->logError("Missing feed URL (id $id)");
            return false;
        }

        $language = $this->translator->getLocale();

        $url = $result->url;
        if (isset($url[$language])) {
            $url = trim($url[$language]);
        } elseif (isset($url['*'])) {
            $url = trim($url['*']);
        } elseif (!isset($result->ilsList)) {
            $this->logError("Missing feed URL (id $id)");
            return false;
        }

        return compact('result', 'url');
    }

    /**
     * Utility function for extracting an image URL from a HTML snippet.
     *
     * @param string $html HTML snippet.
     *
     * @return mixed null|string
     */
    protected function extractImage($html)
    {
        if (empty($html)) {
            return null;
        }
        $doc = new \DOMDocument();
        // Silence errors caused by invalid HTML
        libxml_use_internal_errors(true);
        if (!$doc->loadHTML($html)) {
            return null;
        }
        libxml_clear_errors();

        $img = null;
        $imgs = iterator_to_array($doc->getElementsByTagName('img'));
        if (!empty($imgs)) {
            $img = $imgs[0];
        }

        return $img ? $img->getAttribute('src') : null;
    }

    /**
     * Check for a local file and create a timestamped link if found
     *
     * @param string $url url
     *
     * @return mixed null|string
     */
    protected function checkLocalFile($url)
    {
        $urlParts = parse_url($url);
        $imgLink = null;
        if (empty($urlParts['host'])) {
            $file = preg_replace(
                '/^\/?themes\/[^\/]+\/images\//',
                '',
                $url
            );
            $imgLink = ($this->imageLinkHelper)($file);
        }
        return $imgLink;
    }

    /**
     * Return feed content and settings in an array with the keys:
     *   - 'channel' Laminas\Feed\Reader\Feed\Rss Feed
     *   - 'items'   array                     Feed item data
     *   - 'config'  VuFind\Config             Feed configuration
     *   - 'modal'   boolean                   Display feed content in a modal
     *
     * @param string $id      Feed id
     * @param string $viewUrl View URL
     *
     * @return mixed null|array
     */
    public function readFeed($id, $viewUrl)
    {
        if (!$config = $this->getFeedConfig($id)) {
            throw new \Exception('Error reading feed');
        }
        return $this->processReadFeed($config, $viewUrl, $id);
    }

    /**
     * Utility function for processing a feed (see readFeed).
     *
     * @param array  $feedConfig Configuration
     * @param string $viewUrl    View URL
     * @param string $id         Feed id (needed when the feed content is shown on a
     * content page or in a modal)
     *
     * @return array
     */
    protected function processReadFeed($feedConfig, $viewUrl, $id = null)
    {
        $config = $feedConfig['result'];
        $url = trim($feedConfig['url']);

        $httpClient = $this->httpService->createClient();
        $httpClient->setOptions(['useragent' => 'VuFind']);
        $httpClient->setOptions(['timeout' => 30]);
        Reader::setHttpClient($httpClient);

        $cacheKey = (array)$feedConfig;
        $cacheKey['language'] = $this->translator->getLocale();

        // Check for cached version
        $cacheDir
            = $this->cacheManager->getCache('feed')->getOptions()->getCacheDir();
        $localFile = "$cacheDir/feed-" . md5(var_export($cacheKey, true)) . '.xml';
        $maxAge = isset($this->mainConfig->Content->feedcachetime)
            && '' !== $this->mainConfig->Content->feedcachetime
            ? $this->mainConfig->Content->feedcachetime : 10;
        if (
            $maxAge && is_readable($localFile)
            && time() - filemtime($localFile) < $maxAge * 60
        ) {
            if ($result = unserialize(file_get_contents($localFile))) {
                // Include feed object for downstream usage (cannot be serialized):
                // TODO: Get rid of channel requirement in downstream code
                $result['channel'] = Reader::importString($result['feedXml']);
                return $result;
            }
        }

        // No cache available, read from source.
        $channel = null;
        if (strstr($url, 'finna-test.fi') || strstr($url, 'finna-pre.fi')) {
            // Refuse to load feeds from finna-test.fi or finna-pre.fi
            $feedStr = <<<EOT
                <?xml version="1.0" encoding="UTF-8"?>
                <rss xmlns:atom="http://www.w3.org/2005/Atom" version="2.0">
                  <channel>
                    <atom:link href="" rel="self" type="application/rss+xml"/>
                    <link></link>
                    <title><![CDATA[<!-- Feed URL blocked -->]]></title>
                    <description></description>
                  </channel>
                </rss>
                EOT;
            $channel = Reader::importString($feedStr);
        } elseif (preg_match('/^http(s)?:\/\//', $url)) {
            // Absolute URL
            try {
                $channel = Reader::import($url);
            } catch (\Exception $e) {
                $this->logError(
                    "Error importing feed from url $url: " . $e->getMessage()
                );
            }
        } elseif (substr($url, 0, 1) === '/') {
            // Relative URL
            $url = substr($viewUrl, 0, -1) . $url;
            try {
                $channel = Reader::import($url);
            } catch (\Exception $e) {
                $this->logError(
                    "Error importing feed from url $url: " . $e->getMessage()
                );
            }
        } else {
            // Local file
            $file = APPLICATION_PATH . '/' . ltrim($url, '/');
            if (!is_file($file)) {
                $this->logError("File $file (from $url) could not be found");
            }
            try {
                $channel = Reader::importFile($file);
            } catch (\Exception $e) {
                $this->logError(
                    "Error importing feed from file $file: " . $e->getMessage()
                );
            }
        }

        if (!$channel) {
            // Cache also a failed load as an empty feed XML
            $feedStr = <<<EOT
                <?xml version="1.0" encoding="UTF-8"?>
                <rss xmlns:atom="http://www.w3.org/2005/Atom" version="2.0">
                  <channel>
                    <atom:link href="" rel="self" type="application/rss+xml"/>
                    <link></link>
                    <title>Feed could not be loaded</title>
                    <description></description>
                  </channel>
                </rss>
                EOT;
            $channel = Reader::importString($feedStr);
        }

        $result = $this->parseFeed($channel, $config, $id);
        file_put_contents($localFile, serialize($result));
        // Include feed object for downstream usage (cannot be serialized):
        // TODO: Get rid of channel requirement in downstream code
        $result['channel'] = $channel;
        return $result;
    }

    /**
     * Function to parse feed with config
     *
     * @param AbstractFeed $channel Feed channel
     * @param Config       $config  Feed config
     * @param string|null  $id      Feed ID (required when feed content is
     *                              displayed on content-page or modal)
     *
     * @return array
     */
    public function parseFeed($channel, $config, $id = null)
    {
        $modal = false;
        $showFullContentOnSite = isset($config->linkTo)
            && in_array($config->linkTo, ['modal', 'content-page']);

        $modal = $config->linkTo == 'modal';
        $contentPage = $config->linkTo == 'content-page';
        $dateFormat = $config->dateFormat ?? 'j.n.';
        $contentDateFormat = $config->contentDateFormat ?? 'j.n.Y';
        $fullDateFormat = $config->fullDateFormat ?? 'j.n.Y';
        $cleanContent = $config->cleanContent ?? true;
        $titleTruncateSize = (int)($config->titleTruncateSize ?? 70);
        $displayFormatHeader = $config->displayFormatHeader ?? false;
        $titlePosition = $config->titlePosition ?? null;

        $contentNavigation = $config->feedcontentNavigation ?? true;
        $nextArticles = $config->feedcontentNextArticles ?? false;
        $additionalHtml = $config->feedcontentadditionalHtml ?? '';

        $itemsCnt = $config->items ?? null;
        $elements = $config->content ?? [];
        $allowXcal = $elements['xcal'] ?? true;
        $timeRegex = '/^(.*?)([0-9]|0[0-9]|1[0-9]|2[0-3]):[0-5][0-9]$/';

        $content = [
            'id' => 'getId',
            'title' => 'getTitle',
            'text' => 'getContent',
            'description' => 'getDescription',
            'format' => 'getFormat',
            'image' => 'getEnclosure',
            'link' => 'getLink',
            'date' => 'getDateCreated',
            'contentDate' => 'getDateCreated',
        ];

        $xpathContent = [
            'html' => '//item/content:encoded',
        ];

        $xcalContent = [
            'dtstart',
            'dtend',
            'location',
            'featured',
            'content',
            'organizer',
            'location-address',
            'location-city',
            'organizer-url',
            'url',
            'cost',
            'categories',
        ];

        $items = [];
        $cnt = 0;
        $xpath = null;
        $allowedImages = [];

        foreach ($channel as $item) {
            if (!$xpath) {
                $xpath = ($item instanceof AbstractEntry) ? $item->getXpath() : '';
            }
            $data = [];
            $data['modal'] = $modal;
            $data['titleTruncateSize'] = $titleTruncateSize;
            $data['displayFormatHeader'] = $displayFormatHeader;
            $data['titlePosition'] = $titlePosition;
            foreach ($content as $setting => $method) {
                if (
                    !isset($elements[$setting])
                    || $elements[$setting] != 0
                ) {
                    $value = $item->{$method}();
                    if (is_object($value) && !($value instanceof \DateTime)) {
                        $value = get_object_vars($value);
                    }

                    if ($setting == 'image') {
                        if (!$value || stripos($value['type'], 'image') === false) {
                            // Attempt to parse image URL from content
                            if ($value = $this->extractImage($item->getContent())) {
                                $value = ['url' => $value];
                            }
                        }
                        if (!empty($value['url'])) {
                            $imgLink = $this->checkLocalFile($value['url']);
                            if (null !== $imgLink) {
                                $value['url'] = $imgLink;
                            } elseif ($id) {
                                $allowedImages[] = $value['url'];
                                $value['url']
                                    = $this->proxifyImageUrl($value['url'], $id);
                            }
                        }
                    } elseif ($setting == 'date') {
                        if (null !== $value) {
                            $date = $value;
                            if ($dateFormat) {
                                $value = $date->format($dateFormat);
                            }
                            $data['dateFull'] = $date->format($fullDateFormat);
                        }
                    } elseif ($setting == 'contentDate') {
                        if (null !== $value) {
                            $date = $value;
                            if ($contentDateFormat) {
                                $value = $date->format($contentDateFormat);
                            }
                            $data['contentDateFull']
                                = $date->format($fullDateFormat);
                        }
                    } elseif ($setting == 'link' && $showFullContentOnSite) {
                        if (!($itemId = $item->getId())) {
                            $itemId = $cnt;
                        }
                        $value = $this->urlHelper->fromRoute(
                            'feed-content-page',
                            ['page' => $id],
                            [
                                'query' => [
                                    'element' => $itemId,
                                    'lng' => $this->getTranslatorLocale(),
                                ],
                                'normalize_path' => false,
                            ]
                        );
                    } elseif ($setting == 'id') {
                        if (!$value) {
                            $value = $cnt;
                        }
                    } elseif (is_string($value)) {
                        $value = strip_tags($value);
                        if (filter_var($value, FILTER_VALIDATE_URL)) {
                            $host = parse_url($this->serverUrl->getHost(), PHP_URL_HOST);
                            $linkHost = parse_url($value, PHP_URL_HOST);
                            $data['isExternal'] = $linkHost && $linkHost !== $host;
                        }
                    }
                    if ($value) {
                        $data[$setting] = $value;
                    }
                }
            }
            if ($xcalContent && $allowXcal) {
                $xpathItem = $xpath->query('//item')->item($cnt);
                foreach ($xcalContent as $setting) {
                    $item = $xpath
                        ->query('.//*[local-name()="' . $setting . '"]', $xpathItem)
                        ->item(0);

                    if (!is_object($item)) {
                        continue;
                    }
                    $xcal = $item->nodeValue;
                    if (!empty($xcal)) {
                        if ($setting === 'featured') {
                            if (!empty($imgLink = $this->extractImage($xcal))) {
                                if ($localFile = $this->checkLocalFile($imgLink)) {
                                    $imgLink = $localFile;
                                } elseif ($id) {
                                    $allowedImages[] = $imgLink;
                                    $imgLink = $this->proxifyImageUrl($imgLink, $id);
                                }

                                $data['xcal']['featured'] = $imgLink;
                                if (
                                    !isset($elements['image'])
                                    || $elements['image'] != 0
                                ) {
                                    $data['image']['url'] = $imgLink;
                                }
                            }
                        } else {
                            $data['xcal'][$setting] = htmlspecialchars($xcal);
                        }
                    }
                }
            }
            // Format start/end date and time for xcal events
            if (isset($data['xcal']['dtstart']) && isset($data['xcal']['dtend'])) {
                $dateStart = new \DateTime($data['xcal']['dtstart']);
                $dateEnd = new \DateTime($data['xcal']['dtend']);
                if (preg_match($timeRegex, $data['xcal']['dtstart']) === 1) {
                    $data['xcal']['startTime'] = $dateStart->format('H:i');
                }
                if (preg_match($timeRegex, $data['xcal']['dtend']) === 1) {
                    $data['xcal']['endTime'] = $dateEnd->format('H:i');
                }
                $data['xcal']['startDate'] = $dateStart->format($fullDateFormat);
                $data['xcal']['endDate'] = $dateEnd->format($fullDateFormat);
                $data['xcal']['singleDay']
                    = $data['xcal']['startDate'] === $data['xcal']['endDate'];
            }

            // Make sure that we have something to display
            if (
                trim($data['title'] ?? '') === ''
                && trim($data['text'] ?? '') === ''
                && empty($data['image'])
            ) {
                continue;
            }
            $this->populateIcon($data, $config);
            $items[] = $data;
            $cnt++;
            if ($itemsCnt !== null && $cnt == $itemsCnt) {
                break;
            }
        }

        if ($xpath) {
            if ($xpathItem = $xpath->query('//item/content:encoded')->item(0)) {
                $contentSearch = isset($config->htmlContentSearch)
                    ? $config->htmlContentSearch->toArray() : [];

                $contentReplace = isset($config->htmlContentReplace)
                    ? $config->htmlContentReplace->toArray() : [];

                $searchReplace = array_combine($contentSearch, $contentReplace);

                $cnt = 0;
                foreach ($items as &$item) {
                    foreach ($xpathContent as $setting => $xpathElement) {
                        $content = $xpath->query($xpathElement, $xpathItem)->item($cnt++)?->nodeValue;

                        $content = $this->processItemContent(
                            $content ?: '',
                            $searchReplace,
                            $cleanContent,
                            $id ?? '',
                            $allowedImages
                        );

                        $item[$setting] = $content;
                    }
                }
            }
        }

        $feedXml = $channel->saveXml();
        return compact(
            'feedXml',
            'items',
            'config',
            'modal',
            'contentPage',
            'allowedImages',
            'contentNavigation',
            'nextArticles',
            'additionalHtml',
            'titleTruncateSize',
            'titlePosition'
        );
    }

    /**
     * Set up custom extensions
     *
     * @param ContainerInterface $container Service container
     *
     * @return void
     */
    public function registerExtensions(ContainerInterface $container)
    {
        $manager = new \Laminas\Feed\Reader\ExtensionPluginManager($container);
        $manager->setInvokableClass(
            'DublinCore\Entry',
            \Finna\Feed\Reader\Extension\DublinCore\Entry::class
        );
        Reader::setExtensionManager($manager);
        Reader::registerExtension('DublinCore');
    }

    /**
     * Process item content
     *
     * @param string $content       Content as string
     * @param array  $searchReplace Search and replacement values
     * @param bool   $cleanContent  Whether to run the content through cleanHtml
     * @param string $feedId        Feed ID
     * @param array  $allowedImages Allowed images
     *
     * @return string
     */
    protected function processItemContent(
        string $content,
        array $searchReplace,
        bool $cleanContent,
        string $feedId,
        array &$allowedImages
    ): string {
        if (!$content) {
            return $content;
        }
        // Remove width & height declarations from style
        // attributes in div & p elements
        $dom = new \DOMDocument();
        $saveErrors = libxml_use_internal_errors(true);
        // See https://stackoverflow.com/a/8218649 for more information on mb_encode_numericentity below
        $dom->loadHTML(mb_encode_numericentity($content, [0x80, 0x10FFFF, 0, ~0], 'UTF-8'));
        $domx = new \DOMXPath($dom);

        // Process style attributes:
        $elements = $domx->query('//div[@style]|//p[@style]');
        foreach ($elements as $el) {
            $styleProperties = [];
            $styleAttr = $el->getAttribute('style');
            $properties = explode(';', $styleAttr);
            foreach ($properties as $prop) {
                [$field] = explode(':', $prop);
                if (
                    stristr($field, 'width') === false
                    && stristr($field, 'height') === false
                    && stristr($field, 'margin') === false
                ) {
                    $styleProperties[] = $prop;
                }
            }
            $el->removeAttribute('style');
            $el->setAttribute(
                'style',
                implode(';', $styleProperties)
            );
        }

        // Proxify images:
        if ($feedId) {
            foreach ($domx->query('//img') as $el) {
                $srcAttr = $el->getAttribute('src');
                $allowedImages[] = $srcAttr;
                $el->setAttribute(
                    'src',
                    $this->proxifyImageUrl($srcAttr, $feedId)
                );
            }
        }

        $content = $dom->saveHTML();
        libxml_use_internal_errors($saveErrors);

        // Process feed specific search-replace regexes
        foreach ($searchReplace as $search => $replace) {
            $pattern = "/$search/";
            $replaced = preg_replace($pattern, $replace, $content);
            if ($replaced !== null) {
                $content = $replaced;
            }
        }

        // Clean up the HTML:
        if ($cleanContent) {
            $content = ($this->cleanHtml)($content);
        }
        return $content;
    }

    /**
     * Proxify an image url for loading via the FeedContent controller
     *
     * @param string $url    Image URL
     * @param string $feedId Feed identifier
     *
     * @return string
     */
    protected function proxifyImageUrl(string $url, string $feedId): string
    {
        // Ensure that we don't proxify an empty or already proxified URL or a
        // relative url:
        if (!$url || !parse_url($url, PHP_URL_HOST)) {
            return $url;
        }
        $check = $this->urlHelper->fromRoute('feed-image', ['page' => '']);
        if (strncasecmp($url, $check, strlen($check)) === 0) {
            return $url;
        }

        return $this->urlHelper->fromRoute(
            'feed-image',
            ['page' => $feedId],
            [
                'query' => [
                    'image' => $url,
                ],
            ]
        );
    }

    /**
     * Populate icon data for feed slide.
     *
     * @param array                 $data   Data for slide
     * @param \VuFind\Config\Config $config Config for feed
     *
     * @return void
     */
    protected function populateIcon(
        array &$data,
        \VuFind\Config\Config $config
    ): void {
        if (
            empty($config->showIcons)
            || empty($data['link'])
            || empty($this->mainConfig->Content->feedHostToNameMappings)
        ) {
            return;
        }

        // Parse the link to know the origin
        $comparisons
            = $this->mainConfig->Content->feedHostToNameMappings->toArray();
        $parsed = parse_url($data['link']);
        if (!empty($parsed['host'])) {
            foreach ($comparisons as $comparison) {
                [$from, $to] = explode(':', $comparison, 2);
                if ($parsed['host'] === $from) {
                    $data['icon'] = ['name' => $to];
                    return;
                }
            }
        }
    }
}
