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

        // Get information from DSpace for each DB publication item
        $dspace = $this->serviceLocator->get(\TueFind\Service\DSpace::class);
        $dspace->login();

        $publications = [];
        $dbPublications = $this->getTable('publication')->getByUserId($user->id);
        foreach ($dbPublications as $dbPublication) {
            try {
                $dspacePublication = $dspace->getWorkspaceItem($dbPublication->external_document_id);
            } catch (exception $e) {
                $dspacePublication = null;
            }
            $publications[] = ['db' => $dbPublication, 'dspace' => $dspacePublication];
        }

        $viewParams = $this->getUserAuthoritiesAndRecords($user, /* $onlyGranted = */ true);
        $viewParams['publications'] = $publications;
        return $this->createViewModel($viewParams);
    }

    public function publishAction()
    {

        /*
        $metaArray = [
            "type"=>"Article",
            "language"=>"en",
            "author"=>"Kilian-Yasin, Katharina;02a88394-6161-44ce-a0c0-5f1640137bf4",
            "identifiers"=> "issn;identifiers text",
            "title"=>"Title test",
            "title.alternative"=>"Alternative Title test",
            "publisher"=>"Publisher text",
            "citation"=>"citation text",
            "ispartofseries"=> "Series/Report No. 1 test; Series/Report No. 2 test",
            "date.issued"=> "2022-03-18",
            "subject.keywords" => "Research Subject Categories::SOCIAL SCIENCES::Other social sciences::Labour market research",
            "abstract" => "Abstract text",
            "sponsorship" => "Sponsors text",
            "description" => "Description text"
            ];
        */

        $user = $this->getUser();
        if ($user == false) {
            return $this->forceLogin();
        }

        $uploadInfos = [];
        $uploadError = 0;
        $uploadFileSize = 500000;

        $dspace = $this->serviceLocator->get(\TueFind\Service\DSpace::class);
        $dspace->login();
        $config = $this->getConfig('tuefind');

        $existingRecord = null;
        $dublinCore = null;
        $existingRecordId = $this->params()->fromRoute('record_id', null);

        if (empty($existingRecordId)) {
            $uploadInfos[] = ["Control Number empty!","text-danger"];
            $uploadError = 1;
        } else {
            $existingRecord = $this->getRecordLoader()->load($existingRecordId);
            $dspaceMetadata = $this->serviceLocator->get(\VuFind\MetadataVocabulary\PluginManager::class)->get('DSpace')->getMappedData($existingRecord);
            $dublinCore = $this->serviceLocator->get(\VuFind\MetadataVocabulary\PluginManager::class)->get('DublinCore')->getMappedData($existingRecord);

            $termFileData = $this->getLatestTermFile();
            $action = $this->params()->fromPost('action');

            $dbPublications = $this->getTable('publication')->getByControlNumber($existingRecordId);
            if (!empty($dbPublications->external_document_id)) {
                $uploadInfos[] = ["Publication File exist!","text-danger"];
                $uploadError = 1;
            } else if ($action == 'publish' && $uploadError == 0) {
                $uploadedFile = $this->params()->fromFiles('file');

                $collectionName = $config->Publication->collection_name;

                $collection = $dspace->getCollectionByName($collectionName);
                if (isset($collection->id)) {
                    $collectionID = $collection->id;
                }

                if ($uploadedFile['type'] != "application/pdf") {
                    $uploadInfos[] = ["Invalid file type!: " . $uploadedFile['type'],"text-danger"];
                    $uploadError = 1;
                }

                if ($uploadedFile['size'] > $uploadFileSize) {
                    $uploadInfos[] = ["File is too big!","text-danger"];
                    $uploadError = 1;
                }

                if ($uploadError == 0) {
                    $tmpdir = sys_get_temp_dir();
                    $tmpfile = $tmpdir . '/' . $uploadedFile['name'];

                    if (is_file($tmpfile)) {
                        unlink($tmpfile);
                    }
                    if (!move_uploaded_file($uploadedFile['tmp_name'], $tmpfile)) {
                        throw new \Exception('Uploaded file could not be moved to tmp directory!');
                    }

                    $workspaceItem = $dspace->addWorkspaceItem($tmpfile, $collectionID);
                    $itemID = $workspaceItem->id;
                    $item = $dspace->updateWorkspaceItem($itemID, $dspaceMetadata);

                    $dbPublications = $this->getTable('publication')->addPublication($user->id, $existingRecordId, $itemID, $termFileData['termDate']);

                    $uploadInfos[] = ["Publication File success!","text-success"];
                    // TODO: Start publication process in DSpace after metadata is correct

                    $dspace->addWorkflowItem($itemID);

                }
            }
        }

        $view = $this->createViewModel($this->getUserAuthoritiesAndRecords($user, /* $onlyGranted = */ true, /* $exceptionIfEmpty = */ true));
        $view->existingRecord = $existingRecord;
        $view->dublinCore = $dublinCore;
        $view->uploadInfos = $uploadInfos;
        $view->termFile = $termFileData;
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
