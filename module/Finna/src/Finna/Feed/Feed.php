<?php
/**
 * Feed service
 *
 * PHP version 7
 *
 * Copyright (C) The National Library of Finland 2016-2019.
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

use Laminas\Config\Config;
use Laminas\Feed\Reader\Reader;
use Laminas\Mvc\Controller\Plugin\Url;
use VuFind\Cache\Manager as CacheManager;
use VuFindTheme\View\Helper\ImageLink;

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
class Feed implements \VuFind\I18n\Translator\TranslatorAwareInterface,
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
     * Image link helper
     *
     * @var ImageLink
     */
    protected $imageLinkHelper;

    /**
     * Constructor.
     *
     * @param Config       $config     Main configuration
     * @param Config       $feedConfig Feed configuration
     * @param CacheManager $cm         Cache manager
     * @param Url          $url        URL helper
     * @param ImageLink    $imageLink  Image link helper
     */
    public function __construct(
        Config $config, Config $feedConfig, CacheManager $cm, Url $url,
        ImageLink $imageLink
    ) {
        $this->mainConfig = $config;
        $this->feedConfig = $feedConfig;
        $this->cacheManager = $cm;
        $this->urlHelper = $url;
        $this->imageLinkHelper = $imageLink;
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
     * @return boolean|array
     */
    public function getFeedConfig($id)
    {
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
            $imgLink = $this->imageLinkHelper->__invoke($file);
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
     * Return feed content from a URL.
     * See readFeed for a description of the return object.
     *
     * @param string $id      Feed id
     * @param string $url     Feed URL
     * @param array  $config  Configuration
     * @param string $viewUrl View URL
     *
     * @return mixed null|array
     */
    public function readFeedFromUrl($id, $url, $config, $viewUrl)
    {
        $config = new \Laminas\Config\Config($config);
        return $this->processReadFeed($config, $viewUrl, $id);
    }

    /**
     * Utility function for processing a feed (see readFeed, readFeedFromUrl).
     *
     * @param array  $feedConfig Configuration
     * @param string $viewUrl    View URL
     * @param string $id         Feed id (needed when the feed content is shown on a
     * content page or in a modal)
     *
     * @return mixed null|array
     */
    protected function processReadFeed($feedConfig, $viewUrl, $id = null)
    {
        $config = $feedConfig['result'];
        $url = trim($feedConfig['url']);

        $type = $config->type;

        $cacheKey = (array)$feedConfig;
        $cacheKey['language'] = $this->translator->getLocale();

        $channel = null;

        // Check for cached version
        $cacheDir
            = $this->cacheManager->getCache('feed')->getOptions()->getCacheDir();

        $localFile = "$cacheDir/" . md5(var_export($cacheKey, true)) . '.xml';
        $maxAge = isset($this->mainConfig->Content->feedcachetime)
            && '' !== $this->mainConfig->Content->feedcachetime
            ? $this->mainConfig->Content->feedcachetime : 10;

        $httpClient = $this->httpService->createClient();
        $httpClient->setOptions(['timeout' => 30]);
        Reader::setHttpClient($httpClient);

        if ($maxAge) {
            if (is_readable($localFile)
                && time() - filemtime($localFile) < $maxAge * 60
            ) {
                $channel = Reader::importFile($localFile);
            }
        }

        if (!$channel) {
            // No cache available, read from source.
            if (strstr($url, 'finna-test.fi')) {
                // Refuse to load feeds from finna-test.fi
                $feedStr = <<<EOT
<?xml version="1.0" encoding="UTF-8"?>
<rss xmlns:atom="http://www.w3.org/2005/Atom" version="2.0">
  <channel>
    <atom:link href="" rel="self" type="application/rss+xml"/>
    <link></link>
    <title><![CDATA[<!-- Feed URL blacklisted -->]]></title>
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
                if (!is_file($url)) {
                    $this->logError("File $url could not be found");
                }
                try {
                    $channel = Reader::importFile($url);
                } catch (\Exception $e) {
                    $this->logError(
                        "Error importing feed from file $url: " . $e->getMessage()
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
    <title><![CDATA[<!-- Feed could not be loaded -->]]></title>
    <description></description>
  </channel>
</rss>
EOT;
                $channel = Reader::importString($feedStr);
            }

            file_put_contents($localFile, $channel->saveXml());
        }

        if (!$channel) {
            return false;
        }

        return $this->parseFeed($channel, $config, $id);
    }

    /**
     * Function to parse feed with config
     *
     * @param string      $channel Feed channel
     * @param array       $config  Feed config
     * @param string|null $id      Feed ID (required when feed content is
     *                             displayed on content-page or modal)
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
        $dateFormat = isset($config->dateFormat) ? $config->dateFormat : 'j.n.';
        $contentDateFormat = isset($config->contentDateFormat)
            ? $config->contentDateFormat : 'j.n.Y';
        $fullDateFormat = isset($config->fullDateFormat)
            ? $config->fullDateFormat : 'j.n.Y';

        $itemsCnt = isset($config->items) ? $config->items : null;
        $elements = isset($config->content) ? $config->content : [];
        $allowXcal = $elements['xcal'] ?? true;
        $timeRegex = '/^(.*?)([0-9]|0[0-9]|1[0-9]|2[0-3]):[0-5][0-9]$/';

        $content = [
            'id' => 'getId',
            'title' => 'getTitle',
            'text' => 'getContent',
            'image' => 'getEnclosure',
            'link' => 'getLink',
            'date' => 'getDateCreated',
            'contentDate' => 'getDateCreated'
        ];

        $xpathContent = [
            'html' => '//item/content:encoded'
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
            'categories'
        ];

        $items = [];
        $cnt = 0;
        $xpath = null;

        foreach ($channel as $item) {
            if (!$xpath) {
                $xpath = $item->getXpath();
            }
            $data = [];
            $data['modal'] = $modal;
            foreach ($content as $setting => $method) {
                if (!isset($elements[$setting])
                    || $elements[$setting] != 0
                ) {
                    $value = $item->{$method}();
                    if (is_object($value)) {
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
                            }
                        }
                    } elseif ($setting == 'date') {
                        if (isset($value['date'])) {
                            $date = new \DateTime(($value['date']));
                            if ($dateFormat) {
                                $value = $date->format($dateFormat);
                            }
                            $data['dateFull'] = $date->format($fullDateFormat);
                        }
                    } elseif ($setting == 'contentDate') {
                        if (isset($value['date'])) {
                            $date = new \DateTime(($value['date']));
                            if ($contentDateFormat) {
                                $value = $date->format($contentDateFormat);
                            }
                            $data['contentDateFull']
                                = $date->format($fullDateFormat);
                        }
                    } elseif ($setting == 'link' && $showFullContentOnSite) {
                        if (!$itemId = $item->getId()) {
                            $itemId = $cnt;
                        }
                        $link = $this->urlHelper->fromRoute(
                            'feed-content-page',
                            ['page' => $id, 'element' => urlencode($itemId)]
                        );
                        $value = $link;
                    } elseif ($setting == 'id') {
                        if (!$value) {
                            $value = $cnt;
                        }
                    } else {
                        if (is_string($value)) {
                            $value = strip_tags($value);
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
                    $xcal = $xpath
                        ->query('.//*[local-name()="' . $setting . '"]', $xpathItem)
                        ->item(0)->nodeValue;
                    if (!empty($xcal)) {
                        if ($setting === 'featured') {
                            if (!empty($imgLink = $this->extractImage($xcal))) {
                                if ($localFile = $this->checkLocalFile($imgLink)) {
                                    $imgLink = $localFile;
                                }
                                $data['xcal']['featured'] = $imgLink;
                                if ($elements['image'] != 0
                                    || !isset($elements['image'])
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
            //Format start/end date and time for xcal events
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
            }

            // Make sure that we have something to display
            $accept = $data['title'] && trim($data['title']) != ''
                || $data['text'] && trim($data['text']) != ''
                || $data['image']
            ;
            if (!$accept) {
                continue;
            }

            $items[] = $data;
            $cnt++;
            if ($itemsCnt !== null && $cnt == $itemsCnt) {
                break;
            }
        }

        if ($xpath && !empty($xpathContent)) {
            if ($xpathItem = $xpath->query('//item/content:encoded')->item(0)) {
                $contentSearch = isset($config->htmlContentSearch)
                    ? $config->htmlContentSearch->toArray() : [];

                $contentReplace = isset($config->htmlContentReplace)
                    ? $config->htmlContentReplace->toArray() : [];

                $searchReplace = array_combine($contentSearch, $contentReplace);

                $cnt = 0;
                foreach ($items as &$item) {
                    foreach ($xpathContent as $setting => $xpathElement) {
                        $content = $xpath->query($xpathElement, $xpathItem)
                            ->item($cnt++)->nodeValue;

                        // Remove width & height declarations from style
                        // attributes in div & p elements
                        $dom = new \DOMDocument();
                        libxml_use_internal_errors(true);
                        $dom->loadHTML(
                            mb_convert_encoding($content, 'HTML-ENTITIES', 'UTF-8')
                        );
                        $domx = new \DOMXPath($dom);
                        $elements = $domx->query('//div[@style]|//p[@style]');

                        foreach ($elements as $el) {
                            $styleProperties = [];
                            $styleAttr = $el->getAttribute('style');
                            $properties = explode(';', $styleAttr);
                            foreach ($properties as $prop) {
                                list($field, $val) = explode(':', $prop);
                                if (stristr($field, 'width') === false
                                    && stristr($field, 'height') === false
                                    && stristr($field, 'margin') === false
                                ) {
                                    $styleProperties[] = $prop;
                                }
                            }
                            $el->removeAttribute("style");
                            $el->setAttribute(
                                'style', implode(';', $styleProperties)
                            );
                        }
                        $content = $dom->saveHTML();

                        // Process feed specific search-replace regexes
                        foreach ($searchReplace as $search => $replace) {
                            $pattern = "/$search/";
                            $replaced = preg_replace($pattern, $replace, $content);
                            if ($replaced !== null) {
                                $content = $replaced;
                            }
                        }
                        $item[$setting] = $content;
                    }
                }
            }
        }
        return compact('channel', 'items', 'config', 'modal', 'contentPage');
    }
}
