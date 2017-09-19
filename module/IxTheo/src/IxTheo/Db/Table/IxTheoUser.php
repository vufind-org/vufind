<?php
namespace IxTheo\Db\Table;
class IxTheoUser extends \VuFind\Db\Table\Gateway implements \VuFind\Db\Table\DbTableAwareInterface
{
    use \VuFind\Db\Table\DbTableAwareTrait;

    protected $session;

    public function __construct()
    {
        parent::__construct('ixtheo_user', 'IxTheo\Db\Row\IxTheoUser');
    }

    public function getNew($userId)
    {
        $row = $this->createRow();
        $row->id = $userId;
        return $row;
    }

    public function canUseTAD($userId)
    {
        return $this->get($userId)->can_use_tad;
    }

    public function get($userId)
    {
        $select = $this->getSql()->select();
        $select->where("id=" . $userId);
        $rowset = $this->selectWith($select);
        return $rowset->current();
    }
}