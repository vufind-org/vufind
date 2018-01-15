<?php

namespace IxTheo\Auth;

class Manager extends \VuFind\Auth\Manager
{
    /**
     * necessary redirect since getAuth has become protected
     *
     * @param array $params
     * @param \VuFind\Db\Row\User $user
     * @param \IxTheo\Db\Row\IxTheoUser $ixTheoUser
     */
    public function updateIxTheoUser($params, \VuFind\Db\Row\User $user,
                                     \IxTheo\Db\Row\IxTheoUser $ixTheoUser)
    {
        $this->getAuth()->updateIxTheoUser($params, $user, $ixTheoUser);
    }
}
