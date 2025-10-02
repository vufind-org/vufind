<?php

/**
 * Finna personal information support trait.
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2021.
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
 * @package  Controller
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:controllers Wiki
 */

namespace Finna\Controller;

/**
 * Finna personal information support trait.
 *
 * @category VuFind
 * @package  Controller
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:controllers Wiki
 */
trait FinnaPersonalInformationSupportTrait
{
    /**
     * Sort request (hold, ILL, storage retrieval) list
     *
     * @param array  $recordList Requests
     * @param string $sort       Sort order
     *
     * @return array
     */
    protected function sortRequests($recordList, $sort)
    {
        // Field can contain underscores, so split from the last one:
        $field = $sort;
        $order = 'asc';
        if (false !== $p = strrpos($sort, '_')) {
            $field = substr($sort, 0, $p);
            $order = substr($sort, $p + 1);
        }
        $date = $this->serviceLocator->get(\VuFind\Date\Converter::class);
        $sorter = $this->serviceLocator->get(\VuFind\I18n\Sorter::class);
        $sortFunc = function ($a, $b) use ($field, $order, $date, $sorter) {
            $aDetail = $a->getExtraDetail('ils_details')[$field] ?? '';
            $bDetail = $b->getExtraDetail('ils_details')[$field] ?? '';
            if ($field === 'title') {
                return $sorter->compare(
                    $aDetail,
                    $bDetail
                );
            }
            $aDate = $aDetail ? $date->convertFromDisplayDate('U', $aDetail) : 0;
            $bDate = $bDetail ? $date->convertFromDisplayDate('U', $bDetail) : 0;
            if ($aDetail !== $bDetail) {
                return $order === 'asc' ? $aDate - $bDate : $bDate - $aDate;
            }
            $aAvail = $a->getExtraDetail('ils_details')['available'] ?? '';
            $bAvail = $b->getExtraDetail('ils_details')['available'] ?? '';
            if ($aAvail !== $bAvail) {
                return (int)$bAvail - (int)$aAvail;
            }
            return $sorter->compare(
                $a->getExtraDetail('ils_details')['title'] ?? $a->getFullTitle() ?? '',
                $b->getExtraDetail('ils_details')['title'] ?? $b->getFullTitle() ?? ''
            );
        };

        usort($recordList, $sortFunc);
        return $recordList;
    }

    /**
     * Sort available requests to beginning of the record list
     *
     * @param array $recordList List to order
     *
     * @return array
     */
    protected function sortRequestsByAvailability($recordList)
    {
        if ($recordList === null) {
            return [];
        }

        $date = $this->serviceLocator->get(\VuFind\Date\Converter::class);
        $getDetailDiff = function (array $aDetails, array $bDetails, string $field, bool $isDate) use ($date): int {
            $aDetail = $aDetails[$field] ?? false;
            $bDetail = $bDetails[$field] ?? false;

            if ($isDate) {
                $aDetail = $aDetail ? $date->convertFromDisplayDate('U', $aDetail) : 0;
                $bDetail = $bDetail ? $date->convertFromDisplayDate('U', $bDetail) : 0;
            }
            return $aDetail <=> $bDetail;
        };

        $sortFunc = function ($a, $b) use ($getDetailDiff) {
            $aDetails = $a->getExtraDetail('ils_details');
            $bDetails = $b->getExtraDetail('ils_details');

            if ($diff = $getDetailDiff($aDetails, $bDetails, 'available', false)) {
                return -$diff;
            }
            if ($diff = $getDetailDiff($aDetails, $bDetails, 'in_transit', false)) {
                return -$diff;
            }
            if ($diff = $getDetailDiff($aDetails, $bDetails, 'last_pickup_date', true)) {
                return $diff;
            }
            return $a->getExtraDetail('__key') - $b->getExtraDetail('__key');
        };

        // Add keys to maintain original order for items in a group:
        foreach ($recordList as $key => $record) {
            $record->setExtraDetail('__key', $key);
        }

        usort($recordList, $sortFunc);

        return $recordList;
    }

    /**
     * Get account blocks if supported by the ILS
     *
     * @param array $patron Patron
     *
     * @return array
     */
    protected function getAccountBlocks($patron)
    {
        $catalog = $this->getILS();
        if (
            $catalog->checkCapability('getAccountBlocks', compact('patron'))
            && $blocks = $catalog->getAccountBlocks($patron)
        ) {
            return $blocks;
        }
        return [];
    }
}
