<?php
//deadbeaf
function & load_program ($file) {
  $fp = fopen($file, "r");
  $foo=fgets($fp,8);
  $c=unpack("V", $foo);
  $c=$c[1];
  rewind($fp);
  fseek($fp,4);
  $f=fgets($fp,$c+1);
  $constants=unserialize($f);
  $buf = "";
  while(! feof($fp)) {
    $buf.=fread($fp,8192);
  }
  $x = unpack('n*', $buf);
  fclose($fp);
  $x[0] = $constants;
  return $x;
  exit;
  //return unpack('n*', file_get_contents($file));
}

function machine($store){
  $registers = array();
  $php = array();
  while($store[1] < 0x0fff) {
    $instruction = $store[$store[1]];
    switch($instruction & 0xf000){
      case 0x0000:
        $store[1] = $instruction & 0x0fff;
        break;
      case 0x1000:
        switch ($instruction & 0x0fff) {
          case 0x0000: //load to register
            $r = $store[$store[1] +1];
            $a = $store[$store[1] +2];
            $registers[$r] = $store[$store[1] + 2 + $a];
            $store[1] += 3;
            break;
          case 0x0001: //store from register
            $r = $store[$store[1] +1];
            $a = $store[$store[1] +2];
            $store[$a] = $registers[$r];
            $store[1] += 3;
            break;
          case 0x0002: //load constant to register
            $r = $store[$store[1] +1];
            $a = $store[$store[1] +2];
            $registers[$r] = $store[0][$a];
            $store[1] += 3;
            break;
          case 0x0003: //print register
            $r = $store[$store[1] +1];
            print $registers[$r];
            $store[1] += 2;
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
      case 0x6000:
        if ($registers[0] != 0) {
          $store[1] = $instruction & 0x0fff;
        } else {
          $store[1]++;
        }
        break;
      case 0x7000:
        switch ($instruction & 0x0fff) {
          case 0x0000:
            $r = $store[$store[1] + 1];
            array_push($php, $registers[$r]);
            $store[1]+=2;
            break;
          case 0x0001:
            $store[1]+=1;
            $x=$php;
            $registers[0]=call_user_func_array(array_pop($x),$x);
            break;
          case 0x0002:
            $php=array();
            $store[1]+=1;
            break;
        }
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
          case 0x0003:
            print_r($php);
            break;
        }
        $store[1]++;
        break;
    }
  }
}


$progn = array (
  0x0002,
  0x1002,0x0000,0x0004,
  0x1002,0x0001,0x0002,
  0x1002,0x0002,0x0005,
  0x1002,0x0003,0x0000,
  0x1002,0x0004,0x0006,
  0x1002,0x0005,0x0007,
  0x7000,0x0003,
  0x1003,0x0000, //print hello
  0x1003,0x0001, // print " "
  0x1003,0x0002, //print " world"
  0x7001,
  0x7002,
  0x7000,0x0005,
  0x7000,0x0000,
  0x7000,0x0004,
  0x7001,
  0x1003,0x0001,
  0x1003,0x0000,
  0x0fff, //jump to end
);

$constants = array (
  "time",
  25,
  " ",
  "<pre>",
  "hello",
  "world",
  "date",
  "c"
);

function assemble ($file, $progn, $constants) {
  $fp = fopen($file,'w');
  $c = serialize($constants);
  fwrite($fp, pack("V", strlen($c)));
  fwrite($fp,$c);
  foreach($progn as $byte)
    fputs($fp, pack("n", $byte));
  fclose($fp);
}
assemble("/tmp/foo.bin", $progn, $constants);
machine(load_program("/tmp/foo.bin"));
?>
