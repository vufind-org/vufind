<?php

/**
 * ReCaptcha CAPTCHA.
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

use Laminas\Mvc\Controller\Plugin\Params;

/**
 * ReCaptcha CAPTCHA.
 *
 * @category VuFind
 * @package  CAPTCHA
 * @author   Mario Trojan <mario.trojan@uni-tuebingen.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class PoW extends AbstractBase
{
    /**
     * Constructor
     *
     * @param string $hashAlgo hash algorithm (default sha256)
     * @param int $difficulty number of zeroes needed for proof (default 5)
     */
    public function __construct(
        string $hashAlgo,
        int $difficulty,
    ) {
        $this->hashAlgo = $hashAlgo;
        $this->difficulty = $difficulty;
    }

    /**
     * Get list of URLs with JS dependencies to load for the active CAPTCHA type.
     *
     * @return array
     */
    public function getJsIncludes(): array
    {
        return ['captcha-pow.js'];
    }

    /**
     * Pull the captcha field from controller params and check them for accuracy
     *
     * @param Params $params Controller params
     *
     * @return bool
     */
    public function verify(Params $params): bool
    {
        error_log($params->fromPost('pow-captcha-challenge'));
        error_log($params->fromPost('pow-captcha-nonce'));

        // @TODO: compare to Session challenge

        return false;
    }

    public function getChallenge() {
        // @TODO: random challenge
        // @TODO: story in session for verify
        return $this->challenge ?? "WOW VERY CHALLENGE MUCH FIND";
    }

    public function getDifficulty() {
        return $this->difficulty;
    }

    public function getHashAlgo() {
        return $this->hashAlgo;
    }

    public function getStart() {
        return random_int(0, 1000);
    }
}
