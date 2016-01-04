--TEST--
marc_xml_002: iterate and pretty print a MARC record (LOC standard)
--SKIPIF--
<?php include('skipif.inc'); ?>
--FILE--
<?php
$dir = dirname(__FILE__);
require 'File/MARC.php';
$marc_file = new File_MARC($dir . '/' . 'sandburg.mrc');

while ($marc_record = $marc_file->next()) {
  print $marc_record->toXML();
  print "\n";
}
?>
--EXPECT--
<?xml version="1.0" encoding="UTF-8"?>
<collection xmlns="http://www.loc.gov/MARC21/slim">
 <record>
  <leader>01142cam  2200301 a 4500</leader>
  <controlfield tag="001">   92005291 </controlfield>
  <controlfield tag="003">DLC</controlfield>
  <controlfield tag="005">19930521155141.9</controlfield>
  <controlfield tag="008">920219s1993    caua   j      000 0 eng  </controlfield>
  <datafield tag="010" ind1=" " ind2=" ">
   <subfield code="a">   92005291 </subfield>
  </datafield>
  <datafield tag="020" ind1=" " ind2=" ">
   <subfield code="a">0152038655 :</subfield>
   <subfield code="c">$15.95</subfield>
  </datafield>
  <datafield tag="040" ind1=" " ind2=" ">
   <subfield code="a">DLC</subfield>
   <subfield code="c">DLC</subfield>
   <subfield code="d">DLC</subfield>
  </datafield>
  <datafield tag="042" ind1=" " ind2=" ">
   <subfield code="a">lcac</subfield>
  </datafield>
  <datafield tag="050" ind1="0" ind2="0">
   <subfield code="a">PS3537.A618</subfield>
   <subfield code="b">A88 1993</subfield>
  </datafield>
  <datafield tag="082" ind1="0" ind2="0">
   <subfield code="a">811/.52</subfield>
   <subfield code="2">20</subfield>
  </datafield>
  <datafield tag="100" ind1="1" ind2=" ">
   <subfield code="a">Sandburg, Carl,</subfield>
   <subfield code="d">1878-1967.</subfield>
  </datafield>
  <datafield tag="245" ind1="1" ind2="0">
   <subfield code="a">Arithmetic /</subfield>
   <subfield code="c">Carl Sandburg ; illustrated as an anamorphic adventure by Ted Rand.</subfield>
  </datafield>
  <datafield tag="250" ind1=" " ind2=" ">
   <subfield code="a">1st ed.</subfield>
  </datafield>
  <datafield tag="260" ind1=" " ind2=" ">
   <subfield code="a">San Diego :</subfield>
   <subfield code="b">Harcourt Brace Jovanovich,</subfield>
   <subfield code="c">c1993.</subfield>
  </datafield>
  <datafield tag="300" ind1=" " ind2=" ">
   <subfield code="a">1 v. (unpaged) :</subfield>
   <subfield code="b">ill. (some col.) ;</subfield>
   <subfield code="c">26 cm.</subfield>
  </datafield>
  <datafield tag="500" ind1=" " ind2=" ">
   <subfield code="a">One Mylar sheet included in pocket.</subfield>
  </datafield>
  <datafield tag="520" ind1=" " ind2=" ">
   <subfield code="a">A poem about numbers and their characteristics. Features anamorphic, or distorted, drawings which can be restored to normal by viewing from a particular angle or by viewing the image's reflection in the provided Mylar cone.</subfield>
  </datafield>
  <datafield tag="650" ind1=" " ind2="0">
   <subfield code="a">Arithmetic</subfield>
   <subfield code="x">Juvenile poetry.</subfield>
  </datafield>
  <datafield tag="650" ind1=" " ind2="0">
   <subfield code="a">Children's poetry, American.</subfield>
  </datafield>
  <datafield tag="650" ind1=" " ind2="1">
   <subfield code="a">Arithmetic</subfield>
   <subfield code="x">Poetry.</subfield>
  </datafield>
  <datafield tag="650" ind1=" " ind2="1">
   <subfield code="a">American poetry.</subfield>
  </datafield>
  <datafield tag="650" ind1=" " ind2="1">
   <subfield code="a">Visual perception.</subfield>
  </datafield>
  <datafield tag="700" ind1="1" ind2=" ">
   <subfield code="a">Rand, Ted,</subfield>
   <subfield code="e">ill.</subfield>
  </datafield>
 </record>
</collection>
