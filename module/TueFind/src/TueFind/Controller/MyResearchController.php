<?php

namespace TueFind\Controller;

class MyResearchController extends \VuFind\Controller\MyResearchController
{
    public function newsletterAction() {
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

    public function rssFeedSettingsAction() {
        $user = $this->getUser();
        if ($user == false) {
            return $this->forceLogin();
        }
        return $this->createViewModel(['user' => $user]);
    }

    public function rssFeedPreviewAction() {
        $user = $this->getUser();
        if ($user == false) {
            return $this->forceLogin();
        }

        $rssTable = $this->serviceLocator->get(\VuFind\Db\Table\PluginManager::class)->get('rss_item');
        $rssItems = $rssTable->getItemsForUserSortedByPubDate($user->id);
        return $this->createViewModel(['user' => $user, 'rssItems' => $rssItems]);
    }

    public function rssFeedRawAction() {
        $userId = $this->getRequest()->getQuery('user_id');
        $rssFeedContent = shell_exec('/usr/local/bin/rss_subset_aggregator rss_xml ' . escapeshellarg($userId));
        $response = $this->getResponse();
        $response->getHeaders()->addHeaderLine('Content-type', 'text/xml');
        $response->setContent($rssFeedContent);
        return $response;
    }
}
