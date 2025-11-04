<?php

/**
 * User agent view helper.
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2018.
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
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */

namespace Finna\View\Helper\Root;

/**
 * User agent view helper
 *
 * @category VuFind
 * @package  View_Helpers
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class UserAgent extends \Laminas\View\Helper\AbstractHelper
{
    /**
     * Request object
     *
     * @var \Laminas\Http\PhpEnvironment\Request
     */
    protected $request;

    /**
     * Cached result for isBot
     *
     * @var bool|null
     */
    protected $isBot = null;

    /**
     * Constructor
     *
     * @param Laminas\Http\PhpEnvironment\Request $request Request
     */
    public function __construct(\Laminas\Http\PhpEnvironment\Request $request)
    {
        $this->request = $request;
    }

    /**
     * Check if the request comes from a bot
     *
     * @return bool
     */
    public function isBot()
    {
        if (null === $this->isBot) {
            $headers = $this->request->getHeaders();
            if (!$headers->has('User-Agent')) {
                $this->isBot = false;
            } else {
                $agent = $headers->get('User-Agent')->toString();
                $crawlerDetect = new \Jaybizzle\CrawlerDetect\CrawlerDetect();
                $this->isBot = $crawlerDetect->isCrawler($agent);
            }
        }
        return $this->isBot;
    }
}
