--TEST--
marc_xml_003: Round-trip a MARCXML record to MARC21 (LOC standard)
--SKIPIF--
<?php include('skipif.inc'); ?>
--FILE--
<?php
$dir = dirname(__FILE__);
require 'File/MARCXML.php';
$marc_file = new File_MARCXML($dir . '/' . 'sandburg.xml');

while ($marc_record = $marc_file->next()) {
  print $marc_record->toRaw();
}
?>
--EXPECT--
01142cam  2200301 a 4500001001300000003000400013005001700017008004100034010001700075020002500092040001800117042000900135050002600144082001600170100003200186245008600218250001200304260005200316300004900368500004000417520022800457650003300685650003300718650002400751650002100775650002300796700002100819   92005291 DLC19930521155141.9920219s1993    caua   j      000 0 eng    a   92005291   a0152038655 :c$15.95  aDLCcDLCdDLC  alcac00aPS3537.A618bA88 199300a811/.522201 aSandburg, Carl,d1878-1967.10aArithmetic /cCarl Sandburg ; illustrated as an anamorphic adventure by Ted Rand.  a1st ed.  aSan Diego :bHarcourt Brace Jovanovich,cc1993.  a1 v. (unpaged) :bill. (some col.) ;c26 cm.  aOne Mylar sheet included in pocket.  aA poem about numbers and their characteristics. Features anamorphic, or distorted, drawings which can be restored to normal by viewing from a particular angle or by viewing the image's reflection in the provided Mylar cone. 0aArithmeticxJuvenile poetry. 0aChildren's poetry, American. 1aArithmeticxPoetry. 1aAmerican poetry. 1aVisual perception.1 aRand, Ted,eill.
