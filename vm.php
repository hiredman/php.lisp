<?php

$__STACKS = array(array(), array(), array(), array());
$__CONSTANTS = array ();

function exe ($op, $foo=null) {
  global $__STACKS;
  global $__CONSTANTS;
  switch ($op) {
    case 0xFF: //dump
      print_r($__STACKS);
      break;
    case 0x01: //load
      $stack = array_pop($foo);
      foreach($foo as $x)
        array_push($__STACKS[$stack], $x);
      break;
    case 0x02: //run
      for($op = array_pop($__STACKS[0]); $op != null; $op = array_pop($__STACKS[0]))
        exe ($op);
      break;
    case 0x03: // 0 -> 1
      array_push($__STACKS[1], array_pop($__STACKS[0]));
      break;
    case 0x04: // 2 -> 1
      array_push($__STACKS[1], array_pop($__STACKS[2]));
      break;
    case 0x05: // 1 to stack specified by next on 0
      array_push($__STACKS[array_pop($__STACKS[0])], array_pop($__STACKS[1]));
      break;
    case 0x0F: //copy 1 to 2 
      $x = array_pop($__STACKS[1]);
      array_push($__STACKS[1], $x);
      array_push($__STACKS[2], $x);
      break;
    case 0x10: //add two values from 1, put on 1
      array_push($__STACKS[1], array_pop($__STACKS[1]) + array_pop($__STACKS[1]));
      break;
    case 0x11: //shift 1
      array_push($__STACKS[2], array_pop($__STACKS[1]));
      array_push($__STACKS[3], array_pop($__STACKS[1]));
      array_push($__STACKS[1], array_pop($__STACKS[2]));
      array_push($__STACKS[1], array_pop($__STACKS[3]));
      break;
    case 0x12: //pop 1
      array_pop($__STACKS[1]);
      break;
    case 0x20: //print value from 1
      print array_pop($__STACKS[1]);
      break;
    case 0x21: //load constant -> 1
      array_push($__STACKS[1], $__CONSTANTS[array_pop($__STACKS[1])]);
      break;
    case 0x22: //call php function
      $func=array_pop($__STACKS[3]);
      $ret=call_user_func_array($func,$__STACKS[3]);
      array_push($__STACKS[1], 3);
      exe(0x23);
      array_push($__STACKS[1], $ret);
      break;
    case 0x23: //empty stack number from 1
      $x = array_pop($__STACKS[1]);
      $__STACKS[$x] = array();
      break;
  }
}

function run() {
  exe (0x02);
}

function load_program ($ar) {
  $x = array_reverse($ar);
  array_push($x,0);
  exe (0x01, $x);
}

print "<pre>";
$__CONSTANTS[0] = "\n";
$__CONSTANTS[1] = "time";
$__CONSTANTS[2] = "add";
$__CONSTANTS[12] = "foo";
//exe(0x01, array(0xFF, 0x20, 0x21, 0, 0x03, 0x20, 0x21, 0, 0x03, 0x20, 0x10, 3, 0x03, 9, 0x03, 0));
//exe(0x01, array(0x20, 0x21, 0, 0x03, 0x20, 0x22, 0));

function add ($a, $b) {
  return $a + $b;
}

$prg = array (
  0x03, 1, //load v1 to 1
  0x21, //load constant 
  0x05, 3,  //move 1 to 3
  0x22, //execute php function
  0x20, // print
  0x03, 0,
  0x21,
  0x20,
  0x03, 0,
  0x21,
  0x20,
  0x03, 2,
  0x21,
  0x03, 3,
  0x03, 4,
  0x05, 3,
  0x05, 3,
  0x05, 3,
  0x22,
  0x20,
  0x03, 0,
  0x03, 20,
  0x05, 2,
  0x21,
  0x20
); 

load_program($prg);

run ();
?>
