<?php

/**
 * Overdrive Hold action.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2018.
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
 * @author   Brent Palmer <brent-palmer@icpl.org>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */

namespace VuFind\Action\Overdrive;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerAwareInterface;
use VuFind\ActionHelper\LoginHelper;
use VuFind\Log\LoggerAwareTrait;

use function is_array;

/**
 * Overdrive Hold action.
 *
 * @category VuFind
 * @package  Action
 * @author   Brent Palmer <brent-palmer@icpl.org>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
class HoldAction extends AbstractOverdriveAction implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    /**
     * Overdrive action definitions.
     *
     * @var array
     */
    protected array $actions = [
        'checkoutConfirm' => ['titleCode' => 'od_checkout', 'resMeth' => 'getConfirmCheckoutRes'],
        'holdConfirm' => ['titleCode' => 'od_hold', 'resMeth' => 'getHoldConfirmRes'],
        'cancelHoldConfirm' => ['titleCode' => 'od_cancel_hold', 'resMeth' => null],
        'suspHoldConfirm' => ['titleCode' => 'od_susp_hold', 'resMeth' => null],
        'editHoldConfirm' => ['titleCode' => 'od_susp_hold_edit', 'resMeth' => null],
        'editHoldEmailConfirm' => ['titleCode' => 'od_edit_hold_email', 'resMeth' => 'getEditHoldEmailConfRes'],
        'returnTitleConfirm' => ['titleCode' => 'od_early_return', 'resMeth' => 'getReturnTitleConfirmResult'],
        'doCheckout' => ['titleCode' => 'od_checkout', 'resMeth' => 'getCheckoutRes'],
        'placeHold' => ['titleCode' => 'od_hold', 'resMeth' => 'getPlaceHoldRes'],
        'editHoldEmail' => ['titleCode' => 'od_hold', 'resMeth' => 'getEditHoldEmailRes'],
        'suspendHold' => ['titleCode' => 'od_susp_hold', 'resMeth' => 'getSuspendHoldRes'],
        'editSuspendedHold' => ['titleCode' => 'od_susp_hold_edit', 'resMeth' => 'getEditSuspendedRes'],
        'cancelHold' => ['titleCode' => 'od_cancel_hold', 'resMeth' => 'getCancelHoldRes'],
        'returnTitle' => ['titleCode' => 'od_early_return', 'resMeth' => 'getReturnTitleRes'],
        'getTitle' => ['titleCode' => 'od_get_title', 'resMeth' => 'getDownloadTitleRes'],
    ];

    /**
     * Hold Action.
     *
     * Hold Action handles all of the actions involving Overdrive content including checkout, hold, cancel hold etc.
     *
     * @param ServerRequestInterface $request  Server request
     * @param ResponseInterface      $response Response
     *
     * @return ResponseInterface
     */
    public function action(
        ServerRequestInterface $request,
        ResponseInterface $response,
    ): ResponseInterface {
        // Stop now if the user does not have valid catalog credentials available:
        if (!is_array($patron = $this->getHelper(LoginHelper::class)->catalogLogin($request, $response))) {
            if (!($patron instanceof ResponseInterface)) {
                throw new \Exception('Unexpected response from LoginHelper::catalogLogin');
            }
            return $patron;
        }

        $od_id = $this->getQueryParam('od_id');
        $rec_id = $this->getQueryParam('rec_id');

        // These can come through the form as well as query:
        $action = $this->getPostParam('doAction') ?? $this->getQueryParam('action');
        $edition = $this->getPostOrQueryParam('edition');

        $this->debug("ODRC od_id=$od_id rec_id=$rec_id action=$action");
        $driver = $this->loadOverdriveRecord($rec_id);

        $formats = $driver->getDigitalFormats();
        $title = $driver->getTitle();
        $cover = $driver->getThumbnail('small');
        $listAuthors = $driver->getPrimaryAuthors();
        $result = null;
        $actionTitleCode = '';

        if (!$action) {
            // Double check the availability in case it has changed since the page
            // was loaded.
            $avail = $driver->getOverdriveAvailability();
            $action = ($avail->copiesAvailable > 0) ? 'checkoutConfirm' : 'holdConfirm';
        }

        if (isset($this->actions[$action])) {
            $actionTitleCode = $this->actions[$action]['titleCode'];
            $result = $this->actions[$action]['resMeth'] ? $this->{$this->actions[$action]['resMeth']}() : false;
        } else {
            $this->logWarning("Overdrive action not defined: $action");
        }

        $templateParams = compact(
            'od_id',
            'rec_id',
            'action',
            'result',
            'formats',
            'cover',
            'title',
            'actionTitleCode',
            'listAuthors',
            'edition'
        );

        return $this->renderTemplate($request, $response, $templateParams, 'RecordDriver/SolrOverdrive/hold');
    }

    /**
     * Get hold confirmation result.
     *
     * @return object Result Object
     */
    protected function getHoldConfirmRes()
    {
        $od_id = $this->getQueryParam('od_id');
        $result = $this->overdriveConnector->getResultObject();
        // Check to make sure they don't already have this checked out. Shouldn't need to refresh.
        if ($checkout = $this->overdriveConnector->getCheckout($od_id, true)) {
            $result->status = false;
            $result->data = (object)[];
            $result->data->checkout = $checkout;
            $result->code = 'OD_CODE_ALREADY_CHECKED_OUT';
            $this->debug("title already checked out: $od_id");
        } elseif ($hold = $this->overdriveConnector->getHold($od_id, true)) {
            $result->status = false;
            $result->data = (object)[];
            $result->data->hold = $hold;
            $result->code = 'OD_CODE_ALREADY_ON_HOLD';
            $this->debug("title already on hold: $od_id");
        } else {
            $result->status = true;
        }
        return $result;
    }

    /**
     * Get checkout confirmation result.
     *
     * @return object Result Object
     */
    protected function getConfirmCheckoutRes()
    {
        $od_id = $this->getQueryParam('od_id');
        $rec_id = $this->getQueryParam('rec_id');
        $driver = $this->loadOverdriveRecord($rec_id);
        $formats = $driver->getDigitalFormats();
        if (current($formats)->id == 'magazine-overdrive') {
            // Looks like this is a magazine...
            $result = $this->overdriveConnector->getMagazineIssues($od_id, true);
            if ($result->status) {
                $result->data->isMagazine = true;
            } else {
                $this->debug("couldn't get issues for checkout");
                $result->status = false;
                $result->code = 'OD_CODE_NOMAGISSUES';
                $result->msg = 'No magazine issue available.';
            }
        } else {
            $result = $this->overdriveConnector->getResultObject();
            $result->data = (object)['isMagazine' => false];
            // Check to make sure they don't already have this checked out. Shouldn't need to refresh.
            if ($checkout = $this->overdriveConnector->getCheckout($od_id, false)) {
                $result->status = false;
                $result->data->checkout = $checkout;
                $result->code = 'OD_CODE_ALREADY_CHECKED_OUT';
            } elseif ($hold = $this->overdriveConnector->getHold($od_id, false)) {
                if ($hold->holdReadyForCheckout) {
                    $result->status = true;
                } else {
                    $result->status = false;
                    $result->data->hold = $hold;
                    $result->code = 'OD_CODE_ALREADY_ON_HOLD';
                }
            } else {
                $result->status = true;
            }
        }
        return $result;
    }

    /**
     * Do checkout and get result.
     *
     * @return object Result Object
     */
    protected function getCheckoutRes()
    {
        $od_id = $this->getQueryParam('od_id');
        if ($edition = $this->getPostOrQueryParam('edition')) {
            $od_id = $edition;
        }
        return $this->overdriveConnector->doOverdriveCheckout($od_id);
    }

    /**
     * Place hold and get result.
     *
     * @return object Result Object
     */
    protected function getPlaceHoldRes()
    {
        $od_id = $this->getQueryParam('od_id');
        $email = $this->getPostParam('email');
        $result = $this->overdriveConnector->placeOverDriveHold($od_id, $email);
        if ($result->status) {
            $result->code = 'od_hold_place_success';
            $result->codeParams = ['%%holdListPosition%%' => $result->data->holdListPosition];
        } else {
            $result->code = 'od_hold_place_failure';
        }
        return $result;
    }

    /**
     * Get edit hold email confirmation result.
     *
     * @return object Result Object
     */
    protected function getEditHoldEmailConfRes()
    {
        $od_id = $this->getQueryParam('od_id');
        $hold = $this->overdriveConnector->getHold($od_id, true);
        $result = $this->overdriveConnector->getResultObject(true);
        $result->data = (object)[];
        $result->data->hold = $hold;
        return $result;
    }

    /**
     * Edit hold email and get result.
     *
     * @return object Result Object
     */
    protected function getEditHoldEmailRes()
    {
        $od_id = $this->getQueryParam('od_id');
        $email = $this->getPostParam('email');
        $result = $this->overdriveConnector->updateOverDriveHold($od_id, $email);
        $result->code = $result->status ? 'od_hold_update_success' : 'od_hold_update_failure';
        return $result;
    }

    /**
     * Get return title confirmation result.
     *
     * @return object Result Object
     */
    protected function getReturnTitleConfirmResult()
    {
        $result = $this->overdriveConnector->getResultObject();
        $rec_id = $this->getQueryParam('rec_id');
        $driver = $this->loadOverdriveRecord($rec_id);
        $formats = $driver->getDigitalFormats();
        $result->data = (current($formats)->id == 'magazine-overdrive') ?: false;
        return $result;
    }

    /**
     * Suspend hold and get result.
     *
     * @return object Result Object
     */
    protected function getSuspendHoldRes()
    {
        $od_id = $this->getQueryParam('od_id');
        $suspendValue = $this->getPostParam('suspendValue');
        $hold = $this->overdriveConnector->getHold($od_id, false);
        $holdEmail = $hold->emailAddress;
        $suspensionType = $suspendValue == -1 ? 'indefinite' : 'limited';
        $result = $this->overdriveConnector->suspendHold($od_id, $holdEmail, $suspensionType, $suspendValue);

        if ($result->status) {
            if ($suspensionType == 'indefinite') {
                $result->code = 'od_hold_susp_indef';
            } else {
                $result->code = 'od_hold_redelivery';
                $result->codeParams = ['%%days%%' => $result->data->holdSuspension->numberOfDays];
            }
        } else {
            $result->code = 'od_hold_update_failure';
        }
        return $result;
    }

    /**
     * Toggle hold suspension and get result.
     *
     * @return object Result Object
     */
    protected function getEditSuspendedRes()
    {
        $od_id = $this->getQueryParam('od_id');
        $suspendValue = $this->getPostParam('suspendValue');
        if ($suspendValue == 0) {
            $result = $this->overdriveConnector->deleteHoldSuspension($od_id);
        } else {
            $hold = $this->overdriveConnector->getHold($od_id, false);
            $holdEmail = $hold->emailAddress;
            $suspensionType = $suspendValue == -1 ? 'indefinite' : 'limited';
            $result = $this->overdriveConnector->editSuspendedHold($od_id, $holdEmail, $suspensionType, $suspendValue);
        }
        $result->code = $result->status ? 'od_hold_update_success' : 'od_hold_update_failure';
        return $result;
    }

    /**
     * Cancel hold and get result.
     *
     * @return object Result Object
     */
    protected function getCancelHoldRes()
    {
        $od_id = $this->getQueryParam('od_id');
        $result = $this->overdriveConnector->cancelHold($od_id);
        $result->code = $result->status ? 'od_hold_cancel_success' : 'od_hold_cancel_failure';
        return $result;
    }

    /**
     * Return title and get result.
     *
     * @return object Result Object
     */
    protected function getReturnTitleRes()
    {
        $od_id = $this->getQueryParam('od_id');
        $result = $this->overdriveConnector->returnResource($od_id);
        $result->code = $result->status ? 'od_return_success' : 'od_return_failure';
        return $result;
    }

    /**
     * Get title download redirect URI.
     *
     * @return object Result Object
     */
    protected function getDownloadTitleRes()
    {
        $od_id = $this->getQueryParam('od_id');
        $result = $this->overdriveConnector->getDownloadRedirect($od_id);
        $result->code = $result->status ? '' : 'od_gettitle_failure';
        return $result;
    }
}
