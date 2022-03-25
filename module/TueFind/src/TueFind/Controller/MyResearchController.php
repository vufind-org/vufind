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

    public function publishAction()
    {
        $user = $this->getUser();
        if ($user == false) {
            return $this->forceLogin();
        }

        $uploadInfos = [];
        $uploadError = 0;

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

        if ($action == 'publish') {

            $uploadedFile = $this->params()->fromFiles('file');

            $userFileName = $dublinCore['DC.title'][0];

            $dspace = $this->serviceLocator->get(\TueFind\Service\DSpace::class);
            $dspace->login();
            // TODO: Upload PDF file to DSpace

            $collectionName = $config->Publication->collection_name;

            $uploadInfos[] = ["Collection name: ".$collectionName,"text-secondary"];

            $collectionID = "";

            $collections = $dspace->getCollections();

            foreach($collections->_embedded->collections as $collection) {
                if($collection->name == $collectionName) {
                    $collectionID = $collection->id;
                    $uploadInfos[] = ["Collection ID: ".$collectionID,"text-secondary"];
                }
                //print $collection->name ." - " .$collection->id."<br />";
            }

            if($uploadedFile['type'] != "application/pdf") {
                $uploadInfos[] = ["Invalid file type!: ".$uploadedFile['type'],"text-danger"];
                $uploadError = 1;
            }else{
                $uploadInfos[] = ["File type correct: ".$uploadedFile['type'],"text-success"];
            }

            if($uploadedFile['size'] >  500000) {
                $uploadInfos[] = ["File is too big! size:".$uploadedFile['size'],"text-danger"];
                $uploadError = 1;
            }else{
                $uploadInfos[] = ["File size correct:".$uploadedFile['size'],"text-success"];
            }

            $uploaddir = $_SERVER['CONTEXT_DOCUMENT_ROOT'].'/dspace/';

            if (!file_exists($uploaddir)) {
              $uploadInfos[] = ["Download directory does not exist","text-danger"];
              $uploadError = 1;
            }else{
              $uploadInfos[] = ["Download directory 'dspace' exist","text-success"];
            }

            //$uploadfile = $uploaddir . basename($uploadedFile['name']);
            $customFileName = strtotime("now").".pdf";
            $uploadfile = $uploaddir . $customFileName;

            print_r($uploadedFile);


            if (move_uploaded_file($uploadedFile['tmp_name'], $uploadfile) && $uploadError == 0) {

              if (!file_exists($uploadfile)) {
                $uploadInfos[] = ["Upload File does not exist","text-danger"];
                $uploadError = 1;
              }else{
                $uploadInfos[] = ["Upload File exist: ".$customFileName,"text-success"];
              }

              $workspaceItem = $dspace->addWorkspaceItem($uploadfile, $collectionID);

              $itemID = $workspaceItem->id;
              $uploadInfos[] = ["WorkspaceItem ID: ".$itemID,"text-success"];

              $dbPublications = $this->getTable('publication')->addPublication($user->id, $controlNumber, $itemID, $termFileData['termDate']);
              //$itemID = "134863";
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

              $languege = 'en';
              if(isset($dublinCore['DC.language'][0])) {
                switch($dublinCore['DC.language'][0]){
                  case"German":
                    $languege = 'de';
                  break;
                }
              }

              $metaArray = [
                  "title"=>$userFileName,
                  "language"=>$languege,
                  "publisher"=>$dublinCore['DC.publisher'][0],
                  "author"=>$dublinCore['DC.creator'][0].";02a88394-6161-44ce-a0c0-5f1640137bf4",
                  "identifiers"=>"issn;".$controlNumber

              ];

              $item = $dspace->editMetaData($itemID,$metaArray);

              $uploadInfos[] = ["add created meta data","text-success"];

            } else {
                $uploadInfos[] = ["file not uploaded","text-danger"];
                $uploadError = 1;
            }

            if($uploadError == 0) {
              /*
              $metaArray = [
                "publisher"=>"Published text test"
              ];

              $item2update = $dspace->editMetaData($itemID,$metaArray);

              $uploadInfos[] = ["updated meta data","text-success"];
              */
              // delete file after upload to Dspace
              $this->removeDspaceFile();

              $uploadInfos[] = ["temp file deleted!","text-success"];
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

        $termsDir =  $_SERVER['CONTEXT_DOCUMENT_ROOT'].'/docs/publication_terms/';
        $files = scandir($termsDir);
        $latestTermFileData = [];
        $latestTermData = [];
        foreach($files as $file){
            if(strlen($file) > 3) {
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
          $this->arraySortByColumn($latestTermData, 'milliseconds');
          $latestTermFileData = $latestTermData[0];
        }
        return $latestTermFileData;
    }

    private function arraySortByColumn(&$arr, $col, $dir = SORT_DESC): void {
      $sort_col = array();
      foreach ($arr as $key => $row) {
          $sort_col[$key] = $row[$col];
      }
      array_multisort($sort_col, $dir, $arr);
    }

    private function removeDspaceFile(): void {

      $dspaceDir = $_SERVER['CONTEXT_DOCUMENT_ROOT'].'/dspace/';
      $files = scandir($dspaceDir);
      foreach($files as $file){
        if(strlen($file) > 3) {
           unlink($dspaceDir.$file);
        }
      }

    }

}
