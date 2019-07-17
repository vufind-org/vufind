<?php 
namespace IxTheo\RecordTab;

class FulltextSearch extends \VuFind\RecordTab\AbstractContent
{
    public function __construct()
    {
        $this->accessPermission = 'access.FulltextSearchTab';
    }

    public function getDescription()
    {
         return 'Item Fulltext Search';
    }

    public function isActive()
    {
         return true;
    }

    public function isVisisble()
    {
         return true;
    }
}
?>
