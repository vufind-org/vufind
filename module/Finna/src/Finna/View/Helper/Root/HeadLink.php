<?php
/**
 * Head link view helper (extended for VuFind's theme system)
 *
 * PHP version 5
 *
 * Copyright (C) Villanova University 2010.
 * Copyright (C) The National Library of Finland 2016.
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
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:developer_manual Wiki
 */
namespace Finna\View\Helper\Root;
use Finna\Cache\Manager;
use VuFindTheme\ThemeInfo;
use Zend\Http\Request;

/**
 * Head link view helper (extended for VuFind's theme system)
 *
 * @category VuFind
 * @package  View_Helpers
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:developer_manual Wiki
 */
class HeadLink extends \VuFindTheme\View\Helper\HeadLink
{
    /**
     * Cache Manager
     *
     * @var Manager
     */
    protected $cacheManager;

    /**
     * Request
     *
     * @var Request
     */
    protected $request;

    /**
     * Constructor
     *
     * @param ThemeInfo $themeInfo    Theme information service
     * @param Request   $request      Request
     * @param Manager   $cacheManager Cache manager
     */
    public function __construct(ThemeInfo $themeInfo, Request $request,
        $cacheManager
    ) {
        parent::__construct($themeInfo);
        $this->request = $request;
        $this->cacheManager = $cacheManager;
    }

    /**
     * Create HTML link element from data item
     *
     * @param \stdClass $item data item
     *
     * @return string
     */
    public function itemToString(\stdClass $item)
    {
        // Normalize href to account for themes, then call the parent class:
        $relPath = 'css/' . $item->href;
        $details = $this->themeInfo
            ->findContainingTheme($relPath, ThemeInfo::RETURN_ALL_DETAILS);

        if (!empty($details)) {
            if (substr($details['path'], -4) == '.css') {
                // Check for IE < 10 and if we need to split the css file
                if ($result = $this->splitCssForIe($item, $details)) {
                    return $result;
                }
            }

            $urlHelper = $this->getView()->plugin('url');

            $url = $urlHelper('home') . "themes/{$details['theme']}/" . $relPath;
            $url .= strstr($url, '?') ? '&_=' : '?_=';
            $url .= filemtime($details['path']);
            $item->href = $url;
        }

        return parent::itemToString($item);
    }

    /**
     * Try to acquire a lock on a lock file
     *
     * @param string $lockfile Lock file
     *
     * @return resource Lock file handle
     */
    protected function acquireLock($lockfile)
    {
        $handle = fopen($lockfile, 'c+');
        if (!is_resource($handle)) {
            return null;
        }
        if (!flock($handle, LOCK_EX)) {
            fclose($handle);
            return null;
        }
        return $handle;
    }

    /**
     * Release a lock on a lock file
     *
     * @param resource $handle Lock file handle
     *
     * @return void
     */
    protected function releaseLock($handle)
    {
        if ($handle) {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }

    /**
     * Check if request came from IE < 10 and too large CSS (> 4095 selectors).
     * Split if necessary.
     *
     * @param \stdClass $item    data item
     * @param array     $details Theme item details
     *
     * @return string
     */
    protected function splitCssForIe(\stdClass $item, $details)
    {
        $file = $details['path'];
        // Return right away if the file doesn't exist or is too small to
        // be a problem, hopefully
        if (!file_exists($file) || filesize($file) < 65535) {
            return '';
        }
        $agent = $this->request->getHeader('User-Agent')->toString();
        if (strstr($agent, 'MSIE 9.0') || strstr($agent, 'MSIE 8.0')
            || strstr($agent, 'MSIE 7.0')
        ) {
            $theme = $details['theme'];
            $basename = basename($file);
            $fileTime = filemtime($file);
            $cache = $this->cacheManager->getCache('stylesheet')->getOptions()
                ->getCacheDir();
            if (!file_exists("$cache/$theme/{$basename}_part1.css")
                || filemtime("$cache/$theme/{$basename}_part1.css") < $fileTime
            ) {
                // Populate cache
                if (!is_dir("$cache/$theme")) {
                    mkdir("$cache/$theme");
                }
                $handle = $this->acquireLock("$cache/$theme/.lockfile");
                array_map('unlink', glob("$cache/$theme/{$basename}_*") ?: []);
                $css = file_get_contents($file);
                $splitter = new \CssSplitter\Splitter($css);
                $selectorCount = $splitter->countSelectors();
                $partCount = ceil($selectorCount / 4095);
                for ($part = 1; $part <= $partCount; $part++) {
                    file_put_contents(
                        "$cache/$theme/{$basename}_part{$part}.css",
                        $splitter->split(null, $part)
                    );
                }
                $this->releaseLock($handle);
            }
            $result = [];
            $urlHelper = $this->getView()->plugin('url');
            $files = glob("$cache/$theme/{$basename}_part*");
            foreach ($files as $css) {
                $url = $urlHelper('home') . "themes/$theme/css/" . basename($css);
                $url .= strstr($url, '?') ? '&_=' : '?_=';
                $url .= filemtime($css);
                $item->href = $url;
                $result[] = parent::itemToString($item);
            }
            return implode("\n", $result);
        }
        return '';
    }
}
