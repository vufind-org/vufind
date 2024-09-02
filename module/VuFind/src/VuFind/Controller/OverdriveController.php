<?php

/**
 * Overdrive Controller
 *
 * PHP version 8
 *
 * @category VuFind
 * @package  Controller
 * @author   Brent Palmer <brent-palmer@ipcl.org>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */

namespace VuFind\Controller;

use Laminas\Log\LoggerAwareInterface;
use Laminas\ServiceManager\ServiceLocatorInterface;
use VuFind\DigitalContent\OverdriveConnector;

use function is_array;

/**
 * Overdrive Controller supports actions for Overdrive Integration
 *
 * @category VuFind
 * @package  Controller
 * @author   Brent Palmer <brent-palmer@ipcl.org>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:controllers Wiki
 */
class OverdriveController extends AbstractBase implements LoggerAwareInterface
{
    use \VuFind\Log\LoggerAwareTrait {
        logError as error;
    }

    /**
     * Overdrive Connector
     *
     * @var OverdriveConnector $connector Overdrive Connector
     */
    protected $connector;

    /**
     * Constructor
     *
     * @param ServiceLocatorInterface $sm Service locator
     */
    public function __construct(ServiceLocatorInterface $sm)
    {
        $this->setLogger($sm->get(\VuFind\Log\Logger::class));
        $this->connector
            = $sm->get(\VuFind\DigitalContent\OverdriveConnector::class);
        parent::__construct($sm);
    }

    /**
     * My Content Action
     * Prepares the view for the Overdrive MyContent template.
     *
     * @return array|bool|\Laminas\View\Model\ViewModel
     */
    public function mycontentAction()
    {
        $this->debug('ODC mycontent action');
        // Force login
        if (!is_array($patron = $this->catalogLogin())) {
            return $patron;
        }
        $holds = [];
        $checkouts = [];
        $checkoutsUnavailable = false;
        $holdsUnavailable = false;

        // Check on this patrons's access to Overdrive
        $odAccessResult = $this->connector->getAccess();

        if (!($odAccessResult->status ?? false)) {
            $this->debug('result:' . print_r($odAccessResult, true));
            $this->flashMessenger()->addErrorMessage(
                $this->translate(
                    $odAccessResult->code ?? 'An error has occurred',
                    ['%%message%%' => $odAccessResult->msg ?? '']
                )
            );
            $checkoutsUnavailable = true;
            $holdsUnavailable = true;
        } else {
            // Get the current Overdrive checkouts
            // for this user and add to our array of IDS
            $checkoutResults = $this->connector->getCheckouts(true);
            if (!($checkoutResults->status ?? false)) {
                $this->flashMessenger()->addMessage(
                    $checkoutResults->code ?? 'An error has occurred',
                    'error'
                );
                $checkoutsUnavailable = true;
            } else {
                foreach ($checkoutResults->data as $checkout) {
                    $mycheckout = compact('checkout');

                    if ($checkout->metadata->mediaType == 'Magazine') {
                        $mycheckout['checkout']->isMagazine = true;
                        $this->debug("loading magazine metadata for {$checkout->reserveId}");
                        $idToLoad = strtolower($checkout->metadata->parentMagazineReferenceId);
                        $this->debug("loading magazine parent with id: $idToLoad instead");
                    } else {
                        $mycheckout['checkout']->isMagazine = false;
                        $idToLoad = strtolower($checkout->reserveId);
                    }

                    try {
                        $this->debug("loading checkout using: $idToLoad");
                        $mycheckout['record'] = $this->getService(\VuFind\Record\Loader::class)
                            ->load($idToLoad);
                        $checkouts[] = $mycheckout;
                    } catch (\VuFind\Exception\RecordMissing $e) {
                        $this->debug("missing record in index: $idToLoad");
                        // checkout is missing from Solr
                        $this->flashMessenger()->addMessage(
                            'One or more checkouts could not be displayed properly: ' .
                            $e->getMessage(),
                            'error'
                        );
                        // get metadata from overdrive.
                        $meta = $this->connector->getMetadata([strtolower($checkout->reserveId)]);
                        $mycheckout['metadata'] = $meta[strtolower($checkout->reserveId)];
                        $checkouts[] = $mycheckout;
                    }
                }
            }
            // Get the current Overdrive holds for this user and add to
            // our array of IDS
            $holdsResults = $this->connector->getHolds(true);
            if (
                !($holdsResults->status ?? false)
                && ($checkoutResults->status ?? false) // avoid double errors
            ) {
                $this->flashMessenger()->addMessage(
                    $holdsResults->code ?? 'An error has occurred',
                    'error'
                );
                $holdsUnavailable = true;
            } else {
                foreach ($holdsResults->data as $hold) {
                    $myhold['hold'] = $hold;
                    try {
                        $myhold['record']
                            = $this->getService(\VuFind\Record\Loader::class)
                            ->load(strtolower($hold->reserveId));
                        $holds[] = $myhold;
                    } catch (\VuFind\Exception\RecordMissing $e) {
                        // hold is missing from Solr
                        $this->flashMessenger()->addMessage(
                            'One or more holds could not be displayed properly: ' .
                            $e->getMessage(),
                            'error'
                        );

                        // get metadata from overdrive.
                        $meta = $this->connector->getMetadata([$hold->reserveId]);
                        $myhold['metadata'] = $meta[$hold->reserveId];
                        $holds[] = $myhold;
                    }
                }
            }
        }
        $view = $this->createViewModel(
            compact(
                'checkoutsUnavailable',
                'holdsUnavailable',
                'checkouts',
                'holds'
            )
        );

        $view->setTemplate('myresearch/odmycontent');
        return $view;
    }

    /**
     * Get Status Action
     * Supports the ajax getStatus calls
     *
     * @return array|bool|\Laminas\View\Model\ViewModel
     */
    public function getStatusAction()
    {
        $this->debug('ODC getStatus action');
        $ids = $this->params()->fromPost(
            'id',
            $this->params()->fromQuery('id', [])
        );
        $result = $this->connector->getAvailabilityBulk($ids);
        $view = $this->createViewModel(compact('ids', 'result'));
        $view->setTemplate('RecordDriver/SolrOverdrive/status-full');
        $this->layout()->setTemplate('layout/lightbox');
        return $view;
    }

    /**
     * Hold Action
     *
     * Hold Action handles all of the actions involving
     * Overdrive content including checkout, hold, cancel hold etc.
     *
     * @return array|bool|\Laminas\View\Model\ViewModel
     * @todo   Deal with situation that an unlogged in user requests
     *     an action but the action is no longer valid since they
     *     already have the content on hold/checked out or do not have access
     */
    public function holdAction()
    {
        $this->debug('ODC Hold action');

        if (!is_array($patron = $this->catalogLogin())) {
            return $patron;
        }
        $od_id = $this->params()->fromQuery('od_id');
        $rec_id = $this->params()->fromQuery('rec_id');
        $action = $this->params()->fromQuery('action');
        $edition = $this->params()->fromPost(
            'edition',
            $this->params()->fromQuery('edition', false)
        );
        $holdEmail = '';

        // Action comes in through the form
        if (null !== $this->params()->fromPost('doAction')) {
            $action = $this->params()->fromPost('doAction');
        }

        // Place hold action comes in through the form
        if (null !== $this->params()->fromPost('getTitleFormat')) {
            $format = $this->params()->fromPost('getTitleFormat');
        }

        $format = $this->params()->fromQuery('getTitleFormat');

        $this->debug("ODRC od_id=$od_id rec_id=$rec_id action=$action");
        // Load the Record Driver. Should be a SolrOverdrive driver.
        $driver = $this->getService(\VuFind\Record\Loader::class)->load(
            $rec_id
        );

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
        $actions = [
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

        if (isset($actions[$action])) {
            $actionTitleCode = $actions[$action]['titleCode'];
            $result = $actions[$action]['resMeth'] ? $this->{$actions[$action]['resMeth']}() : false;
        } else {
            $this->logWarning("overdrive action not defined: $action");
        }

        $view = $this->createViewModel(
            compact(
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
            )
        );

        $view->setTemplate('RecordDriver/SolrOverdrive/hold');
        return $view;
    }

    /**
     * Hold Confirm Result
     *
     * Get result of the action
     *
     * @return obj Result Object
     */
    public function getHoldConfirmRes()
    {
        $od_id = $this->params()->fromQuery('od_id');
        $result = $this->connector->getResultObject();
        // Check to make sure they don't already have this checked out.
        // Shouldn't need to refresh.
        if ($checkout = $this->connector->getCheckout($od_id, true)) {
            $result->status = false;
            $result->data = (object)[];
            $result->data->checkout = $checkout;
            $result->code = 'OD_CODE_ALREADY_CHECKED_OUT';
            $this->debug("title already checked out: $od_id");
        } elseif ($hold = $this->connector->getHold($od_id, true)) {
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
     * Confirm Checkout Result
     *
     * Get result of the action
     *
     * @return obj Result Object
     */
    public function getConfirmCheckoutRes()
    {
        $od_id = $this->params()->fromQuery('od_id');
        $rec_id = $this->params()->fromQuery('rec_id');
        // Load the Record Driver. Should be a SolrOverdrive driver.
        $driver = $this->getService(\VuFind\Record\Loader::class)->load(
            $rec_id
        );
        $formats = $driver->getDigitalFormats();
        // Looks like this is a magazine...
        if (current($formats)->id == 'magazine-overdrive') {
            $isMagazine = true;
            $result = $this->connector->getMagazineIssues($od_id, true);
            if ($result->status) {
                $issues = $result->data->products;
                $result->data->isMagazine = true;
            } else {
                $this->debug("couldn't get issues for checkout");
                $result->status = false;
                $result->code = 'OD_CODE_NOMAGISSUES';
                $result->msg = 'No magazine issue available.';
            }
        } else {
            $result = $this->connector->getResultObject();
            $result->data = (object)['isMagazine' => false];
            // Check to make sure they don't already have this checked out
            // shouldn't need to refresh.
            if ($checkout = $this->connector->getCheckout($od_id, false)) {
                $result->status = false;
                $result->data->checkout = $checkout;
                $result->code = 'OD_CODE_ALREADY_CHECKED_OUT';
            } elseif ($hold = $this->connector->getHold($od_id, false)) {
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
     * Checkout Result
     *
     * Get result of the action
     *
     * @return obj Result Object
     */
    public function getCheckoutRes()
    {
        $od_id = $this->params()->fromQuery('od_id');
        $edition = $this->params()->fromPost(
            'edition',
            $this->params()->fromQuery('edition', false)
        );
        if ($edition) {
            $od_id = $edition;
        }
        $result = $this->connector->doOverdriveCheckout($od_id);
        return $result;
    }

    /**
     * Place Hold Result
     *
     * Get result of the action
     *
     * @return obj Result Object
     */
    public function getPlaceHoldRes()
    {
        $od_id = $this->params()->fromQuery('od_id');
        $email = $this->params()->fromPost('email');
        $result = $this->connector->placeOverDriveHold($od_id, $email);
        if ($result->status) {
            $result->code = 'od_hold_place_success';
            $result->codeParams = ['%%holdListPosition%%' => $result->data->holdListPosition];
        } else {
            $result->code = 'od_hold_place_failure';
        }
        return $result;
    }

    /**
     * Edit Hold Email Confirm Result
     *
     * Get result of the action
     *
     * @return obj Result Object
     */
    public function getEditHoldEmailConfRes()
    {
        $od_id = $this->params()->fromQuery('od_id');
        $hold = $this->connector->getHold($od_id, true);
        $result = $this->connector->getResultObject(true);
        $result->data = (object)[];
        $result->data->hold = $hold;
        return $result;
    }

    /**
     * Edit Hold Email Result
     *
     * Get result of the action
     *
     * @return obj Result Object
     */
    public function getEditHoldEmailRes()
    {
        $od_id = $this->params()->fromQuery('od_id');
        $email = $this->params()->fromPost('email');
        $result = $this->connector->updateOverDriveHold($od_id, $email);
        $result->code = $result->status ? 'od_hold_update_success' : 'od_hold_update_failure';
        return $result;
    }

    /**
     * Return Title Confirmation Result
     *
     * Get result of the action
     *
     * @return obj Result Object
     */
    public function getReturnTitleConfirmResult()
    {
        $result = $this->connector->getResultObject();
        $rec_id = $this->params()->fromQuery('rec_id');
        // Load the SolrOverdrive driver.
        $driver = $this->getService(\VuFind\Record\Loader::class)->load(
            $rec_id
        );
        $formats = $driver->getDigitalFormats();
        $result->data = (current($formats)->id == 'magazine-overdrive') ?: false;
        return $result;
    }

    /**
     * Suspend Hold Result
     *
     * Get result of the action
     *
     * @return obj Result Object
     */
    public function getSuspendHoldRes()
    {
        $od_id = $this->params()->fromQuery('od_id');
        $suspendValue = $this->params()->fromPost('suspendValue');
        $hold = $this->connector->getHold($od_id, false);
        $holdEmail = $hold->emailAddress;
        $suspensionType = $suspendValue == -1 ? 'indefinite' : 'limited';
        $result = $this->connector->suspendHold($od_id, $holdEmail, $suspensionType, $suspendValue);

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
     * Edit Suspended Hold Result
     *
     * Get result of the action
     *
     * @return obj Result Object
     */
    public function getEditSuspendedRes()
    {
        $od_id = $this->params()->fromQuery('od_id');
        $suspendValue = $this->params()->fromPost('suspendValue');
        if ($suspendValue == 0) {
            $result = $this->connector->deleteHoldSuspension($od_id);
        } else {
            $hold = $this->connector->getHold($od_id, false);
            $holdEmail = $hold->emailAddress;
            $suspensionType = $suspendValue == -1 ? 'indefinite' : 'limited';
            $result = $this->connector->editSuspendedHold($od_id, $holdEmail, $suspensionType, $suspendValue);
        }
        $result->code = $result->status ? 'od_hold_update_success' : 'od_hold_update_failure';
        return $result;
    }

    /**
     * Cancel Hold Result
     *
     * Get result of the action
     *
     * @return obj Result Object
     */
    public function getCancelHoldRes()
    {
        $od_id = $this->params()->fromQuery('od_id');
        $result = $this->connector->cancelHold($od_id);
        $result->code = $result->status ? 'od_hold_cancel_success' : 'od_hold_cancel_failure';
        return $result;
    }

    /**
     * Return Title Result
     *
     * Get result of the action
     *
     * @return obj Result Object
     */
    public function getReturnTitleRes()
    {
        $od_id = $this->params()->fromQuery('od_id');
        $result = $this->connector->returnResource($od_id);
        $result->code = $result->status ? 'od_return_success' : 'od_return_failure';
        return $result;
    }

    /**
     * Download Title Result
     *
     * Get result of the action
     *
     * @return obj Result Object
     */
    public function getDownloadTitleRes()
    {
        $od_id = $this->params()->fromQuery('od_id');
        $result = $this->connector->getDownloadRedirect($od_id);
        $result->code = $result->status ? '' : 'od_gettitle_failure';
        return $result;
    }
}
