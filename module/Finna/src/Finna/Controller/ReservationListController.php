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
use Finna\ReservationList\Handler\PluginManager;
use Finna\ReservationList\ReservationListService;
use Finna\View\Helper\Root\ReservationList;
use Laminas\ServiceManager\ServiceLocatorInterface;
use Laminas\Stdlib\Parameters;
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
     * @param ServiceLocatorInterface $sm                           Service locator
     * @param ReservationListService  $reservationListService       Reservation list service
     * @param ReservationList         $reservationListHelper        Reservation list helper
     * @param PluginManager           $reservationListPluginManager Reservation list helper
     */
    public function __construct(
        ServiceLocatorInterface $sm,
        protected ReservationListService $reservationListService,
        protected ReservationList $reservationListHelper,
        protected PluginManager $reservationListPluginManager
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
        $view->driver = $driver;
        $listHandler = $this->reservationListService->getListHandler(
            $view->institution,
            $view->listIdentifier
        );
        if (!$listHandler->isEnabled()) {
            throw new \VuFind\Exception\Forbidden('Record is not allowed in the list');
        }
        $lists = $this->reservationListService->getListsNotContainingRecord(
            $user,
            $driver,
            $listHandler->getIdentifier(),
            $listHandler->getInstitution()
        );
        $view->listsContaining = $this->reservationListService->getListsContainingRecord(
            $user,
            $driver,
            $listHandler->getIdentifier(),
            $listHandler->getInstitution()
        );
        // Filter out already ordered lists
        $view->listEntities = array_filter(
            $lists,
            fn ($list) => !$list->getOrdered()
        );
        $view->listHandler = $listHandler;
        if ($this->formWasSubmitted('list_selected')) {
            if (!$this->validateCsrf()) {
                $this->flashMessenger()->addErrorMessage('csrf_validation_failed');
                return $view;
            }
            $listEntity = $this->reservationListService->saveRecordToReservationList(
                $this->getRequest()
                    ->getPost()
                    ->set('institution', $listHandler->getInstitution()),
                $user,
                $driver,
            );
            $view->setTemplate('reservationlist/postadditem');
            $view->listEntity = $listEntity;
            return $view;
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
        $listHandler = $this->reservationListService->getListHandler(
            $view->institution,
            $view->listIdentifier
        );
        if (!$listHandler->isEnabled()) {
            throw new \VuFind\Exception\Forbidden('List is not enabled');
        }
        if ($view->recordId && $view->source) {
            $view->driver = $this->getRecordLoader()->load(
                $view->recordId,
                $view->source,
                false
            );
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
            $newListValues = [
                'title' => $title,
                'desc' => $this->getParam('desc'),
                'institution' => $listHandler->getInstitution(),
                'listIdentifier' => $listHandler->getIdentifier(),
                'connection' => $listHandler->getConnectionType(),
            ];
            $this->reservationListService->updateListFromRequest(
                $list,
                $user,
                $newListValues
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
                $this->getParam('listId'),
                $user
            );
        } catch (RecordMissingException $e) {
            return $this->redirect()->toRoute('reservationlist-displaylists');
        }
        $listHandler = $this->reservationListService->getListHandler(
            $list->getInstitution(),
            $list->getListConfigIdentifier()
        );
        $results = $this->getListAsResults();
        $viewParams = [
            'listEntity' => $list,
            'listHandler' => $listHandler,
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
     * Action route to select how to order the singular item currently selected.
     *
     * @return \Laminas\View\Model\ViewModel
     */
    public function placeOrderOptionsAction()
    {
        $driver = $this->getRecordLoader()->load(
            $this->params()->fromQuery('recordId'),
            $this->params()->fromQuery('source') ?: DEFAULT_SEARCH_BACKEND,
            false
        );
        $listHandler = $this->reservationListService->getListHandler(
            $this->params()->fromQuery('institution'),
            $this->params()->fromQuery('listIdentifier')
        );
        return $this->createViewModel([
            'driver' => $driver,
            'source' => $this->params()->fromQuery('source'),
            'recordId' => $this->params()->fromQuery('recordId'),
            'listHandler' => $listHandler,
        ]);
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

        $listId = $this->getParam('listId');
        $list = $this->reservationListService->getListById($listId, $user);
        if ($list->getOrdered()) {
            throw new \VuFind\Exception\Forbidden('List already ordered');
        }

        $listHandler = $this->reservationListService->getListHandler(
            $list->getInstitution(),
            $list->getListConfigIdentifier()
        );
        if (!$listHandler->isEnabled()) {
            throw new \VuFind\Exception\Forbidden('ReservationList: No list properties found.');
        }
        if (!$this->reservationListService->checkUserRightsForList($listHandler)) {
            $this->flashMessenger()->addErrorMessage('no_ils_support_description');
            if ($this->inLightbox()) {
                return $this->getRefreshResponse();
            }
            return $this->redirect()->toRoute('reservationlist-displaylist', ['listId' => $listId]);
        }
        $request = $this->getRequest();
        $orderSpecificValues = $listHandler->getValuesForListOrder(
            $list,
            $user,
            $request->isGet() ? $request->getQuery()->toArray() : $request->getPost()->toArray()
        );

        $form = $listHandler->getPlaceOrderForm($orderSpecificValues);
        $form->setData($orderSpecificValues);
        $formId = ConnectionAbstractBase::FORM_ID;
        $view = $this->createViewModel(compact('form', 'formId', 'user'));
        $view->setTemplate('feedback/form');
        $view->useCaptcha = false;
        if (!$this->formWasSubmitted(useCaptcha: false)) {
            return $view;
        }

        if (!$form->isValid()) {
            return $view;
        }
        $result = $listHandler->placeOrder($orderSpecificValues, $user);
        if ($result['success']) {
            $this->reservationListService->setListOrdered($user, $list, $result);
            $this->flashMessenger()->addSuccessMessage($form->getSubmitResponse());
            return $this->getRefreshResponse();
        }
        $this->flashMessenger()->addErrorMessage('hold_error_fail');
        return $view;
    }

    /**
     * Handles ordering of a singular item
     *
     * @return mixed
     */
    public function placeSingleOrderAction()
    {
        if (
            !$this->reservationListHelper->isFunctionalityEnabled()
            || !$this->reservationListService->singleOrderEnabled()
        ) {
            throw new ForbiddenException(self::RESERVATION_LISTS_DISABLED);
        }
        $user = $this->getUser();
        if (!$user) {
            return $this->forceLogin();
        }
        $institution = $this->getParam('institution');
        $listIdentifier = $this->getParam('listIdentifier');
        $listHandler = $this->reservationListService->getListHandler(
            $institution,
            $listIdentifier
        );
        if (!$listHandler->isEnabled()) {
            throw new \VuFind\Exception\Forbidden('ReservationList: No list properties found.');
        }
        $request = $this->getRequest();
        $requestValues = $request->isGet() ? $request->getQuery()->toArray() : $request->getPost()->toArray();

        // Form a default title as a suggestion to be used as a list name
        $dateConverter = $this->serviceLocator->get(\VuFind\Date\Converter::class);
        $requestValues['list_title'] ??= $this->getTranslator()->translate('List')
            . ' ' . $dateConverter->convertToDisplayDate('U', time());

        $listValues = [
            'title' => $requestValues['list_title'],
            'desc' => '',
            'institution' => $listHandler->getInstitution(),
            'listIdentifier' => $listHandler->getIdentifier(),
            'connection' => $listHandler->getConnectionType(),
        ];

        // Create an empty list for the user, but do not save it.
        $listEntity = $this->reservationListService->createListForUser($user, $listValues);
        $formId = ConnectionAbstractBase::FORM_ID;
        $queryValues = $listHandler->getValuesForSingleOrder(
            $listEntity,
            $user,
            $requestValues
        );
        if (!$this->reservationListService->checkUserRightsForList($listHandler)) {
            $this->flashMessenger()->addErrorMessage('no_ils_support_description');
            if ($this->inLightbox()) {
                return $this->getRefreshResponse();
            }
            return $this->redirect()->toRoute('record', ['id' => $queryValues['recordId']]);
        }
        $form = $listHandler->getSingleOrderForm($queryValues);
        $view = $this->createViewModel(compact('formId', 'user', 'form'));
        $view->setTemplate('feedback/form');
        $view->useCaptcha = false;
        if (!$this->formWasSubmitted(useCaptcha: false)) {
            $form->setData($queryValues);
            return $view;
        }
        if (!$form->isValid()) {
            return $view;
        }

        $result = $listHandler->placeOrder($queryValues, $user);
        if ($result['success']) {
            $driver = $this->getRecordLoader()->load(
                $queryValues['recordId'],
                $queryValues['source'],
                false
            );
            // Save single order into a list
            $this->reservationListService->populateListValues($listEntity, $user, $listValues);
            $this->reservationListService->setListOrdered($user, $listEntity, $result);

            $params = new Parameters(['list' => $listEntity->getId()]);
            $this->reservationListService->saveRecordToReservationList($params, $user, $driver);
            $view->setTemplate('reservationlist/postadditem');
            $view->listEntity = $listEntity;
            $view->driver = $driver;
            return $view;
        }
        $this->flashMessenger()->addErrorMessage('hold_error_fail');
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
        $listID = $this->getParam('listId');
        if ($this->getParam('confirm')) {
            try {
                $list = $this->reservationListService->getListById((int)$listID, $user);
                $this->reservationListService->destroyList($list, $user);
                $this->flashMessenger()->addSuccessMessage('ReservationList::List deleted');
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
            ['listId' => $listID]
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
                : $this->redirect()->toRoute('reservationlist-displaylist', ['listId' => $listID]);
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
            ['listEntities' => $lists]
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

        if (null !== $this->params()->fromRoute('listId')) {
            $request += ['id' => $this->params()->fromRoute('listId')];
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
        $rManager = $this->getService(\VuFind\Recommend\PluginManager::class);
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
