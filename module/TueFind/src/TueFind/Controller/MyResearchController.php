<?php
namespace TueFind\Controller;

class MyResearchController extends \VuFind\Controller\MyResearchController
{
    protected function getUserAuthoritiesAndRecords($user, $onlyGranted=false, $exceptionIfEmpty=false): array
    {
        $table = $this->getTable('user_authority');

        $accessState = $onlyGranted ? 'granted' : null;
        $userAuthorities = $table->getByUserId($user->id, $accessState);

        if ($exceptionIfEmpty && count($userAuthorities) == 0) {
            throw new \Exception('No authority linked to this user!');
        }

        $authorityRecords = [];
        foreach ($userAuthorities as $userAuthority) {
            $authorityRecords[$userAuthority['authority_id']] = $this->getRecordLoader()
                ->load($userAuthority['authority_id'], 'SolrAuth');
        }

        return ['userAuthorities' => $userAuthorities, 'authorityRecords' => $authorityRecords];
    }

    public function newsletterAction()
    {
        $user = $this->getUser();
        if ($user == false) {
            return $this->forceLogin();
        }

        $submitted = $this->formWasSubmitted('submit');
        if ($submitted) {
            $user->setSubscribedToNewsletter(boolval($this->getRequest()->getPost()->subscribed));
        }

        return $this->createViewModel(['subscribed' => $user->hasSubscribedToNewsletter(),
                                       'submitted'  => $submitted]);
    }

    public function publicationsAction()
    {
        $user = $this->getUser();
        if ($user == false) {
            return $this->forceLogin();
        }

        $config = $this->getConfig('tuefind');
        $dspaceServer = $config->Publication->dspace_url_base;

        $authorityUsers = $this->getTable('user_authority')->getByUserId($user->id);
        $authorityUsersArray = [];
        foreach($authorityUsers as $authorityUser) {
            $authorityUserLoader = $this->serviceLocator->get(\VuFind\Record\Loader::class)->load($authorityUser->authority_id, 'SolrAuth');
            $authorityUsersArray[] = [
                'id'=>$authorityUser->authority_id,
                'access_state'=>$authorityUser->access_state,
                'title'=>$authorityUserLoader->getTitle()
            ];
        }
        $publications = [];
        $dbPublications = $this->getTable('publication')->getByUserId($user->id);
        foreach ($dbPublications as $dbPublication) {
            $existingRecord = $this->getRecordLoader()->load($dbPublication->control_number);
            $dbPublication['title'] = $existingRecord->getTitle();
            $publications[] = $dbPublication;
        }

        $viewParams = $this->getUserAuthoritiesAndRecords($user, /* $onlyGranted = */ true);
        $viewParams['publications'] = $publications;
        $viewParams['dspaceServer'] = $dspaceServer;
        $viewParams['authorityUsers'] = $authorityUsersArray;
        return $this->createViewModel($viewParams);
    }

    public function publishAction()
    {
        $user = $this->getUser();
        if ($user == false) {
            return $this->forceLogin();
        }

        $showForm = true;
        $uploadMaxFileSize = 500000;
        $config = $this->getConfig('tuefind');
        $dspaceServer = $config->Publication->dspace_url_base;

        // 1) Get metadata to show form
        $existingRecordId = $this->params()->fromRoute('record_id', null);
        if (empty($existingRecordId)) {
            throw new \Exception('record_id is empty!');
        }
        $existingRecord = $this->getRecordLoader()->load($existingRecordId);

        $dbPublications = $this->getTable('publication')->getByControlNumber($existingRecordId);
        if (!empty($dbPublications->external_document_id)) {
            $this->flashMessenger()->addMessage(['msg' => "Publication already exists: <a href='".$dspaceServer."/handle/".$dbPublications->external_document_id."' target='_blank'>click here to go to file</a>", 'html' => true], 'error');
            $uploadError = true;
            $showForm = false;
        }

        $termFileData = $this->getLatestTermFile();
        $action = $this->params()->fromPost('action');

        // 2) Process upload action (if form was submitted)
        if ($action == 'publish') {
            // Check uploaded file (+ do some preparations)
            $uploadError = false;
            $uploadedFile = $this->params()->fromFiles('file');
            $PDFMediaTypesArray = ['application/pdf', 'application/x-pdf', 'application/x-bzpdf', 'application-gzpdf'];
            if (!in_array($uploadedFile['type'], $PDFMediaTypesArray)) {
                $this->flashMessenger()->addMessage('Only PDF files allowed.', 'error');
                $uploadError = true;
            }
            if ($uploadedFile['size'] > $uploadMaxFileSize) {
                $this->flashMessenger()->addMessage('File is too big!', 'error');
                $uploadError = true;
            }

            if (!$uploadError) {
                $tmpdir = sys_get_temp_dir();
                $tmpfile = $tmpdir . '/' . $uploadedFile['name'];

                if (is_file($tmpfile)) {
                    unlink($tmpfile);
                }
                if (!move_uploaded_file($uploadedFile['tmp_name'], $tmpfile)) {
                    throw new \Exception('Uploaded file could not be moved to tmp directory!');
                }

                // For DSpace 6:
                // - add Item (including metadata)
                // - add Bitstream (=file)
                // For DSpace 7:
                // - add Workspace Item (including file)
                // - update Workspace Item (= update metadata)
                // - add Workflow Item (= start workflow for the generated item)
                //
                // Also, be aware that ID + URL schemas might be different when switching between the versions.
                //
                // The following implementation is based on DSpace 6:
                $dspace = $this->serviceLocator->get(\TueFind\Service\DSpace6::class);
                $dspace->login();
                $collectionName = $config->Publication->collection_name;
                $collection = $dspace->getCollectionByName($collectionName);
                $dspaceMetadata = $this->serviceLocator->get(\VuFind\MetadataVocabulary\PluginManager::class)->get('DSpace6')->getMappedData($existingRecord);
                $item = $dspace->addItem($collection->uuid, $dspaceMetadata);
                
                $bitstream = $dspace->addBitstream($item->uuid, basename($tmpfile), $tmpfile);
                $dbPublications = $this->getTable('publication')->addPublication($user->id, $existingRecordId, $item->handle, $item->uuid, $termFileData['termDate']);

                if(!strpos($item->handle, '/')) {
                  $publicationURL = $dspaceServer."/handle/".$item->handle;
                }else{
                  $publicationURL = $dspaceServer."/xmlui/handle/".$item->handle;
                }

                // Store information in database
                $this->flashMessenger()->addMessage(['msg' => "Publication successfully created: <a href='".$publicationURL."' target='_blank'>click here go to file</a>", 'html' => true], 'success');
                $showForm = false;
            }
        }

        // 3) Generate view
        $view = $this->createViewModel($this->getUserAuthoritiesAndRecords($user, /* $onlyGranted = */ true, /* $exceptionIfEmpty = */ true));
        $dublinCore = $this->serviceLocator->get(\VuFind\MetadataVocabulary\PluginManager::class)->get('DublinCore')->getMappedData($existingRecord);
        $userAuthorities = [];
        foreach ($view->userAuthorities as $userAuthority) {
            $selected = false;
            $authorityRecord = $view->authorityRecords[$userAuthority['authority_id']];
            $GNDNumber = $authorityRecord->getGNDNumber();
            $authorityTitle = htmlspecialchars($authorityRecord->getTitle());
            foreach ($dublinCore['DC.creator'] as $creator) {
                if ($authorityTitle == $creator) {
                    $selected = true;
                }
            }
            $userAuthorities[] = [
                'authority_id' => $userAuthority['authority_id'],
                'authority_title' => $authorityTitle,
                'authority_GNDNumber' => $GNDNumber,
                'select_title' => $authorityTitle . ' (GND: ' .  $GNDNumber . ')',
                'selected' => $selected
            ];
        }

        $view->showForm = $showForm;
        $view->userAuthorities = $userAuthorities;
        $view->existingRecord = $existingRecord;
        $view->dublinCore = $dublinCore;
        $view->termFile = $termFileData;
        $view->recordLanguages = $existingRecord->getLanguages();
        return $view;
    }

    public function rssFeedSettingsAction()
    {
        $user = $this->getUser();
        if ($user == false) {
            return $this->forceLogin();
        }

        $dbTablePluginManager = $this->serviceLocator->get(\VuFind\Db\Table\PluginManager::class);
        $rssSubscriptionsTable = $dbTablePluginManager->get('rss_subscription');
        $rssFeedsTable = $dbTablePluginManager->get('rss_feed');
        $action = $this->getRequest()->getPost('action', '');
        $feedId = $this->getRequest()->getPost('id', '');
        if ($action == 'add') {
            $rssSubscriptionsTable->addSubscription($user->id, $feedId);
        } elseif ($action == 'remove') {
            $rssSubscriptionsTable->removeSubscription($user->id, $feedId);
        } elseif ($action == 'subscribe_email') {
            $user->setRssFeedSendEmails(true);
        } elseif ($action == 'unsubscribe_email') {
            $user->setRssFeedSendEmails(false);
        }

        return $this->createViewModel(['rssFeeds' => $rssFeedsTable->getFeedsSortedByName(),
                                       'rssSubscriptions' => $rssSubscriptionsTable->getSubscriptionsForUserSortedByName($user->id),
                                       'user' => $user]);
    }

    public function rssFeedPreviewAction()
    {
        $user = $this->getUser();
        if ($user == false) {
            return $this->forceLogin();
        }

        $rssTable = $this->serviceLocator->get(\VuFind\Db\Table\PluginManager::class)->get('rss_item');
        $rssItems = $rssTable->getItemsForUserSortedByPubDate($user->id);
        return $this->createViewModel(['user' => $user,
                                       'rssItems' => $rssItems,
                                       'page' => $this->params()->fromQuery('page') ?? 1]);
    }

    /**
     * This method can be used to access a user's personal RSS feed without a login,
     * for use in e.g. a RSS reader. Instead of using the user_id, we rather use the uuid
     * for privacy reasons:
     * - The user_id might be shown to other users in hyperlinks (e.g. if tags are enabled)
     * - The user_id might be guessed more easily by a brute force attack
     */
    public function rssFeedRawAction()
    {
        $userUuid = $this->params()->fromRoute('user_uuid');
        $user = $this->serviceLocator->get(\VuFind\Db\Table\PluginManager::class)->get('user')->getByUuid($userUuid);
        $instance = $this->serviceLocator->get('ViewHelperManager')->get('tuefind')->getTueFindInstance();
        $cmd = '/usr/local/bin/rss_subset_aggregator --mode=rss_xml ' . escapeshellarg($user->id) . ' ' . escapeshellarg($instance);

        // We need to explicitly pass through VUFIND_HOME, or database.conf cannot be found
        putenv('VUFIND_HOME=' . getenv('VUFIND_HOME'));
        exec($cmd, $rssFeedContentArray, $return_var);
        $rssFeedContentString = implode('', $rssFeedContentArray);

        $response = $this->getResponse();
        $response->getHeaders()->addHeaderLine('Content-type', 'text/xml');
        $response->setContent($rssFeedContentString);
        return $response;
    }

    private function getLatestTermFile(): array
    {
        $termsDir =  $_SERVER['CONTEXT_DOCUMENT_ROOT'] . '/publication_terms/';
        $files = scandir($termsDir);
        $latestTermFileData = [];
        $latestTermData = [];
        foreach ($files as $file) {
            if (preg_match('/(\d{4})(\d{2})(\d{2})/', $file, $matches)) {
                $formatedDate = $matches[1] . "-" . $matches[2] . "-" . $matches[3];
                $timeStamp = strtotime($formatedDate);
                $latestTermData[] = [
                    "milliseconds"=>$timeStamp,
                    "termDate"=>$formatedDate,
                    "fileName"=>$file
                ];
            }
        }
        if (empty($latestTermData)) {
            throw new \Exception('Latest term file not found in: ' . $termsDir);
        }

        usort($latestTermData, function ($a, $b) {
            return strnatcmp($a['milliseconds'], $b['milliseconds']);
        });
        $latestTermFileData = $latestTermData[0];

        return $latestTermFileData;
    }
}
