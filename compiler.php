<?php
class Symbol {
  static $table = array();
  private $name = null;
  public $symbol = true;
  public $macro = false;
  static function init () {}
  static function pull ($name) {
    if (array_key_exists ($name, self :: $table))
      return self :: $table [$name];
    else {
      $x = new Symbol ($name);
      self :: $table [$name] = $x;
      return $x;}}
  private function __construct ($name) {
    $this -> name = $name;}
  function __toString () {
    return $this -> name;}
  function lookup_in ($environment) {
    $x = array_reverse ($environment);
    foreach ($x as $env)
      if (array_key_exists ($this."", $env))
        return $env [$this.""];
    if (array_key_exists ($this."", Lisp :: $root))
      return Lisp :: $root [$this.""];
    if (function_exists ($this.""))
      return $this."";
    die ("Symbol lookup failed ".$this);}}

class Reader {
  static $ignore = "\t\n\, ";
  static function tok ($string) {
    $buf=array ();
    for ($i=0;$i<strlen ($string);$i++)
      array_push ($buf, $string [$i]);
    return $buf;}
  static function read (& $stream) {
    $output=array ();
    while (sizeof ($stream) > 0)
      if (strpbrk (Reader :: $ignore, $stream [0]))
        array_shift ($stream);
      else if ($stream [0] == ";")
        for(;$stream [0] != "\n";array_shift ($stream));
      else
        array_push ($output, self :: next ($stream));
    return $output;}
  static function next (& $stream) {
    if ($stream [0] == "\"") {
      return self :: string ($stream);}
    else if ($stream [0] == "'") {
      array_shift ($stream);
      return self :: quote ($stream);}
    else if ($stream [0] == "~") {
      array_shift($stream);
      return self :: unquote ($stream);}
    else if ($stream [0] == "(") {
      return self :: list_ ($stream);}
    else if ($stream [0] == "[") {
      return self :: vector ($stream);}
    else if ($stream[0] == "0" and $stream[1] == "x") {
      array_shift($stream);
      array_shift($stream);
      $x = self :: hexnumber($stream);
      return hexdec("0x".$x);}
    else if (is_numeric ($stream[0])) {
      return self :: number ($stream);}
    else if (!strpbrk (Reader :: $ignore, $stream [0])) { 
      return self :: symbol ($stream);}
    else {
      array_shift ($stream);}}
  static function symbol (& $stream) {
    $buf="";
    while (sizeof ($stream) != 0 and !strpbrk(Reader :: $ignore, $stream [0])) {
      $buf .= array_shift ($stream);
    }
    return ($buf == "null" or $buf == "nil") ? null : Symbol :: pull ($buf);
  }
  static function hexnumber (& $stream) {
    $buf="";
    while (sizeof ($stream) > 0 and
      $stream[0] != " " and
      in_array($stream[0], array("a","b","c","d","e","f")) or
      is_numeric($stream[0])) {
      $buf.=array_shift($stream);
    }
    return $buf;
  }
  static function number (& $stream) {
    $buf="";
    while (sizeof ($stream) != 0 and is_numeric ($stream [0])) 
      $buf .= array_shift ($stream);
    return intval ($buf);
  }
  static function list_ (& $stream) {
    $buf=array (array_shift ($stream));
    for ($i = 1; $i != 0; ) {
      if ($stream [0] == ")") $i--;
      else if ($stream [0] == "(") $i++;
      array_push ($buf, array_shift ($stream));}
    array_shift ($buf);
    array_pop ($buf);
    return self :: read ($buf);}
  static function vector (& $stream) {
    $buf=array (array_shift ($stream));
    for ($i=1;$i!=0;) {
      if ($stream [0] == "]") $i--;
      else if ($stream [0] == "[") $i++;
      array_push ($buf, array_shift ($stream));
    }
    array_shift ($buf);
    array_pop ($buf);
    return self :: read ($buf);
  }
  static function string (& $stream) {
    array_shift ($stream);
    $buf="";
    while(sizeof ($stream) != 0 and $stream [0] != "\"") {
      if ($stream [0] == "\\" and $stream [1] == "\"")
        array_shift ($stream);
      $buf .= array_shift ($stream);
    }
    array_shift ($stream);
    return $buf;}
  static function quote (& $stream) {
    $tmp = self :: next ($stream);
    return array (Symbol :: pull ("quote"), $tmp);}
  static function unquote (& $stream) {
    $tmp = self :: next ($stream);
    return array (Symbol :: pull ("unquote"), $tmp);}
  static function macro_expand($form) {
    if (is_array ($form)) {
      $output = array ();
      foreach ($form as $part)
        if (is_array ($part))
          array_push ($output, self::macro_expand ($part));
        else
          array_push ($output,$part);
      $op = $output [0];
      if($op instanceof Symbol and substr($op."", -1) == "." and $op."" != ".") {
        $output[0] = Symbol :: pull (substr($op,0, strlen($op) -1));
        $op = Symbol :: pull ("new");
        array_unshift($output, $op);}
      if($op instanceof Symbol and $op -> macro) {
        $op = Lisp :: eval1 ($op);
        array_shift ($output);
        $output = Lisp :: apply1 ($op, $output);
        $output = self :: macro_expand ($output);}
      return $output;}
    else
      return $form;}}

//$x = Reader :: tok ($x);
//$x = Reader :: read ($x);
//$x = $x[0];
//print "<pre>";
//var_dump($x);
//print "<br/>";
//function c($ar) {
//  $x = $ar[0]."";
//  switch($ar[0]."") {
//    case "def":
//      return "global \$".$ar[1].";\n\$".$ar[1]."=".$ar[2].";";
//  }
//}
//print c($x);

$buf="";
while(!feof(STDIN)){
  $buf.=fgets(STDIN);
}
$x = Reader :: tok($buf);
$x = Reader :: read ($x);

$FUNCTIONAL_TABLE=array();

function gensym () {
  global $FUNCTIONAL_TABLE;
  $x=rand_str();
  for($x=rand_str();in_array($x,$FUNCTIONAL_TABLE);$x=rand_str()){}
  array_push($FUNCTIONAL_TABLE, $x);
  return $x;
}

function compile($expr, &$oob) {
  if(is_array($expr)) {
    switch($expr[0].""){
      case "def":
        return compile_def($expr,$oob);
      case "fn":
        return compile_fn($expr,$oob);
      case "do":
        return compile_do($expr,$oob);
      default:
        return compile_call($expr,$oob);
    }
  } else {
    if ($expr instanceof Symbol){
      return "lookup(\"".$expr."\",\$env)";
    }
    return $expr."";
  }
}

function compile_def($expr, &$oob) {
  $name=$expr[1];
  $expr=compile($expr[2], $oob);
  return "\$".$name."=".$expr.";\n";
}

function compile_fn ($expr, &$oob)
{
  $args=$expr[1];
  array_shift($expr);
  array_shift($expr);
  array_unshift($expr,Symbol::pull("do"));
  $body=compile($expr, $oob);
  $buf="";
  foreach($args as $arg)
    $buf.="\"".$arg."\",";
  $buf=substr($buf,0,-1);
  $name=gensym();
  $f="function ".$name." (\$env){\n";
  $f.=$body;
  $f.="\n}";
  array_push($oob, $f);
  return "closure(\$env,array(".$buf."),\"".$name."\")";
}

function compile_do ($expr, &$oob)
{
  array_shift($expr);
  $last = array_pop($expr);
  $buf="";
  foreach($expr as $e)
    $buf.=compile($e,$oob);
  $buf.="return ".compile($last, $oob).";\n";
  return $buf;
}

function compile_call ($expr, &$oob)
{
  $name=array_shift($expr);
  $buf="";
  foreach($expr as $e)
    $buf.=compile($e,$oob).",";
  $buf=substr($buf,0,-1);
  return "call(".compile($name,$oob).",".$buf.")";
}


function lookup ($name,$env)
{
  if(array_key_exists($name,$env))
  {
    return $env[$name];
  }
  else
  {
    return $GLOBALS[$name];
  }
}

function call ()
{
  $x=func_get_args();
  $closure=array_shift($x);
  if(function_exists($closure))
    return call_user_func_array($closure,$x);
  $fun=$closure[2];
  $arg_names=$closure[1];
  $env=$closure[0];
  $args=array();
  foreach($x as $key => $value)
    $args[$arg_names[$key]]=$value;
  return call_user_func($fun,array_merge($env,$args));
}
$w=array();
$c="";
foreach($x as $r)
  $c.=compile($r,$w);
$w["locals"] = "";
$w=implode("\n",$w);
echo "<?php\n";
echo "function write(\$x){print \$x;}\n";
echo "function closure (\$env=null,\$args,\$name) {return array(\$env,\$args,\$name);}\n";
echo "function lookup (\$name,\$env) {return (array_key_exists(\$name,\$env)) ? \$env[\$name] : ((function_exists(\$name)) ? \$name : \$GLOBALS[\$name]);}\n";
echo "\$env=array();\n";
echo $w."\n";
echo $c."\n";
echo "?>";

// Generate a random character string
function rand_str($length = 32, $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz')
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
}?>
