<?php

namespace TueFind\Db\Row;

class User extends \VuFind\Db\Row\User
{
    public function hasSubscribedToNewsletter(): bool {
        return boolval($this->data['tuefind_subscribed_to_newsletter']);
    }

    public function isLicenseAccessLocked(): bool {
        return boolval($this->data['tuefind_license_access_locked']);
    }

    public function setSubscribedToNewsletter(bool $value) {
        $this->tuefind_subscribed_to_newsletter = intval($value);
        $this->save();
    }

    public function setRssFeedSendEmails(bool $value) {
        $this->tuefind_rss_feed_send_emails = intval($value);
        if (true) {
            $this->tuefind_rss_feed_last_notification = date('Y-m-d H:i:s');
        }
        $this->save();
    }
}
