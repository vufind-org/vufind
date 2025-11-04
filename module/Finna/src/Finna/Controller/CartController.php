<?php

/**
 * Book Bag / Bulk Action Controller
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2010.
 * Copyright (C) The National Library of Finland 2017.
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
 * @package  Controller
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Konsta Raunio <konsta.raunio@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */

namespace Finna\Controller;

use VuFind\Controller\Feature\ListItemSelectionTrait;
use VuFind\Exception\Forbidden as ForbiddenException;
use VuFind\Exception\Mail as MailException;

use function count;
use function is_array;

/**
 * Book Bag / Bulk Action Controller
 *
 * @category VuFind
 * @package  Controller
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Konsta Raunio <konsta.raunio@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
class CartController extends \VuFind\Controller\CartController
{
    use ListItemSelectionTrait;

    /**
     * Email a batch of records.
     *
     * @return mixed
     */
    public function emailAction()
    {
        // Retrieve ID list:
        $ids = $this->getSelectedIds();

        // Retrieve follow-up information if necessary:
        if (!is_array($ids) || empty($ids)) {
            $ids = $this->followup()->retrieveAndClear('cartIds') ?? [];
        }
        $actionLimit = $this->getBulkActionLimit('email');
        if (!is_array($ids) || empty($ids)) {
            if ($redirect = $this->redirectToSource('error', 'bulk_noitems_advice')) {
                return $redirect;
            }
            $submitDisabled = true;
        } elseif (count($ids) > $actionLimit) {
            $errorMsg = $this->translate(
                'bulk_limit_exceeded',
                ['%%count%%' => count($ids), '%%limit%%' => $actionLimit],
            );
            if ($redirect = $this->redirectToSource('error', $errorMsg)) {
                return $redirect;
            }
            $submitDisabled = true;
        }

        $emailActionSettings = $this->getService(\VuFind\Config\AccountCapabilities::class)->getEmailActionSetting();
        if ($emailActionSettings === 'disabled') {
            throw new ForbiddenException('Email action disabled');
        }
        // Force login if necessary:
        if (
            $emailActionSettings !== 'enabled'
            && !$this->getUser()
        ) {
            return $this->forceLogin(
                null,
                ['cartIds' => $ids, 'cartAction' => 'Email']
            );
        }

        $view = $this->createEmailViewModel(
            null,
            $this->translate('bulk_email_title')
        );
        $view->records = $this->getRecordLoader()->loadBatch($ids);
        // Set up Captcha
        $view->useCaptcha = $this->captcha()->active('email');

        // Process form submission:
        if (!($submitDisabled ?? false) && $this->formWasSubmitted(null, $view->useCaptcha)) {
            // Attempt to send the email and show an appropriate flash message:
            try {
                // If we got this far, we're ready to send the email:
                $mailer = $this->serviceLocator->get(\VuFind\Mailer\Mailer::class);
                $mailer->setMaxRecipients($view->maxRecipients);
                $cc = $this->params()->fromPost('ccself') && $view->from != $view->to
                    ? $view->from : null;
                $mailer->sendRecords(
                    $view->to,
                    $view->from,
                    $view->message,
                    $view->records,
                    $this->getViewRenderer(),
                    $view->subject,
                    $cc
                );
                return $this->redirectToSource('success', 'bulk_email_success', true);
            } catch (MailException $e) {
                $this->flashMessenger()->addMessage(
                    $e->getDisplayMessage(),
                    'error'
                );
            }
        }
        return $view;
    }

    /**
     * Create a new ViewModel to use as an email form.
     *
     * @param array  $params         Parameters to pass to ViewModel constructor.
     * @param string $defaultSubject Default subject line to use.
     *
     * @return ViewModel
     */
    protected function createEmailViewModel($params = null, $defaultSubject = null)
    {
        $view = parent::createEmailViewModel($params, $defaultSubject);
        if (empty($view->message)) {
            $listName = $this->params()->fromPost('listName', '');
            $listDescription = $this->params()->fromPost('listDescription', '');

            if ($listName && $listDescription) {
                $view->message = "$listName\n\n$listDescription";
            } else {
                $view->message = "$listName$listDescription";
            }
        }
        return $view;
    }
}
