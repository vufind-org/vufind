<?php

namespace IxTheo\Controller\Plugin;

class NewItems extends \VuFind\Controller\Plugin\NewItems {
    public function getSolrFilter($range)
    {
        // Exclude artificial "Literary Remains" records
        return parent::getSolrFilter($range) . '-id:LR*';
    }
}
