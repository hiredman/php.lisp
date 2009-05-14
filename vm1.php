<?php

function & load_program ($file) {
  return unpack('n*', file_get_contents($file));
}

function machine($store){
  $registers = array();
  while($store[1] < 0x0fff) {
    $instruction = $store[$store[1]];
    switch($instruction & 0xf000){
      case 0x0000:
        $store[1] = $instruction & 0x0fff;
        break;
      case 0x1000: //load into register
        switch ($instruction & 0x0fff) {
          case 0x0000:
            $r = $store[$store[1] +1];
            $a = $store[$store[1] +2];
            $registers[$r] = $store[$store[1] + 2 + $a];
            $store[1] += 3;
            break;
          case 0x0001:
            $r = $store[$store[1] +1];
            $a = $store[$store[1] +2];
            $store[$a] = $registers[$r];
            $store[1] += 3;
            break;
        }
        break;
      case 0x2000: //relative jump
        $distance = $instruction & 0x0fff;
        $store[1] += $distance;
        break;
      case 0x3000: //addition
        $r = $store[$store[1]+1];
        $a = $store[$store[1]+2];
        $b = $store[$store[1]+3];
        $registers[$r] = $registers[$a] + $registers[$b];
        $store[1] += 4;
        break;
      case 0x4000: //copy
        $r = $store[$store[1]+1];
        $a = $store[$store[1]+2];
        $registers[$r] = $registers[$a];
        $store[1] += 3;
        break;
      case 0x5000: //cast
        $r = $store[$store[1]+1];
        switch ($instruction & 0x0fff) {
          case 0x0000:
            $registers[$r] = chr($registers[$r]);
            break;
        }
        $store[1] += 2;
        break;
      case 0xf000:
        switch ($instruction & 0x0fff) {
          case 0x0000:
            print_r($registers);
            break;
          case 0x0001:
            print_r($store);
            break;
          case 0x0002:
            $registers = array();
            break;
        }
        $store[1]++;
        break;
    }
  }
}


$progn = array(
  0x0002,
  0x1000,0x0000,0x0002,
  0x2002,10,
  0x1000,0x0001,0x0002,
  0x2002,12,
  0x3000,0x0003,0x0000,0x0001,
  0x4000,0x0000, 0x0003,
  0x1000,0x000A,0x0002,
  0x2002,97, 
  0x5000,0x000A, //cast register 10 to character
  0x1001,0x000a,0xffff, //put register 10 into store[0xffff]
  0xf000, //print registers
  0xf001, //print store
  0x0fff, //jump to end
);
$fp = fopen("/tmp/foo.bin",'w');
foreach($progn as $byte)
  fputs($fp, pack("n", $byte));
fclose($fp);
print "<pre>";
machine(load_program("/tmp/foo.bin"));
?>
