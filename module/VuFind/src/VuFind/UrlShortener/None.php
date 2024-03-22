<?php

/**
 * No-op URL shortener (default version, does nothing).
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2019.
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
 * @package  UrlShortener
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */

namespace VuFind\UrlShortener;

/**
 * No-op URL shortener (default version, does nothing).
 *
 * @category VuFind
 * @package  UrlShortener
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class None implements UrlShortenerInterface
{
    /**
     * Dummy to return original URL version.
     *
     * @param string $url URL
     *
     * @return string
     */
    public function shorten($url)
    {
        return $url;
    }

    /**
     * Dummy implementation. Resolving is not necessary because initial URL
     * has not been shortened.
     *
     * @param string $id ID to resolve
     *
     * @return string
     * @throws \Exception because this class is not meant to resolve shortlinks.
     */
    public function resolve($id)
    {
        throw new \Exception('UrlShortener None is unable to resolve shortlinks.');
    }
}
