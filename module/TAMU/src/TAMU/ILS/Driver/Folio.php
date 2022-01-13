<?php
namespace TAMU\ILS\Driver;

class Folio extends \VuFind\ILS\Driver\Folio
{
    public function init() {
        $this->debug(
            'I am TAMU'
        );        
        parent::init();
    }

    /**
     * This method queries the ILS for holding information.
     *
     * @param string $bibId   Bib-level id
     * @param array  $patron  Patron login information from $this->patronLogin
     * @param array  $options Extra options (not currently used)
     *
     * @return array An array of associative holding arrays
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function getHolding($bibId, array $patron = null, array $options = [])
    {
        echo 'yes the custom';
        $instance = $this->getInstanceByBibId($bibId);
        $query = [
            'query' => '(instanceId=="' . $instance->id
                . '" NOT discoverySuppress==true)'
        ];
        $items = [];

        foreach ($this->getPagedResults(
            'holdingsRecords',
            '/holdings-storage/holdings',
            $query
        ) as $holding) {
            $query = [
                'query' => '(holdingsRecordId=="' . $holding->id
                    . '" NOT discoverySuppress==true)'
            ];
            $notesFormatter = function ($note) {
                return !($note->staffOnly ?? false)
                    && !empty($note->note) ? $note->note : '';
            };
            $textFormatter = function ($supplement) {
                $format = '%s %s';
                $supStat = $supplement->statement;
                $supNote = $supplement->note;
                $statement = trim(sprintf($format, $supStat, $supNote));
                return $statement ?? '';
            };
            $holdingNotes = array_filter(
                array_map($notesFormatter, $holding->notes ?? [])
            );
            $hasHoldingNotes = !empty(implode($holdingNotes));
            $holdingsStatements = array_map(
                $textFormatter,
                $holding->holdingsStatements ?? []
            );
            $holdingsSupplements = array_map(
                $textFormatter,
                $holding->holdingsStatementsForSupplements ?? []
            );
            $holdingsIndexes = array_map(
                $textFormatter,
                $holding->holdingsStatementsForIndexes ?? []
            );
            $holdingCallNumber = $holding->callNumber ?? '';
            $holdingCallNumberPrefix = $holding->callNumberPrefix ?? '';

            $holdingItems = iterator_to_array($this->getPagedResults('items', '/item-storage/items', $query));

            if ($holding->effectiveLocationId) {
                $fallbackLocationId = $holding->effectiveLocationId;
            } else if ($holding->permanentLocationId) {
                $fallbackLocationId = $holding->permanentLocationId;
            } else {
                $fallbackLocationId = null;
            }

            //TAMU boundwith workaround
            if (count($holdingItems) == 0 && $fallbackLocationId) {
                $boundWithLocations = ['stk','blcc,stk','BookStacks','psel,stk','udoc','txdoc'];
                $holdingLocationData = $this->getLocationData($fallbackLocationId);

                if (in_array($holdingLocationData['code'], $boundWithLocations)) {
                    $callNumberData = $this->chooseCallNumber(
                        $holdingCallNumberPrefix,
                        $holdingCallNumber,
                        '',
                        ''
                    );
                    $items[] = $callNumberData + [
                        'id' => $bibId,
                        'item_id' => 'bound-with-item',
                        'item_hrid' => 'bound-with-item',
                        'holding_id' => $holding->id,
                        'holding_hrid' => $holding->hrid,
                        'number' => 0,
                        'barcode' => 'bound-with-item',
                        'status' => null,
                        'availability' => true,
                        'is_holdable' => $this->isHoldable($holdingLocationData['name']),
                        'holdings_notes'=> $hasHoldingNotes ? $holdingNotes : null,
                        'item_notes' => null,
                        'issues' => $holdingsStatements,
                        'supplements' => $holdingsSupplements,
                        'indexes' => $holdingsIndexes,
                        'callnumber' => $holding->callNumber ?? '',
                        'location' => $holdingLocationData['name'],
                        'location_code' => $holdingLocationData['code'],
                        'reserve' => 'TODO',
                        'enumeration' => '',
                        'item_chronology' => '',
                        'addLink' => true
                    ];
                }
            }

            foreach ($holdingItems as $item) {
                $itemNotes = array_filter(
                    array_map($notesFormatter, $item->notes ?? [])
                );
                $locationId = $item->effectiveLocationId;
                $locationData = $this->getLocationData($locationId);
                $locationName = $locationData['name'];
                $locationCode = $locationData['code'];
                $callNumberData = $this->chooseCallNumber(
                    $holdingCallNumberPrefix,
                    $holdingCallNumber,
                    $item->itemLevelCallNumberPrefix ?? '',
                    $item->itemLevelCallNumber ?? ''
                );
                $items[] = $callNumberData + [
                    'id' => $bibId,
                    'item_id' => $item->id,
                    'item_hrid' => $item->hrid,
                    'holding_id' => $holding->id,
                    'holding_hrid' => $holding->hrid,
                    'number' => count($items) + 1,
                    'barcode' => $item->barcode ?? '',
                    'status' => $item->status->name,
                    'availability' => $item->status->name == 'Available',
                    'is_holdable' => $this->isHoldable($locationName),
                    'holdings_notes'=> $hasHoldingNotes ? $holdingNotes : null,
                    'item_notes' => !empty(implode($itemNotes)) ? $itemNotes : null,
                    'issues' => $holdingsStatements,
                    'supplements' => $holdingsSupplements,
                    'indexes' => $holdingsIndexes,
                    'callnumber' => $holding->callNumber ?? '',
                    'location' => $locationName,
                    'location_code' => $locationCode,
                    'reserve' => 'TODO',
                    'enumeration' => $item->enumeration ?? '',
                    'item_chronology' => $item->chronology ?? '',
                    'addLink' => true
                ];
            }
        }
        return $items;
    }
}
