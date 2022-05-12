<?php

namespace TueFind\RecordDriver;

class SolrAuthMarc extends SolrAuthDefault {

    protected $marcReaderClass = \TueFind\Marc\MarcReader::class;

    const EXTERNAL_REFERENCES_DATABASES = ['GND' , 'ISNI', 'LOC', 'ORCID', 'VIAF', 'Wikidata', 'Wikipedia'];

    /**
     * Our metadata is in german, but VuFind requires english keys for translation.
     * Since we do not have many of these cases, we map them by hand.
     */
    const LABEL_TRANSLATION_MAP = [
        // Places
        'Geburtsort'    => 'Place of birth',
        'Sterbeort'     => 'Place of death',
        'Wirkungsort'   => 'Place of activity',

        // Personal Relations
        'Ehemann'       => 'Husband',
        'Ehefrau'       => 'Wife',

        'Sohn'          => 'Son',
        'Tochter'       => 'Daughter',

        'Vater'         => 'Father',
        'Mutter'        => 'Mother',
        'Großvater'     => 'Grandfather',
        'Großmutter'    => 'Grandmother',

        'Bruder'        => 'Brother',
        'Schwester'     => 'Sister',

        'Schwager'      => 'Brother-in-law',
        'Schwägerin'    => 'Sister-in-law',

        'Cousin'        => 'Cousin',
        'Cousine'       => 'Cousin',
    ];

    /**
     * Get List of all beacon references.
     *
     * @param mixed typeFlag    null: all, true: literaryRemains only, false: non-literary-remains only
     *
     * @return [['title', 'url']]
     */
    public function getBeaconReferences($type=null): array
    {
        $beaconReferences = [];
        $beaconFields = $this->getMarcReader()->getFields('BEA');
        if (is_array($beaconFields)) {
            foreach($beaconFields as $beaconField) {
                if ($type !== null) {
                    $typeSubfield = $this->getMarcReader()->getSubfield($beaconField, '0');
                    if ($type === true && ($typeSubfield == false || $typeSubfield != 'lr'))
                        continue;
                    elseif ($type === false && ($typeSubfield != false && $typeSubfield == 'lr'))
                        continue;
                }

                $nameSubfield = $this->getMarcReader()->getSubfield($beaconField, 'a');
                $urlSubfield = $this->getMarcReader()->getSubfield($beaconField, 'u');

                if ($nameSubfield !== false && $urlSubfield !== false)
                    $beaconReferences[] = ['title' => $nameSubfield,
                                            'url' => $urlSubfield];
            }
        }
        return $beaconReferences;
    }

    protected function getExternalReferencesFiltered(array $blacklist=[], array $whitelist=[]): array
    {
        $references = [];

        $fields = $this->getMarcReader()->getFields('670');
        if (is_array($fields)) {
            foreach ($fields as $field) {
                $nameSubfield = $this->getMarcReader()->getSubfield($field, 'a');
                if ($nameSubfield === false)
                    continue;

                $name = $nameSubfield;
                if (in_array($name, $blacklist) || (count($whitelist) > 0 && !in_array($nameSubfield, $whitelist)))
                    continue;

                $urlSubfield = $this->getMarcReader()->getSubfield($field, 'u');
                if ($urlSubfield !== false) {
                    $url = $urlSubfield;
                    if ($name == 'Wikipedia')
                        $url = preg_replace('"&(oldid|diff)=[^&]+"', '', $url);

                    $references[] = ['title' => $name,
                                     'url' => $url];
                }
            }
        }

        return $references;
    }

    public function getBiographicalReferences(): array
    {
        $references = [];

        $gndNumber = $this->getGNDNumber();
        if ($gndNumber != null)
            $references[] = ['title' => 'GND' .  ' (' . $gndNumber . ')',
                             'url' => 'http://d-nb.info/gnd/' . urlencode($gndNumber)];

        $isnis = $this->getISNIs();
        foreach ($isnis as $isni) {
            $references[] = ['title' => 'ISNI',
                             'url' => 'https://isni.org/isni/' . urlencode(str_replace(' ', '', $isni))];
        }

        $lccn = $this->getLCCN();
        if ($lccn != null)
            $references[] = ['title' => 'LOC',
                             'url' => 'https://lccn.loc.gov/' . urlencode($lccn)];

        $orcids = $this->getORCIDs();
        foreach ($orcids as $orcid) {
            $references[] = ['title' => 'ORCID' .  ' (' . $orcid . ')',
                             'url' => 'https://orcid.org/' . urlencode($orcid)];
        }

        $viafs = $this->getVIAFs();
        foreach ($viafs as $viaf) {
            $references[] = ['title' => 'VIAF',
                             'url' => 'https://viaf.org/viaf/' . urlencode($viaf)];
        }

        $wikidataIds = $this->getWikidataIds();
        foreach ($wikidataIds as $wikidataId) {
            $references[] = ['title' => 'Wikidata',
                             'url' => 'https:////www.wikidata.org/wiki/' . urlencode($wikidataId)];
        }

        $references = array_merge($references, $this->getExternalReferencesFiltered(/*blacklist=*/[], /*whitelist=*/['Wikipedia']));
        $references = array_merge($references, $this->getBeaconReferences(/* type flag, false => only non literary-remains */ false));
        return $references;
    }

    public function getArchivedMaterial(): array
    {
        $references = $this->getExternalReferencesFiltered(/*blacklist=*/[], /*whitelist=*/['Archivportal-D', 'Kalliope']);
        $references = array_merge($references, $this->getBeaconReferences(/* type flag, true => only literary remains */ true));
        return $references;
    }

    public function getExternalSubsystems(): array
    {
        // This needs to be overridden in IxTheo/KrimDok if subsystems are present
        return [];
    }

    protected function getLifeDates()
    {
        $lifeDates = ['birth' => null, 'death' => null];

        $fields = $this->getMarcReader()->getFields('548');
        foreach ($fields as $field) {
            $typeSubfield = $this->getMarcReader()->getSubfield($field,'4');

            //echo '<pre>';
            //print_r($typeSubfield);
            //echo '</pre>';
            //die;


            if ($typeSubfield !== false && $typeSubfield == 'datx') {
                if (preg_match('"^(\d{1,2}\.\d{1,2}\.\d{1,4})-(\d{1,2}\.\d{1,2}\.\d{1,4})$"', $this->getMarcReader()->getSubfield($field,'a'), $hits)) {
                    $lifeDates['birth'] = $hits[1];
                    $lifeDates['death'] = $hits[2];
                    break;
                }
            }
        }

        return $lifeDates;
    }

    protected function getLifePlaces()
    {
        $lifePlaces = ['birth' => null, 'death' => null];

        $fields = $this->getMarcReader()->getFields('551');
        foreach ($fields as $field) {
            $typeSubfield = $this->getMarcReader()->getSubfield($field,'4');
            if ($typeSubfield !== false) {
                switch($typeSubfield) {
                case 'ortg':
                    $lifePlaces['birth'] = $this->getMarcReader()->getSubfield($field,'a') ?? null;
                    break;
                case 'orts':
                    $lifePlaces['death'] = $this->getMarcReader()->getSubfield($field,'a') ?? null;
                    break;
                }

            }
        }
        return $lifePlaces;
    }

    /**
     * Get birth date or year if date is not set
     * @return string
     */
    public function getBirthDateOrYear()
    {
        return $this->getBirthDate() ?? $this->getBirthYear();
    }

    /**
     * Get exact birth date
     * @return string
     */
    public function getBirthDate()
    {
        $lifeDates = $this->getLifeDates();
        return $lifeDates['birth'] ?? null;
    }

    /**
     * Get birth place
     * @return string
     */
    public function getBirthPlace()
    {
        return $this->getLifePlaces()['birth'] ?? null;
    }

    /**
     * Get birth year
     * @return string
     */
    public function getBirthYear()
    {
        $pattern = '"^(\d+)(-?)(\d+)?$"';
        $values = $this->getFieldArray('100', ['d']);
        foreach ($values as $value) {
            if (preg_match($pattern, $value, $hits))
                return $hits[1];
        }
    }

    /**
     * Get death date or year if date is not set
     * @return string
     */
    public function getDeathDateOrYear()
    {
        return $this->getDeathDate() ?? $this->getDeathYear();
    }

    /**
     * Get exact death date
     * @return string
     */
    public function getDeathDate()
    {
        $lifeDates = $this->getLifeDates();
        return $lifeDates['death'] ?? null;
    }

    /**
     * Get death place
     * @return string
     */
    public function getDeathPlace()
    {
        return $this->getLifePlaces()['death'] ?? null;
    }

    /**
     * Get death year
     * @return string
     */
    public function getDeathYear()
    {
        $pattern = '"^(\d+)(-?)(\d+)?$"';
        $values = $this->getFieldArray('100', ['d']);
        foreach ($values as $value) {
            if (preg_match($pattern, $value, $hits) && isset($hits[3]))
                return $hits[3];
        }
    }

    /**
     * Get geographical relations from 551
     * @return [['name', 'type']]
     */
    public function getGeographicalRelations()
    {
        $locations = [];
        $fields = $this->getMarcReader()->getFields('551');
        foreach ($fields as $field) {
            $locations[] = ['name' => $this->getMarcReader()->getSubfield($field,'a'),
                            'type' => $this->translateLabel($this->getMarcReader()->getSubfield($field,'i'))];
        }

        $fields = $this->getMarcReader()->getFields('043');
        foreach ($fields as $field) {
            foreach ($this->getMarcReader()->getSubfields($field,'c') as $subfield) {
                $locations[] = ['name' => $subfield,
                                'type' => 'DIN-ISO-3166'];
            }
        }
        return $locations;
    }

    public function getMeetingName()
    {
        foreach ($this->getMarcReader()->getFields('111') as $field) {
            $name = $this->getMarcReader()->getSubfield($field,'a');

            $subfield_c = $this->getMarcReader()->getSubfield($field,'c');
            $subfield_d = $this->getMarcReader()->getSubfield($field,'d');
            $subfield_g = $this->getMarcReader()->getSubfield($field,'g');

            if ($subfield_c != false || $subfield_g != false)
                $name .= '.';
            if ($subfield_g != false)
                $name .= ' ' . $subfield_g;
            if ($subfield_c != false)
                $name .= ' ' . $subfield_c;
            if ($subfield_d != false)
                $name .= ' (' . $subfield_d . ')';

            return $name;
        }

        return '';
    }

    /**
     * Get Name from 100a
     * @return string
     */
    public function getName()
    {
        foreach ($this->getMarcReader()->getFields('100') as $field) {
            $aSubfield = $this->getMarcReader()->getSubfield($field,'a');
            if ($aSubfield == false)
                continue;

            $name = $aSubfield;

            $bSubfield = $this->getMarcReader()->getSubfield($field,'b');
            if ($bSubfield != false)
                $name .= ' ' . $bSubfield;
            return $name;
        }

        return '';
    }

    /**
     * Get multiple notations of the name
     * (e.g. for external searches like wikidata)
     * (e.g. "King, Martin Luther" => "Martin Luther King")
     */
    public function getNameAliases(): array
    {
        $names = [];
        $name = $this->getName();
        $names[] = $name;
        $alias = preg_replace('"^([^,]+)\s*,\s*([^,]+)$"', '\\2 \\1', $name);
        if ($alias != $name)
            $names[] = $alias;
        return $names;
    }

    /**
     * Get name variants as listed in MARC21 400a
     */
    public function getNameVariants(): array
    {
        $nameVariants = [];
        $fields = $this->getMarcReader()->getFieldsDelimiter('400|410|411');
        if (is_array($fields)) {
            foreach ($fields as $field) {
                if (is_array($field)) {
                    foreach ($field as $oneField) {
                        $nameSubfield = $this->getMarcReader()->getSubfield($oneField,'a');
                        if (!empty($nameSubfield)) {
                            $name = $nameSubfield;
                            $numberSubfield = $this->getMarcReader()->getSubfield($oneField,'b');
                            if (!empty($numberSubfield)) {
                                $name .= ' ' . $numberSubfield;
                            }
                            $titleSubfield = $this->getMarcReader()->getSubfield($oneField,'c');
                            if (!empty($titleSubfield)) {
                                $name .= ' ' . $titleSubfield;
                            }
                            $nameVariants[] = $name;
                        }
                    }
                }
            }
        }

        sort($nameVariants);
        return $nameVariants;
    }

    public function getPersonalRelations(): array
    {
        $relations = [];

        $fields = $this->getMarcReader()->getFields('500');
        if (is_array($fields)) {
            foreach ($fields as $field) {
                $nameSubfield = $this->getMarcReader()->getSubfield($field,'a');

                if ($nameSubfield !== false) {
                    $relation = ['name' => $nameSubfield];

                    $idPrefixPattern = '/^\(DE-627\)/';
                    $idSubfield = $this->getMarcReader()->getSubfield($field, '0', $idPrefixPattern);
                    if ($idSubfield !== false)
                        $relation['id'] = preg_replace($idPrefixPattern, '', $idSubfield);

                    $typeSubfield = $this->getMarcReader()->getSubfield($field,'9');
                    if ($typeSubfield !== false)
                        $relation['type'] = $this->translateLabel(preg_replace('/^v:/', '', $typeSubfield));

                    $relations[] = $relation;
                }
            }
        }

        return $relations;
    }

    /**
     * "Titles" does NOT mean title data from the biblio index,
     * it refers to the field 100c instead. Due to the MARC21 authority standard,
     * this subfield is named "Titles and other words associated with a name".
     * It may contain occupations like "Theologe", but also other attributes
     * like "Familie".
     */
    public function getPersonalTitles(): array
    {
        $personalTitles = [];
        $personalTitlesStrings = $this->getFieldArray('100', ['c']);
        foreach ($personalTitlesStrings as $personalTitlesString) {
            $personalTitlesArray = explode(',', $personalTitlesString);
            foreach ($personalTitlesArray as $personalTitle)
                $personalTitles[] = trim($personalTitle);
        }
        return $personalTitles;
    }

    public function getCorporateRelations(): array
    {
        $relations = [];

        $fields = $this->getMarcReader()->getFields('510');
        if (is_array($fields)) {
            foreach ($fields as $field) {
                $nameSubfield = $this->getMarcReader()->getSubfield($field,'a');
                if ($nameSubfield !== false) {
                    $relation = ['name' => $nameSubfield];

                    $addSubfield = $this->getMarcReader()->getSubfield($field,'b');
                    if ($addSubfield !== false)
                        $relation['institution'] = $addSubfield;

                    $locationSubfield = $this->getMarcReader()->getSubfield($field,'g');
                    if ($locationSubfield !== false)
                        $relation['location'] = $locationSubfield;

                    $idPrefixPattern = '/^\(DE-627\)/';
                    $idSubfield = $this->getMarcReader()->getSubfield($field,'0', $idPrefixPattern);
                    if ($idSubfield !== false)
                        $relation['id'] = preg_replace($idPrefixPattern, '', $idSubfield);

                    $localSubfields = $this->getMarcReader()->getSubfields($field,'9');
                    foreach ($localSubfields as $localSubfield) {
                        if (preg_match('"^(.):(.+)"', $localSubfield, $matches)) {
                            if ($matches[1] == 'Z')
                                $relation['timespan'] = $matches[2];
                            else if ($matches[1] == 'v')
                                $relation['type'] = $matches[2];
                        }
                    }

                    $relations[] = $relation;
                }
            }
        }

        return $relations;
    }

    public function getTimespans(): array
    {
        $timespans = [];
        $fields = $this->getMarcReader()->getFields('548');
        if (is_array($fields)) {
            foreach ($fields as $field) {
                $subfield_a = $this->getMarcReader()->getSubfield($field,'a');
                if ($subfield_a !== false)
                    $timespans[] = $subfield_a;
            }
        }
        return $timespans;
    }

    public function getTitle()
    {
        if ($this->isMeeting())
            return $this->getMeetingName();
        return parent::getTitle();
    }

    public function isFamily(): bool
    {
        $fields = $this->getMarcReader()->getFields('079');
        if (is_array($fields)) {
            foreach ($fields as $field) {
                $typeSubfield = $this->getMarcReader()->getSubfield($field,'v');
                if ($typeSubfield != false && $typeSubfield == 'pif')
                    return true;
            }
        }
        return false;
    }

    public function isMeeting(): bool
    {
        return $this->getType() == 'meeting';
    }

    /**
     * This function is used to detect "Tn"-sets, which are similar to persons.
     *
     * @return bool
     */
    public function isName(): bool
    {
        $fields = $this->getMarcReader()->getFields('079');
        if (is_array($fields)) {
            foreach ($fields as $field) {
                $typeSubfield = $this->getMarcReader()->getSubfield($field,'b');
                if ($typeSubfield != false && $typeSubfield == 'n')
                    return true;
            }
        }
        return false;
    }

    /**
     * This just checks whether the main type is "person".
     * Be careful => if the main type is "person", it can e.g. still be sub-type "name", "family" or others.
     *
     * @return bool
     */
    public function isPerson(): bool
    {
        return $this->getType() == 'person';
    }

    protected function translateLabel($label): string
    {
        return self::LABEL_TRANSLATION_MAP[$label] ?? $label;
    }
}
