<?php

namespace TueFind\Db\Row;

class User extends \VuFind\Db\Row\User
{
    public function hasSubscribedToNewsletter(): bool {
        return boolval($this->data['tuefind_subscribed_to_newsletter']);
    }

    public function setSubscribedToNewsletter(bool $value) {
        $this->tuefind_subscribed_to_newsletter = intval($value);
        $this->save();
    }
}
