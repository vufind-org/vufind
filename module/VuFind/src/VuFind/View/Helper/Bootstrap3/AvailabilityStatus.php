<?php

/**
 * Helper class for rendering availability statuses.
 *
 * PHP version 8
 *
 * Copyright (C) Hebis Verbundzentrale 2024.
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
 * @package  View_Helpers
 * @author   Thomas Wagener <wagener@hebis.uni-frankfurt.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */

namespace VuFind\View\Helper\Bootstrap3;

use VuFind\ILS\Logic\AvailabilityStatusInterface;

/**
 * Helper class for rendering availability statuses.
 *
 * @category VuFind
 * @package  View_Helpers
 * @author   Thomas Wagener <wagener@hebis.uni-frankfurt.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class AvailabilityStatus extends \Laminas\View\Helper\AbstractHelper
{
    /**
     * Message cache
     *
     * @var array
     */
    protected array $messageCache = [];

    /**
     * Get html class for availability status.
     *
     * @param AvailabilityStatusInterface $availabilityStatus Availability Status
     *
     * @return string
     */
    public function getClass(AvailabilityStatusInterface $availabilityStatus): string
    {
        if ($availabilityStatus->is(\VuFind\ILS\Logic\AvailabilityStatus::STATUS_UNAVAILABLE)) {
            return 'text-danger';
        }
        if ($availabilityStatus->is(\VuFind\ILS\Logic\AvailabilityStatus::STATUS_AVAILABLE)) {
            return 'text-success';
        }
        return 'text-warning';
    }

    /**
     * Render ajax status.
     *
     * @param AvailabilityStatusInterface $availabilityStatus Availability Status
     *
     * @return string
     */
    public function renderAjax(AvailabilityStatusInterface $availabilityStatus): string
    {
        if (empty($this->messageCache)) {
            $this->messageCache = [
                'available' => $this->getView()->render('ajax/status-available.phtml'),
                'unavailable' => $this->getView()->render('ajax/status-unavailable.phtml'),
                'uncertain' => $this->getView()->render('ajax/status-uncertain.phtml'),
                'unknown' => $this->getView()->render('ajax/status-unknown.phtml'),
            ];
        }

        $availabilityStr = $availabilityStatus->availabilityAsString();
        if ($availabilityStatus->useUnknownMessage()) {
            $key = 'unknown';
        } elseif ('false' === $availabilityStr) {
            $key = 'unavailable';
        } elseif ('uncertain' === $availabilityStr) {
            $key = 'uncertain';
        } else {
            $key = 'available';
        }
        return $this->messageCache[$key] ?? '';
    }
}
