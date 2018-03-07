<?php

namespace KrimDok\RecordDriver;

class SolrDefault extends \TueFind\RecordDriver\SolrMarc {

    public function getGenres()
    {
        return isset($this->fields['genre']) ? $this->fields['genre'] : array();
    }

    /**
     * @return array
     */
    public function getFidSystematik()
    {
        if (isset($this->fields['fid_systematik']) && !empty($this->fields['fid_systematik'])) {
            return $this->fields['fid_systematik'];
        } else {
            return array();
        }
    }

    /**
     * @return array
     */
    public function getInstitutsSystematik()
    {
        if (isset($this->fields['instituts_systematik2']) && !empty($this->fields['instituts_systematik2'])) {
            return $this->fields['instituts_systematik2'];
        } else {
            return array();
        }
    }

    /**
     * Get an array of all the ISILs in the record.
     *
     * @return array
     */
    public function getIsils()
    {
        return isset($this->fields['isil']) ? $this->fields['isil'] : [];
    }

    /**
     * Get local signatures of the current record.
     *
     * @return array
     */
    public function getLocalSignatures()
    {
        return isset($this->fields['local_signature']) && is_array($this->fields['local_signature']) ?
            $this->fields['local_signature'] : [];
    }

    /**
     * Get the start page of the item that contains this record (i.e. MARC 773q of a
     * journal).
     *
     * @return string
     */
    public function getPageCount()
    {
        return isset($this->fields['page_count'])
            ? $this->fields['page_count'] : '';
    }

    /**
     * @return string
     */
    public function getPageRange()
    {
        return isset($this->fields['page_range']) ? $this->fields['page_range'] : '';
    }

    public function getTopics()
    {
        return isset($this->fields['topic']) ? $this->fields['topic'] : array();
    }

    public function isAvailableForPDA()
    {
        return isset($this->fields['available_for_pda']) ? $this->fields['available_for_pda'] : false;
    }

    public function isAvailableInTuebingen()
    {
        return (isset($this->fields['available_in_tubingen']) ? $this->fields['available_in_tubingen'] : false);
    }
}
