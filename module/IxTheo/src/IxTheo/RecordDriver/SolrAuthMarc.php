<?php

namespace IxTheo\RecordDriver;

class SolrAuthMarc extends SolrAuthDefault {
    public function getExternalSubsystems(): array
    {
        $subsystemLinks = [
            ['title' => 'RelBib', 'url' => 'https://relbib.de/Authority/' . urlencode($this->getUniqueID()), 'label' => 'REL'],
            ['title' => 'Index Biblicus', 'url' => 'https://bible.ixtheo.de/Authority/' . urlencode($this->getUniqueID()), 'label' => 'BIB'],
            ['title' => 'IxTheo / KALDI / DaKaR', 'url' => 'https://canonlaw.ixtheo.de/Authority/' . urlencode($this->getUniqueID()), 'label' => 'CAN']
        ];

        $result = [];
        $result[] = ['title' => 'Index Theologicus', 'url' => 'https://ixtheo.de/Authority/' . urlencode($this->getUniqueID()), 'label' => 'IXT'];
        foreach ($this->getSubsystems() as $subsystem) {
            foreach ($subsystemLinks as $subsystemLink) {
                if ($subsystemLink['label'] == $subsystem) {
                    $result[] = $subsystemLink;
                }
            }
        }

        return $result;
    }
}
