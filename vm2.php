<?php
function machine ($store) {
  $registers = array();
  $pc = 0;
  while($pc == 0) {
	$instruction = $store[$pc];
	$a = $instruction << (32 - 6);
	$b = $instruction << (32 - 6 - 4) >> (32 - 4) << (32 - 4);
	$c = $instruction << (32 - 6 - 4 - 6) >> (32 - 6) << (32 - 6);
	print $a;
	exit;
	switch ($instruction >> 26) {
	  case 0: //R-type
		switch($instruction << 26) {
		case -2147483648:
			print "foo";
			exit;
			$pc++;
			break;
		}
		break;
	}
  }
}
$i = bindec("11111111111111111111111111100001");
$i = $i << (32 - 6) >> (32 - 6) & (2 << 26);
print dechex($i);
print "<br>";
print decbin($i);
print "<br>";
print strlen(decbin($i));
?>
