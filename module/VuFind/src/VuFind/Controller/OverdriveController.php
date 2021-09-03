<?php
/**
 * Overdrive Controller
 *
 * PHP version 7
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
        $this->debug("ODRC constructed");
    }

    /**
     * My Content Action
     * Prepares the view for the Overdrive MyContent template.
     *
     * @return array|bool|\Laminas\View\Model\ViewModel
     */
    public function mycontentAction()
    {
        $this->debug("ODC mycontent action");
        //force login
        if (!is_array($patron = $this->catalogLogin())) {
            return $patron;
        }
        $holds = [];
        $checkouts = [];
        $checkoutsUnavailable = false;
        $holdsUnavailable = false;

        //check on this patrons's access to Overdrive
        $odAccessResult = $this->connector->getAccess();

        if (!$odAccessResult->status) {
            $this->debug("result:" . print_r($odAccessResult, true));
            $this->flashMessenger()->addErrorMessage(
                $this->translate(
                    $odAccessResult->code,
                    ["%%message%%" => $odAccessResult->msg]
                )
            );
            $checkoutsUnavailable = true;
            $holdsUnavailable = true;
        }

        if ($odAccessResult->status) {
            //get the current Overdrive checkouts
            //for this user and add to our array of IDS
            $checkoutResults = $this->connector->getCheckouts(true);
            if (!$checkoutResults->status) {
                $this->flashMessenger()->addMessage(
                    $checkoutResults->code,
                    'error'
                );
                $checkoutsUnavailable = true;
            } else {
                foreach ($checkoutResults->data as $checkout) {
                    $mycheckout['checkout'] = $checkout;
                    $mycheckout['record']
                        = $this->serviceLocator->get(\VuFind\Record\Loader::class)
                        ->load(strtolower($checkout->reserveId));
                    $checkouts[] = $mycheckout;
                }
            }
            //get the current Overdrive holds for this user and add to
            // our array of IDS
            $holdsResults = $this->connector->getHolds(true);
            if (!$holdsResults->status) {
                if ($checkoutResults->status) {
                    $this->flashMessenger()->addMessage(
                        $holdsResults->code,
                        'error'
                    );
                }
                $holdsUnavailable = true;
            } else {
                foreach ($holdsResults->data as $hold) {
                    $myhold['hold'] = $hold;
                    $myhold['record']
                        = $this->serviceLocator->get(\VuFind\Record\Loader::class)
                        ->load(strtolower($hold->reserveId));
                    $holds[] = $myhold;
                }
            }
        }
        //Future: get reading history will be here
        //Future: get hold and checkoutlimit using the Patron Info API

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
        $this->debug("ODC getStatus action");
        $ids = $this->params()->fromPost(
            'id',
            $this->params()->fromQuery('id', [])
        );
        $this->debug("ODRC availability for :" . print_r($ids, true));
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
     *     already have the content on hold/checked out or do not have acceess
     */
    public function holdAction()
    {
        $this->debug("ODC Hold action");

        if (!is_array($patron = $this->catalogLogin())) {
            return $patron;
        }
        $this->debug("patron: " . print_r($patron, true));

        $od_id = $this->params()->fromQuery('od_id');
        $rec_id = $this->params()->fromQuery('rec_id');
        $action = $this->params()->fromQuery('action');

        //place hold action comes in through the form
        if (null !== $this->params()->fromPost('doAction')) {
            $action = $this->params()->fromPost('doAction');
        }

        //place hold action comes in through the form
        if (null !== $this->params()->fromPost('getTitleFormat')) {
            $format = $this->params()->fromPost('getTitleFormat');
        }

        $format = $this->params()->fromQuery('getTitleFormat');

        $this->debug("ODRC od_id=$od_id rec_id=$rec_id action=$action");
        //load the Record Driver.  Should be a SolrOverdrive  driver.
        $driver = $this->serviceLocator->get(\VuFind\Record\Loader::class)->load(
            $rec_id
        );

        $formats = $driver->getDigitalFormats();
        $title = $driver->getTitle();
        $cover = $driver->getThumbnail('small');
        $listAuthors = $driver->getPrimaryAuthors();
        if (!$action) {
            //double check the availability in case it
            //has changed since the page was loaded.
            $avail = $driver->getOverdriveAvailability();
            if ($avail->copiesAvailable > 0) {
                $action = "checkoutConfirm";
            } else {
                $action = "holdConfirm";
            }
        }

        if ($action == "checkoutConfirm") {
            $result = $this->connector->getResultObject();
            //check to make sure they don't already have this checked out
            //shouldn't need to refresh.
            if ($checkout = $this->connector->getCheckout($od_id, false)) {
                $result->status = false;
                $result->data->checkout = $checkout;
                $result->code = "OD_CODE_ALREADY_CHECKED_OUT";
            } elseif ($hold = $this->connector->getHold($od_id, false)) {
                $result->status = false;
                $result->data->hold = $hold;
                $result->code = "OD_CODE_ALREADY_ON_HOLD";
            } else {
                $result->status = true;
            }
            $actionTitleCode = "od_checkout";
        } elseif ($action == "holdConfirm") {
            $result = $this->connector->getResultObject();
            //check to make sure they don't already have this checked out
            //check to make sure they don't already have this checked out
            //shouldn't need to refresh.
            if ($checkout = $this->connector->getCheckout($od_id, false)) {
                $result->status = false;
                $result->data->checkout = $checkout;
                $result->code = "OD_CODE_ALREADY_CHECKED_OUT";
                $this->debug("title already checked out: $od_id");
            } elseif ($hold = $this->connector->getHold($od_id, false)) {
                $result->status = false;
                $result->data->hold = $hold;
                $result->code = "OD_CODE_ALREADY_ON_HOLD";
                $this->debug("title already on hold: $od_id");
            } else {
                $result->status = true;
            }
            $actionTitleCode = "od_hold";
        } elseif ($action == "cancelHoldConfirm") {
            $actionTitleCode = "od_cancel_hold";
        } elseif ($action == "returnTitleConfirm") {
            $actionTitleCode = "od_early_return";
        } elseif ($action == "getTitleConfirm") {
            //get only formats that are available...
            $formats = $driver->getAvailableDigitalFormats();
            $actionTitleCode = "od_get_title";
        } elseif ($action == "doCheckout") {
            $actionTitleCode = "od_checkout";
            $result = $this->connector->doOverdriveCheckout($od_id);
        } elseif ($action == "placeHold") {
            $actionTitleCode = "od_hold";
            $email = $this->params()->fromPost('email');
            $result = $this->connector->placeOverDriveHold($od_id, $email);
        } elseif ($action == "cancelHold") {
            $actionTitleCode = "od_cancel_hold";
            $result = $this->connector->cancelHold($od_id);
        } elseif ($action == "returnTitle") {
            $actionTitleCode = "od_early_return";
            $result = $this->connector->returnResource($od_id);
        } elseif ($action == "getTitle") {
            $actionTitleCode = "od_get_title";
            //need to get server name etc.  maybe this: getServerUrl();
            $this->debug(
                "Get Title action.  Getting downloadlink using" .
                $this->getServerUrl('overdrive-hold')
            );
            $result = $this->connector->getDownloadLink(
                $od_id,
                $format,
                $this->getServerUrl('overdrive-hold')
            );
            if ($result->status) {
                //Redirect to resource
                $url = $result->data->downloadLink;
                $this->debug("redirecting to: $url");
                return $this->redirect()->toUrl($url);
            }
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
                'listAuthors'
            )
        );

        $view->setTemplate('RecordDriver/SolrOverdrive/hold');
        return $view;
    }
}
