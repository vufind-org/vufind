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

    // METS:
    // - Very complex documentation: https://mets.github.io/METS_Docs/mets.html
    // - Simpler tutorial with DC examples: https://www.loc.gov/standards/mets/METSOverview.html
    protected function generatePublishPackageFromPost(): string
    {
        $uploadedFile = $this->params()->fromFiles('file');

        // Generate METS file
        $mets = new \XMLWriter();
        $mets->openMemory();
        $mets->setIndent(true);

        $mets->startDocument();
        $mets->startElement('mets');
        $mets->writeAttributeNs('xmlns', 'dc', null, 'http://purl.org/dc/elements/1.1/');

        $mets->startElement('metsHdr');
        $mets->writeAttribute('CREATEDATE', date('c'));
        $mets->endElement();

        $mets->startElement('dmdSec');
        $mets->writeAttribute('ID', 'DMD001');
        $mets->startElement('mdWrap');
        $mets->writeAttribute('MIMETYPE', 'text/xml'); // This MIMETYPE is related to the DC metadata, not the file itself!
        $mets->writeAttribute('MDTYPE', 'DC');
        $mets->writeAttribute('LABEL', 'Dublin Core Metadata');
        $mets->writeElementNs('dc', 'title', null, $this->params()->fromPost('title'));
        $mets->writeElementNs('dc', 'creator', null, $this->params()->fromPost('creator'));
        $mets->writeElementNs('dc', 'language', null, $this->params()->fromPost('language'));
        $mets->writeElementNs('dc', 'format', null, $uploadedFile['type']);
        $mets->endElement();
        $mets->endElement();

        $mets->writeElement('amdSec');

        $mets->startElement('fileSec');
        $mets->startElement('fileGrp');
        $mets->writeAttribute('ID', 'VERS1');
        $mets->startElement('file');
        $mets->writeAttribute('ID', 'FILE001');
        $mets->writeAttribute('MIMETYPE', $uploadedFile['type']);
        $mets->writeAttribute('SIZE', $uploadedFile['size']);
        $mets->startElement('FContent');
        $mets->writeElement('binData', base64_encode(file_get_contents($uploadedFile['tmp_name'])));
        $mets->endElement();
        $mets->endElement();
        $mets->endElement();
        $mets->endElement();

        $mets->writeElement('structMap');
        $mets->writeElement('structLink');
        $mets->writeElement('behaviorSec');

        $mets->endElement();
        $mets->endDocument();

        $metsPath = tempnam(sys_get_temp_dir(), 'mets');
        $metsAsString = $mets->outputMemory();
        //print '<pre>' . htmlspecialchars($metsAsString) . '</pre>';
        file_put_contents($metsPath, $metsAsString);

        // Generate ZIP package
        $zipPath = tempnam(sys_get_temp_dir(), 'zip');
        $zip = new \ZipArchive();
        $zip->open($zipPath, \ZIPARCHIVE::CREATE);
        $zip->addFile($metsPath, 'mets.xml');
        //$zip->addFile($uploadedFile['tmp_name'], $uploadedFile['name']);

        return $zipPath;
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

        $viewParams = $this->getUserAuthoritiesAndRecords($user, /* $onlyGranted = */ true);
        $viewParams['publications'] = $this->getTable('publication')->getByUserId($user->id);
        return $this->createViewModel($viewParams);
    }

    public function publishAction()
    {
        $user = $this->getUser();
        if ($user == false) {
            return $this->forceLogin();
        }

        $existingRecord = null;
        $dublinCore = null;
        $existingRecordId = $this->params()->fromRoute('record_id', null);
        if ($existingRecordId != null) {
            $existingRecord = $this->getRecordLoader()->load($existingRecordId);
            $dublinCore = $this->serviceLocator->get(\VuFind\MetadataVocabulary\PluginManager::class)->get('DublinCore')->getMappedData($existingRecord);
        }

        $action = $this->params()->fromPost('action');
        if ($action == 'publish') {
            $packagePath = $this->generatePublishPackageFromPost();
            // TODO: post to sword2 and create DB entry
            // copy to /tmp for debugging purposes
            //copy($packagePath, '/tmp/package.zip');

        }

        $view = $this->createViewModel($this->getUserAuthoritiesAndRecords($user, /* $onlyGranted = */ true, /* $exceptionIfEmpty = */ true));
        $view->existingRecord = $existingRecord;
        $view->dublinCore = $dublinCore;
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
}
