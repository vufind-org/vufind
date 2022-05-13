<?php

namespace IxTheo\Controller;

class RecordController extends \TueFind\Controller\RecordController
{
    function processSubscribe()
    {
        if (!($user = $this->getUser())) {
            return $this->forceLogin();
        }
        $post = $this->getRequest()->getPost()->toArray();
        $results = $this->loadRecord()->subscribe($post, $user);

        if ($results == null)
            return $this->createViewModel();

        $this->flashMessenger()->addMessage("Success", 'success');
        return $this->redirectToRecord();
    }

    function processUnsubscribe()
    {
        if (!($user = $this->getUser())) {
            return $this->forceLogin();
        }
        $post = $this->getRequest()->getPost()->toArray();
        $this->loadRecord()->unsubscribe($post, $user);

        $this->flashMessenger()->addMessage("Success", 'success');
        return $this->redirectToRecord();
    }

    function subscribeAction()
    {
        // Process form submission:
        if ($this->params()->fromPost('action') == 'subscribe') {
            return $this->processSubscribe();
        } else if ($this->params()->fromPost('action') == 'unsubscribe') {
            return $this->processUnsubscribe();
        }

        // Retrieve user object and force login if necessary:
        if (!($user = $this->getUser())) {
            return $this->forceLogin();
        }
        $driver = $this->loadRecord();
        $table = $driver->getDbTable('Subscription');
        $recordId = $driver->getUniqueId();
        $userId = $user->id;

        $infoText = $this->forward()->dispatch('Content', [
            'action' => 'content',
            'page' => 'SubscriptionInfoText'
        ]);

        $subscribed = boolval($table->findExisting($userId, $recordId));
        $bundles = [];
        foreach($driver->getBundleIds() as $bundle) {
            if (boolval($table->findExisting($userId, $bundle))) {
                $bundles[] = $bundle;
            }
        }

        return $this->createViewModel(["subscribed" => $subscribed,
                                       "bundles" => $bundles,
                                       "infoText" => $infoText]);
    }

    function processPDASubscribe()
    {
        if (!($user = $this->getUser())) {
            return $this->forceLogin();
        }
        $post = $this->getRequest()->getPost()->toArray();
        $data = [];
        $results = $this->loadRecord()->pdaSubscribe($post, $user, $data);
        if ($results == null) {
            return $this->createViewModel();
        }
        $id = $this->loadRecord()->getRecordID();
        $notifier = $this->PDASubscriptions();
        $notifier->sendPDANotificationEmail($post, $user, $data, $id);
        $notifier->sendPDAUserNotificationEmail($post, $user, $data, $id);
        $this->flashMessenger()->addMessage("Success", 'success');
        return $this->redirectToRecord();
    }

    function processPDAUnsubscribe()
    {
        if (!($user = $this->getUser())) {
            return $this->forceLogin();
        }
        $post = $this->getRequest()->getPost()->toArray();
        $this->loadRecord()->pdaUnsubscribe($post, $user);
        $id = $this->loadRecord()->getRecordID();
        $notifier = $this->PDASubscriptions();
        $notifier->sendPDAUnsubscribeEmail($user, $id);
        $notifier->sendPDAUserUnsubscribeEmail($user, $id);
        $this->flashMessenger()->addMessage("Success", 'success');
        return $this->redirectToRecord();
    }

    function pdasubscribeAction()
    {
        // Process form submission:
        if ($this->params()->fromPost('action') == 'pdasubscribe') {
            return $this->processPDASubscribe();
        } else if ($this->params()->fromPost('action') == 'pdaunsubscribe') {
            return $this->processPDAUnsubscribe();
        }

        // Retrieve user object and force login if necessary:
        if (!($user = $this->getUser())) {
            return $this->forceLogin();
        }
        $driver = $this->loadRecord();
        $table = $driver->getDbTable('PDASubscription');
        $recordId = $driver->getUniqueId();
        $userId = $user->id;

        $infoText = $this->forward()->dispatch('Content', [
            'action' => 'content',
            'page' => 'PDASubscriptionInfoText'
        ]);
        $bookDescription = $driver->getAuthorsAsString() . ": " .
                           $driver->getTitle() .  ($driver->getYear() != "" ? "(" . $driver->getYear() . ")" : "") .
                           ", ISBN: " . $driver->getISBNs()[0];
        return $this->createViewModel(["pdasubscription" => !($table->findExisting($userId, $recordId)), "infoText" => $infoText,
                                       "bookDescription" => $bookDescription]);
    }


    // Use custom logic "useExportOutputAsParameter" for export redirects
    public function exportAction()
    {
        $driver = $this->loadRecord();
        $view = $this->createViewModel();
        $format = $this->params()->fromQuery('style');

        // Display export menu if missing/invalid option
        $export = $this->serviceLocator->get('VuFind\Export');
        if (empty($format) || !$export->recordSupportsFormat($driver, $format)) {
            if (!empty($format)) {
                $this->flashMessenger()
                    ->addMessage('export_invalid_format', 'error');
            }
            $view->setTemplate('record/export-menu');
            return $view;
        }

        // If this is an export format that redirects to an external site, perform
        // the redirect now (unless we're being called back from that service!):
        if ($export->needsRedirect($format)
            && !$this->params()->fromQuery('callback')
        )  {
            if ($export->useExportOutputAsParameter($format)) {
                $query_parameter = ($this->getViewRenderer()->plugin('record')($driver))->getExport($format);
                return $this->redirect()
                 ->toUrl($export->getRedirectUrl($format, $query_parameter));
            }
            else {
             // Build callback URL:
             $parts = explode('?', $this->getServerUrl(true));
             $callback = $parts[0] . '?callback=1&style=' . urlencode($format);
             return $this->redirect()
                 ->toUrl($export->getRedirectUrl($format, $callback));
            }
        }

        // Send appropriate HTTP headers for requested format:
        $response = $this->getResponse();
        $response->getHeaders()->addHeaders($export->getHeaders($format));

        // Actually export the record
        $recordHelper = $this->getViewRenderer()->plugin('record');
        $response->setContent($recordHelper($driver)->getExport($format));
        return $response;
}


}
