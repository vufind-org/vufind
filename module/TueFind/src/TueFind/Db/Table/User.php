<?php

namespace TueFind\Db\Table;

class User extends \VuFind\Db\Table\User {
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
        $select->where(['tuefind_is_admin' => true]);
        $select->order('username ASC');
        return $this->selectWith($select);
    }
}
