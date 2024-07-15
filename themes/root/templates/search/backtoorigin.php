<?php
/** @var \Laminas\View\Renderer\PhpRenderer $this */
/** @var \VuFind\Search\SearchOrigin\AbstractSearchOrigin|null $searchOrigin */
if (isset($searchOrigin)) {
    $link = $this->url('alphabrowse-home') . '?' . http_build_query($searchOrigin->getOriginUrlParamsArray());
    echo
        '<div class="backtoorigin container">' .
        '<p><a href="' . $link . '">' . $this->translate('Back to ' . $searchOrigin->getDisplayName()) . '</a></p>' .
        '</div>';
}
