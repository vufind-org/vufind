<?php

namespace TueFind\Controller;

class QuickLinkController extends \VuFind\Controller\AbstractBase {

    /**
     * Try to resolve quicklink and perform redirect if found (else trigger "not found" action).
     *
     * @return ViewModel
     */
    public function redirectAction() {
        $id = $this->params()->fromRoute('id', $this->params()->fromQuery('id'));
        $url = $this->resolveQuickLink($id);
        if (!$url)
            return $this->notFoundAction();
        else
            return $this->redirect()->toUrl($url);
    }

    /**
     * Try to find target url for the given quicklink id
     *
     * @param string $id
     *
     * @return string|false
     */
    private function resolveQuickLink($id) {
        $id = mb_strtolower($id);
        $map = $this->getQuickLinkMap();
        return $map[$id] ?? false;
    }

    /**
     * Load quicklink map from file
     *
     * @return array
     */
    private function getQuickLinkMap() {
        $quicklinks = [];
        $path = getenv('VUFIND_LOCAL_DIR') . '/config/vufind/quicklinks.csv';
        if (is_file($path)) {
            $handle = fopen($path, 'r');
            while ($row = fgetcsv($handle, 0, ';', '"')) {
                if (isset($row[0]) && isset($row[1]))
                    $quicklinks[mb_strtolower($row[0])] = mb_strtolower($row[1]);
            }
            fclose($handle);
        }
        return $quicklinks;
    }
}
