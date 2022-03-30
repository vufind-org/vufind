<?php

namespace TueFind\Controller;

class MyResearchController extends \VuFind\Controller\MyResearchController
{
    protected function getUserAuthoritiesAndRecords($user, $onlyGranted=false, $exceptionIfEmpty=false): array
    {
        $table = $this->getTable('user_authority');

        $accessState = $onlyGranted ? 'granted' : null;
        $userAuthorities = $table->getByUserId($user->id, $accessState);

        if ($exceptionIfEmpty && count($userAuthorities) == 0)
            throw new \Exception('No authority linked to this user!');

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
        if ($submitted)
            $user->setSubscribedToNewsletter(boolval($this->getRequest()->getPost()->subscribed));

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

    public function build_sorter($key) {
        return function ($a, $b) use ($key) {
            return strnatcmp($a[$key], $b[$key]);
        };
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
        $controlNumber = null;
        $existingRecordId = $this->params()->fromRoute('record_id', null);
        if ($existingRecordId != null) {
            $existingRecord = $this->getRecordLoader()->load($existingRecordId);
            $dublinCore = $this->serviceLocator->get(\VuFind\MetadataVocabulary\PluginManager::class)->get('DublinCore')->getMappedData($existingRecord);
            $controlNumber = $dublinCore['DC.identifier'][0];
        }

        $action = $this->params()->fromPost('action');

        $termFileData = $this->getLatestTermFile();

        $dbPublications = $this->getTable('publication')->getByControlNumber($controlNumber);
        if(!empty($dbPublications->external_document_id)){
            $uploadInfos[] = ["Publication File exist!","text-danger"];
            $uploadError = 1;
        }

        if ($action == 'publish') {

            $uploadedFile = $this->params()->fromFiles('file');
            $userFileName = $dublinCore['DC.title'][0];

            $collectionName = $config->Publication->collection_name;

            $collection = $dspace->getCollectionByName($collectionName);
            if(isset($collection->id)) {
              $collectionID = $collection->id;
            }

            if($uploadedFile['type'] != "application/pdf") {
                $uploadInfos[] = ["Invalid file type!: ".$uploadedFile['type'],"text-danger"];
                $uploadError = 1;
            }

            if($uploadedFile['size'] >  $uploadFileSize) {
                $uploadInfos[] = ["File is too big!","text-danger"];
                $uploadError = 1;
            }

            $uploaddir = $_SERVER['CONTEXT_DOCUMENT_ROOT'].'/dspace';

            if (!is_dir($uploaddir)) {
                $uploadError = 1;
            }

            $customFileName = strtotime("now").".pdf";
            $uploadfile = $uploaddir ."/". $customFileName;

            if (move_uploaded_file($uploadedFile['tmp_name'], $uploadfile) && $uploadError == 0) {
                if (file_exists($uploadfile)) {
                    $workspaceItem = $dspace->addWorkspaceItem($uploadfile, $collectionID);
                    $itemID = $workspaceItem->id;
                    $dbPublications = $this->getTable('publication')->addPublication($user->id, $controlNumber, $itemID, $termFileData['termDate']);

                    $language = 'en';
                    if(isset($dublinCore['DC.language'][0])) {
                        switch($dublinCore['DC.language'][0]){
                            case"German":
                                $language = 'de';
                            break;
                        }
                    }

                    $metaArray = [
                        "title"=>$userFileName,
                        "language"=>$language,
                        "publisher"=>$dublinCore['DC.publisher'][0],
                        "author"=>$dublinCore['DC.creator'][0].";02a88394-6161-44ce-a0c0-5f1640137bf4",
                        "identifiers"=>"issn;".$controlNumber

                    ];

                    $patchData = [];
                    foreach($metaArray as $metaKey=>$metaValue) {
                        $this->generateMetaData($metaKey,$metaValue,$patchData);
                    }
                    $patchDataJson = json_encode($patchData);
                    $item = $dspace->updateWorkspaceItem($itemID,$patchDataJson);
                    $this->removeDspaceFile();
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

    private function getLatestTermFile(): array {

        $termsDir =  $_SERVER['CONTEXT_DOCUMENT_ROOT'].'/publication_terms/';
        $files = scandir($termsDir);
        $latestTermFileData = [];
        $latestTermData = [];
        foreach($files as $file){
            if(strlen($file) > 3) { //remove system files (. ..)
                preg_match_all('/(\d{4})(\d{2})(\d{2})/',$file,$matches,PREG_PATTERN_ORDER);
                if(isset($matches[0])){
                    $formatedDate = $matches[1][0]."-".$matches[2][0]."-".$matches[3][0];
                    $timeStamp = strtotime($formatedDate);
                    $latestTermData[] = [
                        "milliseconds"=>$timeStamp,
                        "termDate"=>$formatedDate,
                        "fileName"=>$file
                    ];
                }
            }
        }
        if(!empty($latestTermData)) {
            usort($latestTermData, $this->build_sorter('milliseconds'));
            $latestTermFileData = $latestTermData[0];
        }
        return $latestTermFileData;
    }

    private function removeDspaceFile(): void {

        $dspaceDir = $_SERVER['CONTEXT_DOCUMENT_ROOT'].'/dspace/';
        $files = scandir($dspaceDir);
        foreach($files as $file){
            if(strlen($file) > 3) { //
                unlink($dspaceDir.$file);
            }
        }

    }

    private function generateMetaData($metaKey,$metaValue,&$dataArray): void {

        $oneMetaArray = [];

        $op = 'add';
        $language = NULL;
        $authority = NULL;
        $confidence = -1;
        $place = 0;
        $otherInformation = NULL;
        $path = '';

        switch($metaKey) {
            case"title":
                $path = '/sections/traditionalpageone/dc.title';
            break;
            case"title.alternative":
                $path = '/sections/traditionalpageone/dc.title.alternative';
            break;
            case"publisher":
                $path = '/sections/traditionalpageone/dc.publisher';
            break;
            case"citation":
                $path = '/sections/traditionalpageone/dc.identifier.citation';
            break;
            case"ispartofseries":
                $path = '/sections/traditionalpageone/dc.relation.ispartofseries';
            break;
            case"date.issued":
                $path = '/sections/traditionalpageone/dc.date.issued';
            break;
            case"subject.keywords":
                $path = '/sections/traditionalpagetwo/dc.subject';
            break;
            case"abstract":
                $path = '/sections/traditionalpagetwo/dc.description.abstract';
            break;
            case"description":
                $path = '/sections/traditionalpagetwo/dc.description';
            break;
            case"sponsorship":
                $path = '/sections/traditionalpagetwo/dc.description.sponsorship';
            break;
            case"type":
                $path = '/sections/traditionalpageone/dc.type';
            break;
            case"language":
                $path = '/sections/traditionalpageone/dc.language.iso';
            break;
            case"author":
                $path = '/sections/traditionalpageone/dc.contributor.author';
                $confidence = 600;
                $explodeValue = explode(';',$metaValue);
                $metaValue = $explodeValue[0];
                $authority = $explodeValue[1];
            break;
            case"identifiers":
                $explodeValue = explode(';',$metaValue);
                $metaValue = $explodeValue[1];
                $identifierType = $explodeValue[0];
                if($identifierType == 'issn') {
                    $path = '/sections/traditionalpageone/dc.identifier.issn';
                }else{
                    $path = '/sections/traditionalpageone/dc.identifier.other';
                }
            break;
        }

        $oneMetaArray = [
            'op' => $op,
            'path' => $path,
            'value' =>
              [
                  [
                      'value' => $metaValue,
                      'language' => $language,
                      'authority' => $authority,
                      'display' => $metaValue,
                      'confidence' => $confidence,
                      'place' => $place,
                      'otherInformation' => $otherInformation
                  ]
              ]
        ];

        $dataArray[] = $oneMetaArray;
    }

}
