<?php
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

function linkr ($source) {
  $out=array();
  $functions=array();
  $os=1;
  foreach($source as $function => $body) {
    foreach($body as $i)
      array_push($out, $i);
    $functions[$function] = $os+1;
    $os+=sizeof($body);
  }
  array_unshift($out,null);
  foreach($out as $k => $v) {
    if (is_string($v)) {
      $out[$k] = $functions[$v];
    }
  }
  $out[0] = $functions["main"];
  array_push($out,0x0fff);
  return $out;
}

function assemble ($file, $progn, $constants) {
  $fp = fopen($file,'w');
  $c = serialize($constants);
  fwrite($fp, pack("V", strlen($c)));
  fwrite($fp,$c);
  foreach($progn as $byte)
    fputs($fp, pack("n", $byte));
  fclose($fp);
}

function machine($store){
  $registers = array();
  $php = array();
  $context = array();
  while($store[1] < 0x0fff) {
    $instruction = $store[$store[1]];
    //print dechex($store[1])."=>".dechex($instruction)."<br>";
    switch($instruction & 0xf000){
      case 0x0000:
        $store[1] = $instruction & 0x0fff;
        break;
      case 0x1000:
        switch ($instruction & 0x0fff) {
          case 0x0000: //load to register
            $r = $store[$store[1] +1];
            $a = $store[$store[1] +2];
            $registers[$r] = $a;
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
          case 0x0004: //load current pc
            $r = $store[$store[1] +1];
            $registers[$r] = $store[1];
            $store[1] += 2;
            break;
        }
        break;
      case 0x2000:
        switch($instruction & 0x0fff) {
          case 0x0000:
            $a = $store[$store[1]+1];
            $registers[0x00ff] = $store[1]+2;
            $store[1]=$a;
            break;
          case 0x0001:
            $store[1] = $registers[0x00ff];
            break;
          case 0x0002:
            $store[1] = $registers[$store[1]+1];
            break;
          case 0x0003:
            array_push($context,$registers);
            $store[1]++;
            break;
          case 0x0004:
            $x = $registers;
            $registers = array_pop($context);
            for($i=0x0030;$i<0x0040;$i++)
              $registers[$i] = $x[$i];
            $store[1]++;
            break;
        }
        break;
      case 0x3000: //addition
        switch($instruction & 0x0fff) {
          case 0x0000:
            $r = $registers[$store[$store[1]+1]];
            $a = $registers[$store[$store[1]+2]];
            $registers[0x00FF] = $r + $a;
            $store[1] += 3;
            break;
          case 0x0001: //inc
            $registers[$store[$store[1]+1]]++;
            $store[1] += 2;
            break;
          case 0x0002: //dec
            $registers[$store[$store[1]+1]]--;
            $store[1] += 2;
            break;
        }
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
          $store[1] = $registers[0x0001];
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
          case 0x0004:
            print_r($context);
            break;
        }
        $store[1]++;
        break;
    }
  }
  return $store;
}

function labeler ($ls) {
  $output = array ();
  foreach ($ls as $l) {
    $lab = array_shift($l);
    $output[$lab] = $l; 
  }
  return $output;
}

//$progn = array (
//  array (
//    "dump",
//    0x4000,0x00f0,0x00ff,
//    0x1002,0x0001,0x0009,
//    0x1003,0x0001,
//    0x4000,0x0001,0xffff,
//    0x2000,"start-pre",
//    0xf000,
//    0x2000,"end-pre",
//    0x4000,0x00ff,0x00f0,
//    0x2001),
//  array (
//    "start-pre",
//    0x1002,0x0000,0x0003,
//    0x1003,0x0000,
//    0x4000,0x0000,0xffff,
//    0x2001),
//  array (
//    "end-pre",
//    0x1002,0x0000,0x0008,
//    0x1003,0x0000,
//    0x4000,0x0000,0xffff,
//    0x2001),
//  array (
//    "space",
//    0x1002,0x0000,0x0002,
//    0x1003,0x0000,
//    0x4000,0x0000,0xffff,
//    0x2001),
//  array (
//    "hello world",
//    0x1002,0x0000,0x0004,
//    0x1003,0x0000,
//    0x2003,
//    0x2000,"space",
//    0x2004,
//    0x1002,0x0000,0x0005,
//    0x1003,0x0000,
//    0x4000,0x0000,0xffff,
//    0x2001),
//  array (
//    "date",
//    0x1002,0x0000,0x0007,
//    0x7000,0x0000,
//    0x1002,0x0000,0x0006,
//    0x7000,0x0000,
//    0x7001,
//    0x7002,
//    0x4000,0x0030,0x0000,
//    0x2001),
//  array (
//    "havoc",
//    0x1003,0x0003,
//    0x1003,0x0002,
//    0x2001),
//  array (
//    "loop",
//    0x3002,0x0000,
//    0x2003,
//    0x2000,havoc,
//    0x2004,
//    0x6000,
//    0x2001),
//  array (
//    "main",
//    0x2000, "hello world",
//    0x2000, "space",
//    0x2003,
//    0x2000, "date",
//    0x2004,
//    0x1003,0x0030,
//    0x4000,0x0030,0x0000,
//    0x1000,0x0000,5,
//    0x1000,0x0001,"loop",
//    0x1002,0x0002,0x000b,
//    0x1002,0x0003,0x000c,
//    0x1003,0x0002,
//    0x2000,"loop",
//    0x4000,0x0002,0xffff,
//    0x4000,0x0003,0xffff,
//    0x2000, "dump",
//    0x0fff));
//
//$constants = array (
//  "time",
//  25,
//  " ",
//  "<pre>",
//  "hello",
//  "world",
//  "date",
//  "c",
//  "</pre>",
//  "<br>registers:<br>",
//  10,
//  "<br>",
//  "cry havoc and let slip the dogs of war"
//);
//
//assemble("/tmp/foo.bin", linkr(labeler($progn)), $constants);
//machine(load_program("/tmp/foo.bin"));
?>
