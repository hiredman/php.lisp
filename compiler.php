<?php
$FUNCTIONAL_TABLE=array();
$REGISTERS=array();
$STACK=array();
function gensym () {
  global $FUNCTIONAL_TABLE;
  $x=rand_str();
  for($x=rand_str();in_array($x,$FUNCTIONAL_TABLE);$x=rand_str()){}
  array_push($FUNCTIONAL_TABLE, $x);
}

function ftemplate ($name,$code) {
  return "function $name () { $code }";
}

function gogo () {
  global $STACK;
  while (true) {
    call_user_func(array_pop($STACK));
  }
}

array_push($STACK,"exit");
gogo();


// Generate a random character string
function rand_str($length = 32, $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz1234567890')
{
    // Length of character list
    $chars_length = (strlen($chars) - 1);

    // Start our string
    $string = $chars{rand(0, $chars_length)};
   
    // Generate random string
    for ($i = 1; $i < $length; $i = strlen($string))
    {
        // Grab a random character from our list
        $r = $chars{rand(0, $chars_length)};
       
        // Make sure the same two characters don't appear next to each other
        if ($r != $string{$i - 1}) $string .=  $r;
    }
   
    // Return the string
    return $string;
}
?>
