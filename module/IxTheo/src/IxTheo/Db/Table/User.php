<?php

namespace IxTheo\Db\Table;

class User extends \TueFind\Db\Table\User
{
    public function canUseTAD($userId)
    {
        return $this->get($userId)->ixtheo_can_use_tad;
    }

    public function createRowForUsername($username)
    {
        $row = parent::createRowForUsername($username);
        $row->ixtheo_user_type = \IxTheo\Utility::getUserTypeFromUsedEnvironment();
        return $row;
    }

    public function getAdmins()
    {
        $select = $this->getSql()->select();
        $select->where('user.tuefind_rights IS NOT NULL AND user.ixtheo_user_type = "' . \IxTheo\Utility::getUserTypeFromUsedEnvironment() . '"');
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

    public function getByEmail($email)
    {
        $row = $this->select(['email' => $email, 'ixtheo_user_type' => \IxTheo\Utility::getUserTypeFromUsedEnvironment()])->current();
        return $row;
    }

    public function getByRight($right)
    {
        $select = $this->getSql()->select();
        $select->where('FIND_IN_SET("' . $right . '", tuefind_rights) > 0 AND ixtheo_user_type="' . \IxTheo\Utility::getUserTypeFromUsedEnvironment() . '"');
        $select->order('username ASC');
        return $this->selectWith($select);
    }

    public function getByUsername($username, $create = true)
    {
        $row = $this->select(['username' => $username, 'ixtheo_user_type' => \IxTheo\Utility::getUserTypeFromUsedEnvironment()])->current();
        return ($create && empty($row))
            ? $this->createRowForUsername($username) : $row;
    }

    public function getNew($userId)
    {
        $row = $this->createRow();
        $row->id = $userId;
        $row->ixtheo_user_type = \IxTheo\Utility::getUserTypeFromUsedEnvironment();
        return $row;
    }


}
