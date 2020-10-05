<?php
/**
 * R2 Feedback Controller
 *
 * PHP version 7
 *
 * Copyright (C) The National Library of Finland 2015-2020.
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
 * PHP version 7
 *
 * @category VuFind
 * @package  Controller
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
namespace Finna\Controller;

use Finna\Form\Form;
use Finna\Form\R2Form;

/**
 * R2 Feedback Controller
 *
 * @category VuFind
 * @package  Controller
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
class R2FeedbackController extends FeedbackController
    implements \Laminas\Log\LoggerAwareInterface
{
    use \VuFind\Log\LoggerAwareTrait;

    /**
     * Feedback form class
     *
     * @var string
     */
    protected $formClass = \Finna\Form\R2Form::class;

    /**
     * Handles rendering and submit of dynamic forms.
     * Form configurations are specified in FeedbackForms.json
     *
     * @return void
     */
    public function formAction()
    {
        $formId = $this->params()->fromRoute('id', $this->params()->fromQuery('id'));
        if (!R2Form::isR2RegisterForm($formId)) {
            return;
        }

        if (!($user = $this->getUser())) {
            // Not logged, prompt login
            return $this->forceLogin();
        }

        $submitted = $this->formWasSubmitted('submit');

        if (!$submitted) {
            if ($formId === R2Form::R2_REGISTER_FORM && $this->getUser()
            ) {
                $rems
                    = $this->serviceLocator->get(\Finna\Service\RemsService::class);
                try {
                    if ($rems->isUserRegistered()) {
                        // Replace R2 new user registration form id with the id for
                        // returning user registration form.
                        return $this->forwardTo(
                            'R2Feedback', 'Form',
                            ['id' => R2Form::R2_REGISTER_RETURNING_USER_FORM]
                        );
                    }
                } catch (\Exception $e) {
                }
            }
        }

        $getRedirect = function () {
            // Logged but not authorized (wrong login method etc), close form
            if ($this->inLightbox()) {
                // Login completed inside lightbox: refresh page
                $response = $this->getResponse();
                $response->setStatusCode(205);
                return '';
            } else {
                return $this->redirect()->toRoute('search-home');
            }
        };

        // Verify that user is authenticated to access restricted R2 data.
        $isAuthenticated
            = $this->serviceLocator->get(\Finna\Service\R2SupportService::class)
            ->isAuthenticated();
        if (!$isAuthenticated) {
            return $getRedirect();
        }

        // Check user permission from REMS and show registration if needed.
        $rems = $this->serviceLocator->get(\Finna\Service\RemsService::class);
        try {
            if ($rems->isUserBlocklisted()) {
                return $getRedirect();
            }
        } catch (\Exception $e) {
            $this->flashMessenger()->addErrorMessage('R2_rems_connect_error');
            return $getRedirect();
        }

        try {
            if ($rems->hasUserAccess(true)) {
                // User already has access
                return $getRedirect();
            }
        } catch (\Exception $e) {
            $this->flashMessenger()->addErrorMessage('R2_rems_connect_error');
            return $getRedirect();
        }

        if ($submitted) {
            $form = $this->serviceLocator->get($this->formClass);
            $form->setFormId($formId);

            $view = $this->createViewModel(compact('form', 'formId', 'user'));
            $view->setTemplate('feedback/form');
            $params = $this->params()->fromPost();
            $form->setData($params);

            if (!$form->isValid()) {
                return $view;
            }

            // Collect submitted params required by REMS form
            $formParams = [];
            $formParams['usage_purpose'] = $params['usage_purpose'];
            $formParams['usage_purpose_text'] = $params['usage_purpose'];
            if ($age = ($params['age'] ?? null)) {
                $formParams['age'] = $age[0];
            }
            if ($license = ($params['license'] ?? null)) {
                $formParams['license'] = $license[0];
            }

            // Take firstname and lastname from profile
            $firstname = $user->firstname;
            $lastname = $user->lastname;
            $email = $params['email'];

            try {
                $rems->registerUser(
                    $email,
                    $firstname,
                    $lastname,
                    $formParams
                );
            } catch (\Exception $e) {
                $this->flashMessenger()->addErrorMessage('R2_register_error');
                $this->logError('REMS registration error: ' . $e->getMessage());
            }

            return $getRedirect();
        }

        $view = parent::formAction();
        $view->setTemplate('feedback/form');
        return $view;
    }

    /**
     * Prefill form sender fields for logged in users.
     *
     * @param Form  $form Form
     * @param array $user User
     *
     * @return Form
     */
    protected function prefillUserInfo($form, $user)
    {
        $form = parent::prefillUserInfo($form, $user);
        if ($user) {
            $form->populateValues(
                [
                 'firstname' => $user->firstname,
                 'lastname' => $user->lastname
                ]
            );
        }
        return $form;
    }
}
