<?php
/**
 * Voyager ILS Driver
 *
 * PHP version 5
 *
 * Copyright (C) The National Library of Finland 2015.
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
 * @category VuFind2
 * @package  ILS_Drivers
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:building_an_ils_driver Wiki
 */
namespace Finna\ILS\Driver;

/**
 * Voyager Restful ILS Driver
 *
 * @category VuFind2
 * @package  ILS_Drivers
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:building_an_ils_driver Wiki
 */
class VoyagerRestful extends \VuFind\ILS\Driver\VoyagerRestful
{
    /**
     * Get Patron Profile
     *
     * This is responsible for retrieving the profile for a specific patron.
     *
     * @param array $patron The patron array
     *
     * @throws ILSException
     * @return array        Array of the patron's profile data on success.
     */
    public function getMyProfile($patron)
    {
        $profile = parent::getMyProfile($patron);
        $profile['blocks'] = $this->checkAccountBlocks($patron['id']);
        return $profile;
    }

    /**
     * Check Account Blocks
     *
     * Checks if a user has any blocks against their account which may prevent them
     * performing certain operations
     *
     * @param string $patronId A Patron ID
     *
     * @return mixed           A boolean false if no blocks are in place and an array
     * of block reasons if blocks are in place
     */
    protected function checkAccountBlocks($patronId)
    {
        $cacheId = "blocks_$patronId";
        $blockReason = $this->getCachedData($cacheId);
        if (null === $blockReason) {
            // Build Hierarchy
            $hierarchy = [
                "patron" =>  $patronId,
                "patronStatus" => "blocks"
            ];

            // Add Required Params
            $params = [
                "patron_homedb" => $this->ws_patronHomeUbId,
                "view" => "full"
            ];

            $blockReason = [];

            $blocks = $this->makeRequest($hierarchy, $params);
            if ($blocks) {
                $borrowingBlocks = $blocks->xpath(
                    "//blocks/institution[@id='LOCAL']/borrowingBlock"
                );
                if (count($borrowingBlocks)) {
                    $blockReason[] = $this->translate('Borrowing Block Message');
                }
                foreach ($borrowingBlocks as $borrowBlock) {
                    $code = (int)$borrowBlock->blockCode;
                    $reason = "Borrowing Block Voyager Reason $code";
                    $params = [];
                    if ($code == 19) {
                        $params = [
                            '%%blockCount%%' => $borrowBlock->blockCount,
                            '%%blockLimit%%' => $borrowBlock->blockLimit
                        ];
                    }
                    $translated = $this->translate($reason, $params);
                    if ($reason !== $translated) {
                        $reason = $translated;
                        $blockReason[] = $reason;
                    }
                }
            }
            $this->putCachedData($cacheId, $blockReason);
        }
        return empty($blockReason) ? false : $blockReason;
    }
}
