<?xml version="1.0" encoding="UTF-8"?>
<sch:schema xmlns:lido="http://www.lido-schema.org"
            xmlns:sch="http://purl.oclc.org/dsdl/schematron"
            xmlns:xs="http://www.w3.org/2001/XMLSchema"
            xml:lang="en"
            queryBinding="xslt2">
   <sch:title>
	            Schematron Constraints for FINNA LIDO Profile</sch:title>
   <sch:ns uri="http://purl.oclc.org/dsdl/schematron" prefix="sch"/>
   <sch:ns uri="http://www.lido-schema.org" prefix="lido"/>
   <sch:ns uri="http://www.w3.org/2002/07/owl#" prefix="owl"/>
   <sch:ns uri="http://www.w3.org/2004/02/skos/core#" prefix="skos"/>
   <sch:ns uri="http://www.w3.org/1999/02/22-rdf-syntax-ns#" prefix="rdf"/>
   <sch:pattern>
      <sch:title>FINNA Schematron patterns</sch:title>
      <sch:rule context="lido:actorID[@lido:source='finaf']">
         <sch:assert test="starts-with(., 'http://urn.fi/URN:NBN:fi:au:finaf:')" role="WARN">URIs for KANTO actors should begin with 'http://urn.fi/URN:NBN:fi:au:finaf:'</sch:assert>
      </sch:rule>
      <sch:rule context="lido:actorID[starts-with(., 'http://urn.fi/URN:NBN:fi:au:finaf:')]">
         <sch:assert test="@lido:source='finaf'" role="WARN">For KANTO actors, the source attribute of actorID should be 'finaf'.</sch:assert>
      </sch:rule>
      <sch:rule context="lido:actorID">
         <sch:assert test="@lido:source" role="WARN">Missing actorID[@source]: It is required to identify the source for actor ID.</sch:assert>
      </sch:rule>
      <sch:rule context="lido:classificationWrap">
         <sch:assert test="count(lido:classification) &gt; 0" role="INFO">Missing classifications: Classification is a recommended element.</sch:assert>
         <sch:assert test="count(lido:classification[@lido:type='language']/lido:term) &gt; 0"
                     role="INFO">Missing object language: For textual materials, it is strongly recommended to describe the language(s) of the object in a classification/term element with type 'language'.</sch:assert>
      </sch:rule>
      <sch:rule context="lido:classification">
         <sch:assert test="count(lido:term[string(normalize-space(text()))]) &gt; 0"
                     role="WARN">Missing classification/term: Term is a required element within classification</sch:assert>
      </sch:rule>
      <sch:rule context="lido:classification[@lido:type='language']/lido:term">
         <sch:assert test="translate(normalize-space(text()), 'bcdefghijklmnopqrstuvxyz', 'aaaaaaaaaaaaaaaaaaaaaaaa')='aaa'"
                     role="WARN">Invalid language code: For the language of the object, the label of the term should be "language" and the term should contain a three-letter language code.</sch:assert>
      </sch:rule>
      <sch:rule context="lido:earliestDate">
         <sch:assert test="matches(string(normalize-space(text())), '(^((-|–)?[0-9]{4})$)|(^((-|–)?[0-9]{4})(-|–)(0[1-9]|1[0-2])$)|(^((-|–)?[0-9]{4})(-|–)(0[1-9]|1[0-2])(-|–)([0][1-9]|[12][0-9]|3[01])(T)?)')"
                     role="WARN">
							earliestDate: The date should comply to the formats [-]CCYY, [-]CCYY-MM, [-]CCYY-MM-DD or [-]CCYY-MM-DDThh:mm:ss[Z|(+|-)hh:mm].
						</sch:assert>
      </sch:rule>
      <sch:rule context="lido:eventWrap">
         <sch:assert test="count(lido:eventSet) &gt; 0" role="INFO">Missing eventSet: eventSet is a recommended element.</sch:assert>
      </sch:rule>
      <sch:rule context="lido:eventSet">
         <sch:assert test="count(lido:event) &gt; 0" role="WARN">Missing eventSet/event: Within eventSet, event is a required element.</sch:assert>
      </sch:rule>
      <sch:rule context="lido:event">
         <sch:assert test="count(lido:eventType/lido:term[string(normalize-space(text()))]) &gt; 0"
                     role="WARN">Missing eventType/term: An event should have an event type term.</sch:assert>
         <sch:assert test="count(lido:eventActor) &gt; 0" role="INFO">Missing event/eventActor: eventActor is a recommended element.</sch:assert>
         <sch:assert test="count(lido:eventDate) &gt; 0" role="INFO">Missing event/eventDate: eventDate is a recommended element.</sch:assert>
         <sch:assert test="count(lido:eventPlace) &gt; 0" role="INFO">Missing event/eventPlace: eventPlace is a recommended element.</sch:assert>
      </sch:rule>
      <sch:rule context="lido:eventDate">
         <sch:assert test="count(lido:displayDate[string(normalize-space(text()))]) &gt; 0"
                     role="INFO">Missing eventDate/displayDate: displayDate is a recommended element.</sch:assert>
         <sch:assert test="not(lido:displayDate[not(@xml:lang)])" role="INFO">Missing lang attribute in eventDate/displayDate: It is recommended to specify the language of the date in the lang attribute of displayDate.</sch:assert>
         <sch:assert test="count(lido:date) &gt; 0" role="INFO">Missing eventDate/date: date is a recommended element.</sch:assert>
      </sch:rule>
      <sch:rule context="lido:eventPlace">
         <sch:assert test="count(lido:displayPlace[string(normalize-space(text()))]) &gt; 0"
                     role="INFO">Missing eventPlace/displayPlace: displayPlace is a recommended element.</sch:assert>
         <sch:assert test="count(lido:place) &gt; 0" role="INFO">Missing eventPlace/place: place is a recommended element.</sch:assert>
         <sch:assert test="(count(lido:displayPlace[string(normalize-space(text()))]) &gt; 0) or (count(lido:place/lido:namePlaceSet/lido:appellationValue[string(normalize-space(text()))]) &gt; 0)"
                     role="WARN">Missing eventPlace/displayPlace and eventPlace/place/namePlaceSet/appellationValue: It is required to include the name of the place either to displayPlace or namePlaceSet.</sch:assert>
      </sch:rule>
      <sch:rule context="lido:inscriptionDescription">
         <sch:assert test="count(lido:descriptiveNoteValue[string(normalize-space(text()))]) &gt; 0"
                     role="WARN">Missing inscriptionDescription/descriptiveNoteValue: A non-empty descriptiveNoteValue is required.</sch:assert>
         <sch:assert test="not(lido:descriptiveNoteValue[not(@xml:lang)])" role="INFO">Missing lang attribute in inscriptionDescription/descriptiveNoteValue: It is recommended to specify the language of the description in the lang attribute of descriptiveNoteValue.</sch:assert>
         <sch:assert test="not(@lido:type) or @lido:type='technique' or @lido:type='location' or @lido:type='description'"
                     role="WARN">Invalid inscriptionDescription[@type]: If type attribute is in use, it should be one from "technique", "location" or "description".</sch:assert>
      </sch:rule>
      <sch:rule context="lido:latestDate">
         <sch:assert test="matches(string(normalize-space(text())), '(^((-|–)?[0-9]{4})$)|(^((-|–)?[0-9]{4})(-|–)(0[1-9]|1[0-2])$)|(^((-|–)?[0-9]{4})(-|–)(0[1-9]|1[0-2])(-|–)([0][1-9]|[12][0-9]|3[01])(T)?)')"
                     role="WARN">
							latestDate: The date should comply to the formats [-]CCYY, [-]CCYY-MM, [-]CCYY-MM-DD or [-]CCYY-MM-DDThh:mm:ss[Z|(+|-)hh:mm].
						</sch:assert>
      </sch:rule>
      <sch:rule context="lido:lido">
         <sch:assert test="lido:lidoRecID[string(normalize-space(text()))]" role="WARN">Missing lidoRecID: There should be a non-empty record identifier.</sch:assert>
         <sch:assert test="count(lido:lidoRecID[string(normalize-space(text()))]) &lt; 2"
                     role="WARN">lidoRecID: There should be exactly one record identifier.</sch:assert>
      </sch:rule>
      <sch:rule context="lido:linkResource[string(normalize-space(text()))]">
         <sch:assert test="@lido:formatResource" role="WARN">Missing linkResource[@formatResource]: It is required to specify the format of the resource.</sch:assert>
         <sch:assert test="starts-with(normalize-space(text()), 'http')" role="WARN">Invalid linkResource: There must be a valid http/https link in linkResource.</sch:assert>
      </sch:rule>
      <sch:rule context="lido:namePlaceSet/lido:appellationValue">
         <sch:assert test="@lido:label" role="INFO">Missing namePlaceSet/appellationValue[@label]: It is recommended to specify the type of place in the label attribute of appellationValue.</sch:assert>
      </sch:rule>
      <sch:rule context="lido:objectDescriptionWrap">
         <sch:assert test="count(lido:objectDescriptionSet) &gt; 0" role="INFO">Missing objectescriptionSet: objectDescriptionSet is a recommended element.</sch:assert>
         <sch:assert test="not(lido:objectDescriptionSet/lido:descriptiveNoteValue[not(@xml:lang)])"
                     role="INFO">Missing lang attribute in objectDescriptionSet/descriptiveNoteValue: It is recommended to specify the language of the descriptive note in the lang attribute of descriptiveNoteValue.</sch:assert>
      </sch:rule>
      <sch:rule context="lido:objectDescriptionSet">
         <sch:assert test="count(lido:descriptiveNoteValue[string(normalize-space(text()))]) &gt; 0"
                     role="WARN">Missing objectDescriptionSet/descriptiveNoteValue: descriptiveNoteValue is required within objectDescriptionSet.</sch:assert>
      </sch:rule>
      <sch:rule context="lido:objectNote">
         <sch:assert test="not(@lido:type) or @lido:type='objectWorkType'" role="WARN">Invalid objectNote[@type]: If type attribute is used for objectNote, it must be "objectWorkType".</sch:assert>
      </sch:rule>
      <sch:rule context="lido:objectType">
         <sch:assert test="count(lido:term[string(normalize-space(text()))]) &gt; 0"
                     role="WARN">Missing objectType/term: A non-empty term is required.</sch:assert>
      </sch:rule>
      <sch:rule context="lido:objectWorkTypeWrap">
         <sch:assert test="count(lido:objectWorkType/lido:term[string(normalize-space(text()))]) &gt; 0"
                     role="WARN">Missing objectWorkType/term: At least one object work type term is required.</sch:assert>
      </sch:rule>
      <sch:rule context="lido:partOfPlace">
         <sch:assert test="count(lido:placeID[string(normalize-space(text()))]) &gt; 0"
                     role="INFO">Missing partOfPlace/placeID: Adding an identifier for the place is recommended.</sch:assert>
         <sch:assert test="count(lido:namePlaceSet/lido:appellationValue[string(normalize-space(text()))]) &gt; 0"
                     role="INFO">Missing partOfPlace/namePlaceSet/appellationValue: Describing the name of the place in namePlaceSet is recommended.</sch:assert>
      </sch:rule>
      <sch:rule context="lido:place">
         <sch:assert test="count(lido:placeID[string(normalize-space(text()))]) &gt; 0"
                     role="INFO">Missing place/placeID: Adding an identifier for the place is recommended.</sch:assert>
         <sch:assert test="count(lido:namePlaceSet/lido:appellationValue[string(normalize-space(text()))]) &gt; 0"
                     role="INFO">Missing place/namePlaceSet/appellationValue: Describing the name of the place in namePlaceSet is recommended.</sch:assert>
         <sch:assert test="count(lido:partOfPlace) &gt; 0" role="INFO">Missing partOfPlace: It is recommended to include at least one broader context for the place in partOfPlace element.</sch:assert>
         <sch:assert test="count(lido:gml) &gt; 0" role="INFO">Missing place/gml: Including the cordinates of the place in gml is recommended.</sch:assert>
      </sch:rule>
      <sch:rule context="lido:placeID[@lido:source='yso']">
         <sch:assert test="starts-with(., 'http://www.yso.fi/onto/yso/')" role="WARN">URIs for YSO places should begin with 'http://www.yso.fi/onto/yso/'</sch:assert>
         <sch:assert test="@lido:type='http://terminology.lido-schema.org/lido00099'"
                     role="WARN">For YSO place URIs, the type attribute of placeID should be 'http://terminology.lido-schema.org/lido00099'.</sch:assert>
      </sch:rule>
      <sch:rule context="lido:placeID[starts-with(., 'http://www.yso.fi/onto/yso/')]">
         <sch:assert test="@lido:source='yso'" role="WARN">For YSO places, the source attribute of placeID should be 'yso'.</sch:assert>
      </sch:rule>
      <sch:rule context="lido:placeID">
         <sch:assert test="@lido:source" role="WARN">Missing placeID[@source]: It is required to identify the source for place ID.</sch:assert>
      </sch:rule>
      <sch:rule context="lido:qualifierMeasurements">
         <sch:assert test="count(lido:term[string(normalize-space(text()))]) &gt; 0"
                     role="WARN">Missing qualifierMeasurements/term: A non-empty term is required.</sch:assert>
         <sch:assert test="not(lido:term[not(@xml:lang)])" role="INFO">Missing lang attribute in qualifierMeasurements/term: It is recommended to specify the language of the term in the lang attribute of term.</sch:assert>
      </sch:rule>
      <sch:rule context="lido:recordWrap">
         <sch:assert test="count(lido:recordRights/lido:rightsType/lido:conceptID[string(normalize-space(text()))]) &gt; 0"
                     role="WARN">Missing recordRights/rightsType/conceptID: It is required to specify the license of the LIDO record.</sch:assert>
      </sch:rule>
      <sch:rule context="lido:recordSource">
         <sch:assert test="count(lido:legalBodyName/lido:appellationValue[string(normalize-space(text()))]) &gt; 0"
                     role="WARN">Missing recordSource/legalBodyName/appellationValue: The name of the institution is required.</sch:assert>
      </sch:rule>
      <sch:rule context="lido:relatedWork">
         <sch:assert test="count(lido:displayObject[string(normalize-space(text()))]) &gt; 0"
                     role="WARN">Missing relatedWork/displayObject: displayObject is required within relatedWork.</sch:assert>
      </sch:rule>
      <sch:rule context="lido:relatedWork/lido:displayObject">
         <sch:assert test="@xml:lang" role="INFO">Missing lang attribute in relatedWork/displayObject: It is recommended to specify the language in the lang attribute of displayObject.</sch:assert>
      </sch:rule>
      <sch:rule context="lido:relatedWork/lido:object[(lido:objectType/lido:term='collection' or lido:objectType/lido:term='parent') and lido:objectID[string(normalize-space(text()))]]">
         <sch:assert test="count(lido:objectNote[@lido:type='objectWorkType' and string(normalize-space(text()))]) &gt; 0"
                     role="WARN">Missing objectNote: If there is a non-empty object/objectID and object/objectType/term is "collection" or "parent", there must be a non-empty object/objectNote with type attribute "objectWorkType".</sch:assert>
      </sch:rule>
      <sch:rule context="lido:relatedWork/lido:object[lido:objectType/lido:term='parent']">
         <sch:assert test="count(lido:objectID[string(normalize-space(text()))]) &gt; 0"
                     role="WARN">Missing objectID: If the object/objectType/term is "parent", there must be a non-empty object/objectID.</sch:assert>
      </sch:rule>
      <sch:rule context="lido:relatedWorkRelType">
         <sch:assert test="count(lido:term[string(normalize-space(text()))]) &gt; 0"
                     role="WARN">Missing relatedWorkRelType/term: A related work should have a term for the relation type.</sch:assert>
      </sch:rule>
      <sch:rule context="lido:relatedWorksWrap[lido:relatedWorkSet/lido:relatedWork/lido:object/lido:objectType/lido:term='parent']">
         <sch:assert test="count(lido:relatedWorkSet[lido:relatedWork/lido:object/lido:objectType/lido:term='collection']) &gt; 0"
                     role="WARN">Missing relatedWorkSet element for collection record: If there is a relatedWorkSet element for parent record (with relatedWork/object/objectType/term "parent"), there must also ve a relatedWorkSet element for collection record (with relatedWork/object/objectType/term "collection").</sch:assert>
      </sch:rule>
      <sch:rule context="lido:resourceDescription">
         <sch:assert test="@lido:type" role="INFO">Missing resourceDescription[@type]: It is recommended to specify the type of resource description.</sch:assert>
         <sch:assert test="@xml:lang" role="INFO">Missing resourceDescription[@lang]: It is recommended to specify the language of resource description.</sch:assert>
      </sch:rule>
      <sch:rule context="lido:resourceRepresentation[not(@lido:type)]">
         <sch:assert test="@lido:type" role="WARN">Missing resourceRepresentation[@type]: It is required to specify the type of resource representation.</sch:assert>
      </sch:rule>
      <sch:rule context="lido:resourceRepresentation[@lido:type!='image_thumb']">
         <sch:assert test="count(lido:resourceMeasurementsSet) &gt; 0" role="INFO">Missing resourceMeasurementsSet: It is recommended to specify the file size of a downloadable resource in resourceMeasurementsSet.</sch:assert>
      </sch:rule>
      <sch:rule context="lido:rightsResource[lido:rightsType/lido:conceptID[contains(string(normalize-space(text())), 'InC')]]">
         <sch:assert test="count(lido:rightsHolder/lido:legalBodyName/lido:appellationValue[string(normalize-space(text()))]) &gt; 0"
                     role="WARN">Missing rightsResource/rightsHolder/legalBodyName/appellationValue: For resources with rights type https://rightsstatements.org/vocab/InC/1.0/, the name of the rights holder should be specified.</sch:assert>
      </sch:rule>
      <sch:rule context="lido:rightsResource">
         <sch:assert test="count(lido:rightsType/lido:conceptID[string(normalize-space(text()))]) &gt; 0"
                     role="WARN">Missing rightsResource/rightsType/conceptID: It is required to specify the rights statement or license of the resource representation.</sch:assert>
      </sch:rule>
      <sch:rule context="lido:subjectWrap">
         <sch:assert test="(count(lido:subjectSet/lido:subject/lido:subjectConcept) &gt; 0)"
                     role="INFO">Missing subjectConcept: subjectConcept is a recommended element.</sch:assert>
      </sch:rule>
      <sch:rule context="lido:subjectConcept">
         <sch:assert test="count(lido:conceptID[string(normalize-space(text()))]) &gt; 0"
                     role="INFO">Missing subjectConcept/conceptID: Adding an identifier for the subject concept is recommended.</sch:assert>
      </sch:rule>
      <sch:rule context="lido:subjectConcept/lido:conceptID[@lido:source='yso']">
         <sch:assert test="starts-with(., 'http://www.yso.fi/onto/yso/')" role="WARN">URIs for YSO concepts should begin with 'http://www.yso.fi/onto/yso/'</sch:assert>
         <sch:assert test="@lido:type='http://terminology.lido-schema.org/lido00099'"
                     role="WARN">For YSO concept URIs, the type attribute of conceptID should be 'http://terminology.lido-schema.org/lido00099'.</sch:assert>
      </sch:rule>
      <sch:rule context="lido:subjectConcept/lido:conceptID[@lido:source='koko']">
         <sch:assert test="starts-with(., 'http://www.yso.fi/onto/koko/')" role="WARN">URIs for KOKO concepts should begin with 'http://www.yso.fi/onto/koko/'</sch:assert>
         <sch:assert test="@lido:type='http://terminology.lido-schema.org/lido00099'"
                     role="WARN">For KOKO concept URIs, the type attribute of conceptID should be 'http://terminology.lido-schema.org/lido00099'.</sch:assert>
      </sch:rule>
      <sch:rule context="lido:subjectConcept/lido:conceptID[starts-with(., 'http://www.yso.fi/onto/yso/')]">
         <sch:assert test="@lido:source='yso'" role="WARN">For YSO concepts, the source attribute of conceptID should be 'yso'.</sch:assert>
      </sch:rule>
      <sch:rule context="lido:subjectConcept/lido:conceptID[starts-with(., 'http://www.yso.fi/onto/koko/')]">
         <sch:assert test="@lido:source='koko'" role="WARN">For KOKO concepts, the source attribute of conceptID should be 'koko'.</sch:assert>
      </sch:rule>
      <sch:rule context="lido:subjectConcept/lido:conceptID">
         <sch:assert test="@lido:source" role="WARN">Missing subjectConcept/conceptID[@source]: It is required to identify the source for subject concept ID.</sch:assert>
      </sch:rule>
      <sch:rule context="lido:subjectConcept/lido:term">
         <sch:assert test="@xml:lang" role="INFO">Missing lang attribute in subjectConcept/term: It is recommended to specify the language of subject concetps by using the lang attribute in term.</sch:assert>
      </sch:rule>
      <sch:rule context="lido:subjectDate">
         <sch:assert test="count(lido:displayDate[string(normalize-space(text()))]) &gt; 0"
                     role="INFO">Missing subjectDate/displayDate: displayDate is a recommended element.</sch:assert>
         <sch:assert test="not(lido:displayDate[not(@xml:lang)])" role="INFO">Missing lang attribute in subjectDate/displayDate: It is recommended to specify the language of the date in the lang attribute of displayDate.</sch:assert>
         <sch:assert test="count(lido:date) &gt; 0" role="INFO">Missing subjectDate/date: date is a recommended element.</sch:assert>
      </sch:rule>
      <sch:rule context="lido:subjectPlace">
         <sch:assert test="count(lido:displayPlace[string(normalize-space(text()))]) &gt; 0"
                     role="INFO">Missing subjectPlace/displayPlace: displayPlace is a recommended element.</sch:assert>
         <sch:assert test="count(lido:place) &gt; 0" role="INFO">Missing subjectPlace/place: place is a recommended element.</sch:assert>
         <sch:assert test="(count(lido:displayPlace[string(normalize-space(text()))]) &gt; 0) or (count(lido:place/lido:namePlaceSet/lido:appellationValue[string(normalize-space(text()))]) &gt; 0)"
                     role="WARN">Missing subjectPlace/displayPlace and subjectPlace/place/namePlaceSet/appellationValue: It is required to include the name of the place either to displayPlace or namePlaceSet.</sch:assert>
      </sch:rule>
      <sch:rule context="lido:titleWrap">
         <sch:assert test="count(lido:titleSet/lido:appellationValue[string(normalize-space(text()))]) &gt; 0"
                     role="WARN">Missing titleSet/appellationValue: There should be at least one title.</sch:assert>
      </sch:rule>
      <sch:rule context="lido:titleWrap">
         <sch:assert test="not(lido:titleSet/lido:appellationValue[not(@xml:lang)])"
                     role="INFO">Missing lang attribute in titleSet/appellationValue: It is recommended to specify the language of each title by using the lang attribute in appellationValue.</sch:assert>
      </sch:rule>
      <sch:rule context="lido:titleSet">
         <sch:assert test="not(lido:appellationValue[string-length(string(normalize-space(text()))) &lt; 3])"
                     role="INFO">Very short titleSet/appellationValue: The recommended minimum length is 3 characters.</sch:assert>
         <sch:assert test="not(lido:appellationValue[string-length(string(normalize-space(text()))) &gt; 70])"
                     role="INFO">Very long titleSet/appellationValue: The recommended maximum length is 70 characters.</sch:assert>
      </sch:rule>
   </sch:pattern>
</sch:schema>
