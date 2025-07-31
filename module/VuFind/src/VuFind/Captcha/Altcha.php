<?php

/**
 * Altcha proof-of-work CAPTCHA.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2025.
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
 * @author   Chris Hallberg <challber@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */

namespace VuFind\Captcha;

use AltchaOrg\Altcha\ChallengeOptions;
use AltchaOrg\Altcha\Hasher\Algorithm;
use Laminas\Mvc\Controller\Plugin\Params;

/**
 * Altcha proof-of-work CAPTCHA.
 *
 * @category VuFind
 * @package  CAPTCHA
 * @author   Chris Hallberg <challber@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class Altcha extends AbstractBase
{
    /**
     * Constructor
     *
     * @param AltchaOrg\Altcha\Altcha $altcha Required HMAC key for challenge calculation and solution verification.
     * @param Algorithm               $algorithm  Hashing algorithm to use (`SHA-1`, `SHA-256`, `SHA-512`, default: `SHA-256`).
     * @param int                     $maxNumber  Maximum number for the random number generator (default: 1,000,000)
     * @param null|\DateTimeInterface $expires    Optional expiration time for the challenge.
     * @param ChallengeParams         $params     Optional URL-encoded query parameters.
     * @param int<1, max>             $saltLength Length of the random salt (default: 12 bytes).
     */
    public function __construct(
        protected AltchaOrg\Altcha\Altcha $altcha,
        // Options for creation of a new challenge
        protected Algorithm $algorithm = Algorithm::SHA256,
        protected ?\DateTimeInterface $expires = null,
        protected array $params = [],
    ) {
    }

    /**
     * Get list of URLs with JS dependencies to load for the active CAPTCHA type.
     *
     * @return array
     */
    public function getJsIncludes(): array
    {
        return ['vendor/altcha.js', 'vendor/altcha-i18n.js'];
    }

    /**
     * Generate challenge
     *
     * @return Challenge
     */
    public function getChallenge()
    {
        $options = new ChallengeOptions(
            algorithm: $this->algorithm,
            maxNumber: $this->maxNumber,
            expires: $this->expires,
            params: $this->params,
            saltLength: $this->saltLength,
        );

        return json_encode($this->altcha->createChallenge($options));
    }

    /**
     * Pull the captcha field from controller params and check them for accuracy
     * We pull from the form in case config changed since challenge was sent
     *
     * @param Params $params Controller params
     *
     * @return bool
     */
    public function verify(Params $params): bool
    {
        $encoded = $params->fromPost('altcha', null);
        $json = base64_decode($encoded);
        $payload = json_decode($json, true);

        return $this->altcha->verifySolution($payload, checkExpires: true);
    }
}
