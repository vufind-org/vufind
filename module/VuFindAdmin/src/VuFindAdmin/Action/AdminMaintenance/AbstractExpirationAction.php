<?php

/**
 * Abtract base class for expiration actions.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2010.
 * Copyright (C) The National Library of Finland 2026.
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
 * @package  Action
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */

namespace VuFindAdmin\Action\AdminMaintenance;

use DateTime;
use VuFind\ActionHelper\FlashMessagesHelper;
use VuFind\Db\Service\DbServiceInterface;
use VuFind\Db\Service\Feature\DeleteExpiredInterface;
use VuFindAdmin\Action\Admin\AbstractAdminAction;

/**
 * Abtract base class for expiration actions.
 *
 * @category VuFind
 * @package  Action
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
abstract class AbstractExpirationAction extends AbstractAdminAction
{
    /**
     * Abstract delete method.
     *
     * @param DbServiceInterface $service       Service to operate on.
     * @param string             $successString String for reporting success.
     * @param string             $failString    String for reporting failure.
     * @param int                $minAge        Minimum age allowed for expiration (also used
     *                                          as default value).
     *
     * @return void
     */
    protected function expire(
        DbServiceInterface $service,
        string $successString,
        string $failString,
        int $minAge = 2
    ): void {
        $flashMessagesHelper = $this->getHelper(FlashMessagesHelper::class);
        $daysOld = (int)($this->getQueryParam('daysOld', $minAge));
        if ($daysOld < $minAge) {
            $flashMessagesHelper
                ->addErrorMessage(str_replace('%%age%%', $minAge, 'Expiration age must be at least %%age%% days.'));
        } else {
            if (!$service instanceof DeleteExpiredInterface) {
                throw new \Exception('Unsupported service: ' . $service::class);
            }
            $count = $service->deleteExpired(new DateTime("now - $daysOld days"));
            $msg = $count == 0
                ? $failString
                : str_replace('%%count%%', $count, $successString);
            $flashMessagesHelper->addSuccessMessage($msg);
        }
    }
}
