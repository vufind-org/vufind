<?php

/**
 * Reservation List Controller
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2024.
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
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @author   Konsta Raunio <konsta.raunio@helsinki.fi>
 * @author   Kalle Pyykkönen <kalle.pyykkonen@helsinki.fi>
 * @author   Juha Luoma <juha.luoma@helsinki.fi>
 * @author   Tuure Ilmarinen <tuure.ilmarinen@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */

namespace Finna\Controller;

use Exception;
use Finna\ReservationList\Handler\AbstractBase as ConnectionAbstractBase;
use Finna\ReservationList\ReservationListService;
use Finna\View\Helper\Root\ReservationList;
use Laminas\ServiceManager\ServiceLocatorInterface;
use VuFind\Controller\AbstractBase;
use VuFind\Controller\Feature\ListItemSelectionTrait;
use VuFind\Exception\Forbidden as ForbiddenException;
use VuFind\Exception\ListPermission as ListPermissionException;
use VuFind\Exception\LoginRequired as LoginRequiredException;
use VuFind\Exception\RecordMissing as RecordMissingException;

/**
 * Reservation List Controller
 *
 * @category VuFind
 * @package  Controller
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @author   Konsta Raunio <konsta.raunio@helsinki.fi>
 * @author   Kalle Pyykkönen <kalle.pyykkonen@helsinki.fi>
 * @author   Juha Luoma <juha.luoma@helsinki.fi>
 * @author   Tuure Ilmarinen <tuure.ilmarinen@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
class ReservationListController extends AbstractBase
{
    use ListItemSelectionTrait;

    /**
     * Error warning to display when reservation lists are disabled
     *
     * @var string
     */
    protected const RESERVATION_LISTS_DISABLED = 'Reservation lists disabled';

    /**
     * Constructor
     *
     * @param ServiceLocatorInterface $sm                     Service locator
     * @param ReservationListService  $reservationListService Reservation list service
     * @param ReservationList         $reservationListHelper  Reservation list helper
     */
    public function __construct(
        ServiceLocatorInterface $sm,
        protected ReservationListService $reservationListService,
        protected ReservationList $reservationListHelper
    ) {
        parent::__construct($sm);
    }

    /**
     * Retrieves the value of the specified parameter.
     *
     * @param string $param   The name of the parameter to retrieve.
     * @param mixed  $default Default value to return if not found
     *
     * @return mixed The value of the specified parameter.
     */
    protected function getParam(string $param, mixed $default = null): mixed
    {
        return $this->params()->fromRoute($param)
            ?? $this->params()->fromPost($param)
            ?? $this->params()->fromQuery($param, $default);
    }

    /**
     * Validate CSRF from post request
     *
     * @return bool
     */
    protected function validateCsrf(): bool
    {
        $csrf = $this->serviceLocator->get(\VuFind\Validator\CsrfInterface::class);
        $valueFromPost = $this->getRequest()->getPost()->get('csrf');
        return $valueFromPost && $csrf->isValid($valueFromPost);
    }

    /**
     * Add item to list action.
     *
     * @return \Laminas\View\Model\ViewModel
     * @throws \Exception
     */
    public function addItemToListAction()
    {
        if (!$this->reservationListHelper->isFunctionalityEnabled()) {
            throw new ForbiddenException(self::RESERVATION_LISTS_DISABLED);
        }
        $user = $this->getUser();
        if (!$user) {
            return $this->forceLogin();
        }
        $view = $this->createViewModel(
            [
                'institution' => $this->getParam('institution'),
                'listIdentifier' => $this->getParam('listIdentifier'),
                'recordId' => $this->getParam('recordId'),
                'source' => $this->getParam('source'),
            ]
        );
        $driver = $this->getRecordLoader()->load(
            $view->recordId,
            $view->source ?: DEFAULT_SEARCH_BACKEND,
            false
        );
        $listProperties = $this->reservationListService->getListProperties(
            $view->institution,
            $view->listIdentifier
        )['properties'];
        if (!$listProperties || !$listProperties['Enabled']) {
            throw new \VuFind\Exception\Forbidden('Record is not allowed in the list');
        }
        $lists = $this->reservationListService->getListsNotContainingRecord(
            $user,
            $driver,
            $view->listIdentifier,
            $view->institution
        );
        $view->listsContaining = $this->reservationListService->getListsContainingRecord(
            $user,
            $driver,
            $view->listIdentifier,
            $view->institution
        );
        // Filter out already ordered lists
        $view->lists = array_filter(
            $lists,
            fn ($list) => !$list->getOrdered()
        );

        if ($this->formWasSubmitted('list_selected')) {
            if (!$this->validateCsrf()) {
                $this->flashMessenger()->addErrorMessage('csrf_validation_failed');
                return $view;
            }
            $this->reservationListService->saveRecordToReservationList(
                $this->getRequest()
                    ->getPost()
                    ->set('institution', $view->institution),
                $user,
                $driver,
            );
            return $this->inLightbox()  // different behavior for lightbox context
                ? $this->getRefreshResponse()
                : $this->redirect()->toRoute('reservationlist-displaylists');
        }
        return $view;
    }

    /**
     * Add a new list action
     *
     * @return \Laminas\View\Model\ViewModel
     */
    public function createListAction(): \Laminas\View\Model\ViewModel
    {
        if (!$this->reservationListHelper->isFunctionalityEnabled()) {
            throw new ForbiddenException(self::RESERVATION_LISTS_DISABLED);
        }
        $user = $this->getUser();
        if (!$user) {
            return $this->forceLogin();
        }

        $view = $this->createViewModel(
            [
                'source' => $this->getParam('source'),
                'recordId' => $this->getParam('recordId'),
                'institution' => $this->getParam('institution'),
                'listIdentifier' => $this->getParam('listIdentifier'),
            ]
        );
        $listProperties = $this->reservationListService->getListProperties(
            $view->institution,
            $view->listIdentifier
        )['properties'];
        if (!$listProperties || !$listProperties['Enabled']) {
            throw new \VuFind\Exception\Forbidden('List is not enabled');
        }
        if ($this->formWasSubmitted('list_created')) {
            if (!$this->validateCsrf()) {
                $this->flashMessenger()->addErrorMessage('csrf_validation_failed');
                return $view;
            }
            $title = $this->getParam('title');
            if (!$title) {
                return $view;
            }
            $list = $this->reservationListService->createListForUser($user);
            $this->reservationListService->updateListFromRequest(
                $list,
                $user,
                $this->getRequest()->getPost()
            );

            return $this->forwardTo(\Finna\Controller\ReservationListController::class, 'AddItemToList');
        }
        return $view;
    }

    /**
     * List action for the ReservationListController.
     *
     * @return \Laminas\View\Model\ViewModel|\Laminas\Http\Response
     */
    public function displayListAction(): \Laminas\View\Model\ViewModel|\Laminas\Http\Response
    {
        if (!$this->reservationListHelper->isFunctionalityEnabled()) {
            throw new ForbiddenException(self::RESERVATION_LISTS_DISABLED);
        }
        $user = $this->getUser();
        if (!$user) {
            return $this->forceLogin();
        }
        try {
            $list = $this->reservationListService->getListById(
                $this->getParam('id'),
                $user
            );
        } catch (RecordMissingException $e) {
            return $this->redirect()->toRoute('reservationlist-displaylists');
        }
        $results = $this->getListAsResults();
        $viewParams = [
            'list' => $list,
            'results' => $results,
            'params' => $results->getParams(),
            'enabled' => true,
        ];
        try {
            return $this->createViewModel($viewParams);
        } catch (ListPermissionException $e) {
            if (!$this->getUser()) {
                return $this->forceLogin();
            }
            throw $e;
        }
    }

    /**
     * Handles ordering of reservation lists
     *
     * @return mixed
     */
    public function placeOrderAction()
    {
        if (!$this->reservationListHelper->isFunctionalityEnabled()) {
            throw new ForbiddenException(self::RESERVATION_LISTS_DISABLED);
        }
        $user = $this->getUser();
        if (!$user) {
            return $this->forceLogin();
        }
        $request = $this->getRequest();
        $listId = $request->getPost('rl_list_id') ?? $this->getParam('id');
        $list = $this->reservationListService->getListById($listId, $user);
        if ($list->getOrdered()) {
            throw new \VuFind\Exception\Forbidden('List already ordered');
        }
        $listProperties = $this->reservationListService->getListProperties(
            $list->getInstitution(),
            $list->getListConfigIdentifier()
        )['properties'];
        if (!($listProperties['Enabled'] ?? true)) {
            throw new \VuFind\Exception\Forbidden('ReservationList: No list properties found.');
        }

        $handler = $this->getService(\Finna\ReservationList\Handler\PluginManager::class)
            ->getWithConfig($listProperties);
        $orderSpecificValues = $handler->getValuesForPlaceOrderForm($list, $user, $request->getPost()->toArray());
        $form = $handler->getPlaceOrderForm($orderSpecificValues);

        $formId = ConnectionAbstractBase::FORM_ID;
        $view = $this->createViewModel(compact('form', 'formId', 'user'));
        $view->setTemplate('feedback/form');
        $view->useCaptcha = false;

        if (!$this->formWasSubmitted(useCaptcha: false)) {
            $form->setData(
                [
                    'firstName' => $user->getFirstname(),
                    'lastName' => $user->getLastname(),
                    'email' => $user->getEmail(),
                ]
            );
            return $view;
        }

        if (!$form->isValid()) {
            return $view;
        }
        $result = $handler->placeOrder($orderSpecificValues, $user);
        if ($result['success']) {
            $this->reservationListService->setListOrdered($user, $list, $result);
            $this->flashMessenger()->addSuccessMessage($form->getSubmitResponse());
            return $this->getRefreshResponse();
        }
        $this->flashMessenger()->addErrorMessage('could_not_process_feedback');
        return $view;
    }

    /**
     * Deletes a list.
     *
     * @return Response The response object.
     */
    public function deleteListAction()
    {
        if (!$this->reservationListHelper->isFunctionalityEnabled()) {
            throw new ForbiddenException(self::RESERVATION_LISTS_DISABLED);
        }
        $user = $this->getUser();
        if (!$user) {
            return $this->forceLogin();
        }
        $listID = $this->getParam('listID');
        if ($this->getParam('confirm')) {
            try {
                $list = $this->reservationListService->getListById((int)$listID, $user);
                $this->reservationListService->destroyList($list, $user);
                $this->flashMessenger()->addSuccessMessage('ReservationList::List Deleted');
            } catch (LoginRequiredException | ListPermissionException $e) {
                if ($user == false) {
                    return $this->forceLogin();
                }
                // Logged in? Then we have to rethrow the exception!
                throw $e;
            }
            // Redirect to MyResearch home
            return $this->redirect()->toRoute('reservationlist-displaylists');
        }
        return $this->confirm(
            'confirm_delete_list_brief',
            $this->url()->fromRoute('reservationlist-deletelist'),
            $this->url()->fromRoute('reservationlist-displaylists'),
            'confirm_delete_list_text',
            ['id' => $listID]
        );
    }

    /**
     * Delete group of records from a list.
     *
     * @return mixed
     */
    public function deleteBulkAction()
    {
        if (!$this->reservationListHelper->isFunctionalityEnabled()) {
            throw new ForbiddenException(self::RESERVATION_LISTS_DISABLED);
        }

        $user = $this->getUser();
        if (!$user) {
            return $this->forceLogin();
        }

        $listID = $this->getParam('listID', false);
        if (false === $listID) {
            throw new \Exception('List ID not defined in deleteBulkAction');
        }
        $ids = $this->getSelectedIds();
        $list = $this->reservationListService->getListById($listID, $user);
        $viewParams = [
            'resource_ids' => $ids,
            'resources' => $this->getRecordLoader()->loadBatch($ids),
            'list' => $list,
        ];
        if ($this->formWasSubmitted()) {
            if (!$this->validateCsrf()) {
                $this->flashMessenger()->addErrorMessage('csrf_validation_failed');
                return $this->createViewModel($viewParams);
            }
            $this->reservationListService->deleteResourcesFromList(
                $this->getSelectedIds(),
                $list,
                $user
            );
            // Redirect to MyResearch home
            return $this->inLightbox()  // different behavior for lightbox context
                ? $this->getRefreshResponse()
                : $this->redirect()->toRoute('reservationlist-displaylist', ['id' => $listID]);
        }

        return $this->createViewModel($viewParams);
    }

    /**
     * Action to display all users reservation lists
     *
     * @return \Laminas\View\Model\ViewModel
     */
    public function displayListsAction()
    {
        if (!$this->reservationListHelper->isFunctionalityEnabled()) {
            throw new ForbiddenException(self::RESERVATION_LISTS_DISABLED);
        }
        $user = $this->getUser();
        if (!$user) {
            return $this->forceLogin();
        }
        $lists = $this->reservationListService->getReservationListsForUser($user);
        $view = $this->createViewModel(
            ['lists' => $lists]
        );
        return $view;
    }

    /**
     * Retrieves the request as an array.
     *
     * @return array Request as an array.
     */
    protected function getRequestAsArray(): array
    {
        $request = $this->getRequest()->getQuery()->toArray()
          + $this->getRequest()->getPost()->toArray();

        if (!null !== $this->params()->fromRoute('id')) {
            $request += ['id' => $this->params()->fromRoute('id')];
        }
        return $request;
    }

    /**
     * Retrieves list of reservations as results.
     *
     * @return \VuFind\Search\Base\Results
     */
    protected function getListAsResults()
    {
        $request = $this->getRequestAsArray();
        $runner = $this->serviceLocator->get(\VuFind\Search\SearchRunner::class);
        // Set up listener for recommendations:
        $rManager = $this->serviceLocator
            ->get(\VuFind\Recommend\PluginManager::class);
        $setupCallback = function ($runner, $params, $searchId) use ($rManager) {
            $listener = new \VuFind\Search\RecommendListener($rManager, $searchId);
            $listener->setConfig(
                $params->getOptions()->getRecommendationSettings()
            );
            $listener->attach($runner->getEventManager()->getSharedManager());
        };

        return $runner->run($request, 'ReservationList', $setupCallback);
    }
}
