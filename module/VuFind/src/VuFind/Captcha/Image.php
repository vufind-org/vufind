<?php

/**
 * Image CAPTCHA.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2020.
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
 * @package  CAPTCHA
 * @author   Mario Trojan <mario.trojan@uni-tuebingen.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */

namespace VuFind\Captcha;

/**
 * Image CAPTCHA.
 *
 * @category VuFind
 * @package  CAPTCHA
 * @author   Mario Trojan <mario.trojan@uni-tuebingen.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class Image extends LaminasBase
{
    /**
     * Base path of cache where image will be stored, e.g. /vufind/cache/
     *
     * @var string
     */
    protected $cacheBasePath;

    /**
     * Constructor
     *
     * @param \Laminas\Captcha\AbstractWord $captcha       Laminas CAPTCHA object
     * @param string                        $cacheBasePath e.g. /vufind/cache/
     */
    public function __construct(
        \Laminas\Captcha\AbstractWord $captcha,
        string $cacheBasePath
    ) {
        $this->cacheBasePath = $cacheBasePath;
        parent::__construct($captcha);
    }

    /**
     * Getter for template
     *
     * @return string
     */
    public function getCacheBasePath(): string
    {
        return $this->cacheBasePath;
    }
}
