<?php

namespace IxTheo\View\Helper\Root;

class Record extends \VuFind\View\Helper\Root\Record
{
    /**
     * Render an subscription entry.
     *
     * @param \VuFind\Db\Row\UserList $list Currently selected list (null for
     * combined favorites)
     * @param \VuFind\Db\Row\User     $user Current logged in user (false if none)
     *
     * @return string
     */
    public function getSubscriptionListEntry($list = null, $user = false)
    {
        return $this->renderTemplate(
            'subscription-entry.phtml',
            [
                'driver' => $this->driver,
                'list' => $list,
                'user' => $user,
            ]
        );
    }

    /**
     * Render a PDA subscription entry.
     *
     * @param \VuFind\Db\Row\UserList $list Currently selected list (null for
     * combined favorites)
     * @param \VuFind\Db\Row\User     $user Currently logged in user (false if none)
     *
     * @return string
     */
    public function getPDASubscriptionListEntry($list = null, $user = false)
    {
        return $this->renderTemplate(
            'pdasubscription-entry.phtml',
            [
                'driver' => $this->driver,
                'list' => $list,
                'user' => $user,
            ]
        );
    }
}
