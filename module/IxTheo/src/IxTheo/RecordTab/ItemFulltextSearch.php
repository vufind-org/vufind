<?php 
namespace IxTheo\RecordTab;

class ItemFulltextSearch extends \VuFind\RecordTab\AbstractContent
{
    public function __construct()
    {
        $this->accessPermission = 'access.ItemFulltextSearchTab';
    }

    public function getDescription()
    {
         return 'Item Fulltext Search';
    }

    public function isActive()
    {
         return false;
    }

    public function isVisisble()
    {
         return false;
    }
}
?>
