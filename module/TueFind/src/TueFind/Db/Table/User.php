<?php

namespace TueFind\Db\Table;

class User extends \VuFind\Db\Table\User {
    public function getByRight($right)
    {
        $select = $this->getSql()->select();
        $select->where('FIND_IN_SET("' . $right . '", tuefind_rights) > 0');
        $select->order('username ASC');
        return $this->selectWith($select);
    }

    /**
     * Retrieve a user object from the database based on ID.
     *
     * @param string $uuid Uuid.
     *
     * @return UserRow
     */
    public function getByUuid($uuid)
    {
        return $this->select(['tuefind_uuid' => $uuid])->current();
    }

    public function getAdmins()
    {
        $select = $this->getSql()->select();
        $select->where('tuefind_rights IS NOT NULL');
        $select->order('username ASC');
        return $this->selectWith($select);
    }
}
