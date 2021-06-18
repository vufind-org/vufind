<?php

namespace IxTheo\Db\Table;

use Laminas\Db\Sql\Select;

class User extends \TueFind\Db\Table\User
{
    public function getAdmins()
    {
        $select = $this->getSql()->select();
        $select->join('ixtheo_user', 'user.id = ixtheo_user.id', Select::SQL_STAR, SELECT::JOIN_LEFT);
        $select->where(['user.tuefind_is_admin' => true, 'ixtheo_user.user_type' => \IxTheo\Utility::getUserTypeFromUsedEnvironment()]);
        $select->order('user.username ASC');
        return $this->selectWith($select);
    }
}
