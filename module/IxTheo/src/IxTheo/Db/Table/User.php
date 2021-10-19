<?php

namespace IxTheo\Db\Table;

use Laminas\Db\Sql\Select;

class User extends \TueFind\Db\Table\User
{
    public function canUseTAD($userId)
    {
        return $this->get($userId)->ixtheo_can_use_tad;
    }

    public function getAdmins()
    {
        $select = $this->getSql()->select();
        $select->where(['user.tuefind_is_admin' => true, 'user.ixtheo_user_type' => \IxTheo\Utility::getUserTypeFromUsedEnvironment()]);
        $select->order('user.username ASC');
        return $this->selectWith($select);
    }

    public function get($userId)
    {
        $select = $this->getSql()->select();
        $select->where("id=" . $userId);
        $rowset = $this->selectWith($select);
        return $rowset->current();
    }

    public function getNew($userId)
    {
        $row = $this->createRow();
        $row->id = $userId;
        return $row;
    }


}
