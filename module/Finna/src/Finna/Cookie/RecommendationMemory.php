<?php
/**
 * Recommendation memory.
 *
 * PHP version 7
 *
 * Copyright (C) The National Library of Finland 2020.
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
 * @package  Cookie
 * @author   Aleksi Peebles <aleksi.peebles@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
namespace Finna\Cookie;

use Laminas\Stdlib\Parameters;
use VuFind\Cookie\CookieManager;

/**
 * Recommendation memory.
 *
 * @category VuFind
 * @package  Cookie
 * @author   Aleksi Peebles <aleksi.peebles@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class RecommendationMemory
{
    /**
     * Name of the query string parameter containing the recommendation memory
     * cookie name.
     *
     * @var string
     */
    public const PARAMETER_NAME = 'rmKey';

    /**
     * Key for the source recommendation module.
     *
     * This is different from the (target) recommendation module parameter used
     * in deferred AJAX requests.
     *
     * @var string
     */
    public const SOURCE_MODULE = 'srcMod';

    /**
     * Key for the recommended search term or query.
     *
     * @var string
     */
    public const RECOMMENDATION = 'rec';

    /**
     * Key for the original search term or query.
     *
     * @var string
     */
    public const ORIGINAL = 'orig';

    /**
     * Key for the recommendation type.
     *
     * @var string
     */
    public const RECOMMENDATION_TYPE = 'recType';

    /**
     * Matomo custom variable name for followed recommendations.
     */
    public const CUSTOM_VARIABLE_NAME = 'RecommendationFollowed';

    /**
     * Cookie manager.
     *
     * @var CookieManager
     */
    protected $cookieManager;

    /**
     * RecommendationMemory constructor.
     *
     * @param CookieManager $cookieManager Cookie manager
     */
    public function __construct(CookieManager $cookieManager)
    {
        $this->cookieManager = $cookieManager;
    }

    /**
     * Returns a Base64 encoded string containing the provided recommendation data.
     *
     * @param string $srcMod  Source recommendation module.
     * @param string $rec     Recommended search term or query.
     * @param string $orig    Original search term or query (optional).
     * @param string $recType Recommendation type (optional).
     *
     * @return string
     */
    public function getDataString(
        string $srcMod, string $rec, string $orig = '', string $recType = ''
    ): string {
        $data = [
            self::SOURCE_MODULE => $srcMod,
            self::RECOMMENDATION => $rec,
            self::ORIGINAL => $orig,
            self::RECOMMENDATION_TYPE => $recType
        ];
        return base64_encode(json_encode($data));
    }

    /**
     * Returns data about a recommendation followed by the user.
     *
     * @param Parameters $request Parameter object representing user request.
     * @param boolean    $clear   Whether to clear the data cookie (optional,
     *                            defaults to true).
     *
     * @return array|null Recommendation data or null if there is none.
     */
    public function get(Parameters $request, $clear = true): ?array
    {
        $rmValue = $request->get(self::PARAMETER_NAME);
        if (isset($rmValue)) {
            $dataString = $this->cookieManager->get($rmValue);
            if (isset($dataString)) {
                $data = json_decode(base64_decode($dataString), true);
                if ($clear) {
                    $this->cookieManager->clear($rmValue);
                }
                return $data;
            }
        }
        return null;
    }
}
