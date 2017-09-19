<?php

/**
 * Generic class for Solr RecordDriver extensions
 *
 * Since multiple inheritance is not allowed in PHP, please use the following
 * class tree structure:
 *
 * VuFind\RecordDriver\SolrDefault
 * VuFind\RecordDriver\SolrMarc
 * TuFind\RecordDriver\SolrDefault
 * TuFind\RecordDriver\SolrMarc
 * <custom>\RecordDriver\SolrDefault
 * <custom>\RecordDriver\SolrMarc
 *
 * ... where <custom> might be krimDok, ixTheo, and so on.
 * So SolrDefault should always inherit from SolrMarc of the top level.
 */

namespace TuFind\RecordDriver;
use Zend\ServiceManager\ServiceLocatorAwareInterface;
use Zend\ServiceManager\ServiceLocatorInterface;

class SolrDefault extends \VuFind\RecordDriver\SolrMarc implements ServiceLocatorAwareInterface
{
    protected $serviceLocator;

    public function setServiceLocator(ServiceLocatorInterface $serviceLocator)
    {
        $this->serviceLocator = $serviceLocator->getServiceLocator();
    }

    public function getServiceLocator()
    {
        return $this->serviceLocator;
    }

    /**
     * Get all non-standardized topics
     */
    public function getAllNonStandardizedSubjectHeadings()
    {
        return (isset($this->fields['topic_non_standardized'])) ?
            $this->fields['topic_non_standardized'] : '';
    }

    /**
     * Get all standardized topics including KWCs
     */

    public function getAllStandardizedSubjectHeadings()
    {
        return (isset($this->fields['topic_standardized'])) ?
            $this->fields['topic_standardized'] : '';
    }

    public function getAllSubjectHeadingsFlat()
    {
        $result     = array();
        $headings   = $this->getAllSubjectHeadings();
        foreach($headings as $heading_arr) {
            $result = array_merge($result, $heading_arr);
        }
        return $result;
    }

    public function getAuthorsAsString() {
        $author_implode = function ($array) {
            if (is_null($array)) {
                return null;
            }
            return implode(", ", array_filter($array, function($entry) {
                return empty($entry) ? false : true;
            }));
        };
        return $author_implode(array_map($author_implode, array_map("array_keys", $this->getDeduplicatedAuthors())));
    }

    /**
     * Return an associative array of all container IDs (parents) mapped to their titles containing the record.
     *
     * @return array
     */
    public function getContainerIDsAndTitles()
    {
        $retval = array();
        if (isset($this->fields['container_ids_and_titles']) && !empty($this->fields['container_ids_and_titles'])) {
            foreach ($this->fields['container_ids_and_titles'] as $id_and_title) {
                $a = explode(chr(0x1F), str_replace("#31;", chr(0x1F), $id_and_title), 3);
                if (count($a) == 3) {
                    $retval[$a[0]] = array($a[1], $a[2]);
                }
            }
        }
        return $retval;
    }

    /**
     * Get the mediatype
     */
    public function getMediaType()
    {
        return (isset($this->fields['mediatype'])) ?
            $this->fields['mediatype'] : '';
    }

    public function getOtherTitles() {
        return isset($this->fields['other_titles']) ?
            $this->fields['other_titles'] : array();
    }

    public function getRecordDriverByPPN($ppn) {
        $recordLoader = $this->getServiceLocator()->get('VuFind\RecordLoader');
        return $recordLoader->load($ppn, 'Solr', false);
    }

    /**
     * Get the record ID of the current record.
     *
     * @return string
     */
    public function getRecordId()
    {
        return isset($this->fields['id']) ?
            $this->fields['id'] : '';
    }

    public function getReviewedRecords()
    {
        $retval = array();
        if (isset($this->fields['reviewed_records']) && !empty($this->fields['reviewed_records'])) {
            foreach ($this->fields['reviewed_records'] as $review) {
                $a = explode(chr(0x1F), str_replace("#31;", chr(0x1F), $review), 3);
                if (count($a) == 3) {
                    $retval[$a[0]] = array($a[1], $a[2]);
                }
            }
        }
        return $retval;
    }

    public function getReviews()
    {
        $retval = array();
        if (isset($this->fields['reviews']) && !empty($this->fields['reviews'])) {
            foreach ($this->fields['reviews'] as $review) {
                $a = explode(chr(0x1F), str_replace("#31;", chr(0x1F), $review), 3);
                if (count($a) == 3) {
                    $retval[$a[0]] = array($a[1], $a[2]);
                }
            }
        }
        return $retval;
    }

    public function getSubitoURL($broker_id) {
        $base_url = "http://www.subito-doc.de/preorder/?BI=" . $broker_id;
        switch ($this->getBibliographicLevel()) {
            case 'Monograph':
                $isbn = $this->getCleanISBN();
                if (!empty($isbn))
                    return $base_url . "&SB=" . $isbn;
                return $base_url . "&CAT=SWB&ND" . $this->getRecordId();
            case 'Serial':
                $zdb_number = $this->getZDBNumber();
                if (!empty($zdb_number))
                    return $base_url . "&ND=" . $zdb_number;
                $issn = $this->getCleanISSN();
                if (!empty($issn))
                   return $base_url . "&SS=" . $issn;
                break;
            case 'MonographPart':
            case 'SerialPart':
                $isbn = $this->getCleanISBN();
                $issn = $this->getCleanISSN();
                $title = $this->getTitle();
                $authors = $this->getDeduplicatedAuthors();
                $page_range = $this->getPageRange();
                $volume = $this->getVolume();
                $issue = $this->getIssue();
                $year = $this->getYear();
                if ((!empty($isbn) || !empty($issn)) && !empty($title) && !empty($authors) && !empty($page_range)
                    && (!empty($volume) || !empty($issue)) && !empty($year))
                {
                    $title = $this->escapeHtml($title);
                    $author_list = "";
                    foreach ($authors as $author) {
                        if (!empty($author_list))
                            $author_list .= "%3B";
                        $author_list .= $this->escapeHtml($author);
                    }
                    $page_range = $this->escapeHtml($page_range);

                    $volume_and_or_issue = $this->escapeHtml($volume);
                    if (!empty($volume_and_or_issue))
                        $volume_and_or_issue .= "%2F";
                    $volume_and_or_issue .= $this->escapeHtml($issue);

                    return $base_url . (!empty($isbn) ? "&SB=" . $isbn : "&SS=" . $issn) . "&ATI=" . $title . "&AAU="
                        . $author_list . "&PG=" . $page_range . "&APY=" . $year . "&VOL=" . $volume_and_or_issue;
                }
        }

        return "";
    }

    /**
     * Get the full title of the record.
     *
     * @return string
     */
    public function getTitle()
    {
        $title = $this->getShortTitle();
        $subtitle = $this->getSubtitle();
        $titleSection = $this->getTitleSection();
        if (!empty($subtitle)) {
            if ($title != '') {
                $separator = preg_match("/^[\\s=]+/", $subtitle) ? " " : " : ";
                $title .= $separator;
            }
            $title .= $subtitle;
        }
        if (!empty($titleSection)) {
            if ($title != '') {
                $title .= ' / ';
            }
            $title .= $titleSection;
        }
        return $title;
    }

    public function getZDBNumber()
    {
        return (isset($this->fields['zdb_number'])) ?
            $this->fields['zdb_number'] : '';
    }

    /** Check whether a record is potentially available for PDA
     *
     * @return bool
     */
    public function isPotentiallyPDA()
    {
        return isset($this->fields['is_potentially_pda']) && $this->fields['is_potentially_pda'];
    }

    public function isSuperiorWork() {
        return $this->fields['is_superior_work'];
    }

    public function isSubscribable() {
        return $this->fields['is_subscribable'];
    }

    public function stripTrailingDates($text) {
        $matches = array();
        if (!preg_match("/(\\D*)(\\d{4}).*/", $text, $matches))
            return $text;
        return rtrim($matches[1]);
    }

    public function subscribe($params, $user)
    {
        if (!$user) {
            throw new LoginRequiredException('You must be logged in first');
        }

        $table = $this->getDbTable('Subscription');
        $recordId = $this->getUniqueId();
        $userId = $user->id;

        if ($table->findExisting($userId, $recordId)) {
            return "Exists";
        }
        return $table->subscribe($userId, $recordId, $this->getTitle(), $this->getAuthorsAsString(), $this->getPublicationDates()[0]);
    }

    public function unsubscribe($params, $user)
    {
        if (!$user) {
            throw new LoginRequiredException('You must be logged in first');
        }

        $table = $this->getDbTable('Subscription');
        $recordId = $this->getUniqueId();
        $userId = $user->id;

        return $table->unsubscribe($userId, $recordId);
    }

    public function pdaSubscribe($params, $user, &$data)
    {
        if (!$user) {
            throw new LoginRequiredException('You must be logged in first');
        }

        $table = $this->getDbTable('PDASubscription');
        $recordId = $this->getUniqueId();
        $userId = $user->id;

        if ($table->findExisting($userId, $recordId)) {
            return "Exists";
        }

        $data = [$userId, $recordId, $this->getTitle(), $this->getAuthorsAsString(), $this->getPublicationDates()[0], $this->getISBNs()[0]];
        return call_user_func_array([$table, "subscribe"], $data);
    }

    public function pdaUnsubscribe($params, $user)
    {
        if (!$user) {
            throw new LoginRequiredException('You must be logged in first');
        }

        $table = $this->getDbTable('PDASubscription');
        $recordId = $this->getUniqueId();
        $userId = $user->id;

        return $table->unsubscribe($userId, $recordId);
    }
}
