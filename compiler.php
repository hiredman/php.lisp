<?php
require_once('symbol.php');

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
    return ($buf == "null" or $buf == "nil") ? null : symbol($buf);
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
    return array (symbol("quote"), $tmp);}
  static function unquote (& $stream) {
    $tmp = self :: next ($stream);
    return array (symbol("unquote"), $tmp);}
  static function macro_expand($form) {
    if (is_array ($form)) {
      $output = array ();
      foreach ($form as $part)
        if (is_array ($part))
          array_push ($output, self::macro_expand ($part));
        else
          array_push ($output,$part);
      $op = $output [0];
      if(is_symbol($op) and substr($op."", -1) == "." and $op."" != ".") {
        $output[0] = symbol(substr($op,0, strlen($op) -1));
        $op = symbol("new");
        array_unshift($output, $op);}
      if(is_symbol($op) and $op -> macro) {
        $op = Lisp :: eval1 ($op);
        array_shift ($output);
        $output = Lisp :: apply1 ($op, $output);
        $output = self :: macro_expand ($output);}
      return $output;}
    else
      return $form;}}

$buf="";
while(!feof(STDIN)){
  $buf.=fgets(STDIN);
}
$x = Reader :: tok($buf);
$x = Reader :: read ($x);

$FUNCTIONAL_TABLE=array();

function gensym ()
{
  global $FUNCTIONAL_TABLE;
  $x=rand_str(5);
  for($x=rand_str(5);in_array($x,$FUNCTIONAL_TABLE);$x=rand_str()){}
  array_push($FUNCTIONAL_TABLE, $x);
  return $x;
}

function compile ($expr, &$oob)
{
  global $ENVIRONMENT;
  if (is_array($expr))
  {
    switch($expr[0]."")
    {
      case "def":
        return compile_def($expr,$oob);
      case "fn":
        return compile_fn($expr,$oob);
      case "do":
        return compile_do($expr,$oob);
      case "«":
        compile_php($expr,$oob);
        return null;
      case "function":
        return compile_function($expr,$oob);
      case "macro":
        compile_macro($expr,$oob);
        return null;
      case "if":
        return compile_if($expr,$oob);
      case "quote":
        return var_export($expr[1], true);
      case "env":
        return compile_env($expr,$oob);
      case "inline":
        return compile_inline($expr,$oob);
      case "let":
        return compile_let($expr, $oob);
      case "-":
      case "*":
      case "/":
      case  "+":
        return compile_math($expr,$oob);
      default:
        return compile_call($expr,$oob);
    }
  }
  else
  {
    if (is_symbol($expr))
    {
      if ($expr."" == "true") return "true";
      if ($expr."" == "false") return "false";
      if ($expr."" == "nil") return "null";

      $p = count($oob["locals"]) - 1;
      if (is_array($oob["locals"][$p]) && in_array($expr,$oob["locals"][$p]))
      {
        return compile(array(symbol("env"), $expr),$oob);
      }

      $expr=mangle($expr);
      //return "(function_exists(\"".$expr."\") ? \"".$expr."\" : \$GLOBALS[\"".$expr."\"])";
      return "(\$GLOBALS[\"".$expr."\"] ? \$GLOBALS[\"".$expr."\"] : \"".$expr."\")";
    }
    if (is_string($expr))
      return "\"".$expr."\"";
    if ($expr == 0) return "0";
    if ($expr == null) return "null";
    return $expr."";
  }
}

function compile_def ($expr, &$oob)
{
  $name=$expr[1];
  $oob["name"]="_".mangle($name."");
  $expr=compile($expr[2], $oob);
  $oob["name"]=null;
  return "\$".mangle($name)."=".$expr."";
}

function compile_fn ($expr, &$oob)
{
  global $ENVIRONMENT;
  $name=($oob["name"] == null) ? $name=gensym() : $oob["name"];
  $name_args = $oob["name_args"];
  $oob["name"] = null;
  $oob["name_args"] = null;
  $args=$expr[1];
  array_push($oob["locals"], $args);
  array_shift($expr);
  array_shift($expr);
  $syms = extract_symbols($expr);
  array_unshift($expr,symbol("do"));
  $body=compile($expr, $oob);
  $buf="";
  $buf3="";
  foreach($args as $arg)
  {
    $buf.="\"".$arg."\",";
    $buf3.="\$".$arg.",";
  }
  $buf=substr($buf,0,-1);
  $buf3=substr($buf3,0,-1);
  $f="";
  if ($name_args == null )
  {
    $f.="function ".$name." (".$ENVIRONMENT.")\n{";
    foreach(array_unique(array_merge($syms,$args)) as $s)
      if (!in_array($s."", array("+")))
        $f.="\$".mangle($s)."=".$ENVIRONMENT."[\"".$s."\"];";
    $f.="\n  //\n";
  }
  else
  {
    $f.="function ".$name." (".mangle($buf3).")\n{\n";
  }
  $f.=$body;
  $f.="\n}";
  eval($f);
  array_push($oob, $f);
  $buf2="";
  foreach($syms as $s)
    $buf2.="\"".$s."\",";
  $buf2=mangle(substr($buf2,0,-1));
  array_pop($oob["locals"]);
  return "call(".compile(symbol("closure"), $oob).",extend(".$ENVIRONMENT.",".$buf2."),array(".$buf."),\"".$name."\")";
  //foreach($syms as $s)
  //  $buf2.="\"".$s."\"=>".$ENVIRONMENT."[\"".$s."\"],";
  //$buf2=mangle(substr($buf2,0,-1));
  //array_pop($oob["locals"]);
  //return "call(".compile(Symbol::pull("closure"), $oob).",".$buf2."),array(".$buf."),\"".$name."\")";

}

function extract_symbols ($expr)
{
  $output=array();
  $sub=array();
  foreach ($expr as $e)
  {
    if (is_symbol($e))
      array_push($output, $e);
    if (is_array($e))
      $output = array_merge($output, extract_symbols($e));
  }
  return $output;
}

function compile_php ($expr, &$oob)
{
  array_shift($expr);
  $name=array_shift($expr)."";
  $args=array_shift($expr)."";
  $body=array_shift($expr)."";
  $f="function ".$name." (".$args.")\n{\n";
  $f.=$body;
  $f.="\n}\n";
  array_push($oob,$f);
}

function compile_function ($expr, &$oob)
{
  array_shift($expr);
  $name=mangle(array_shift($expr)."");
  array_unshift($expr,symbol("fn"));
  $oob["name"] = $name."";
  $oob["name_args"] = true;
  compile($expr, $oob);
  return null;
}

function compile_inline ($expr, &$oob)
{
  return $expr[1];
}

function compile_do ($expr, &$oob)
{
  array_shift($expr);
  $last = array_pop($expr);
  $buf="";
  foreach($expr as $e)
    $buf.="  ".compile($e,$oob).";\n";
  $buf.="  return ".compile($last, $oob).";";
  return $buf;
}

function compile_call ($expr, &$oob)
{
  static $php_forms = array("print","array");
  $name=array_shift($expr);
  if (array_key_exists($name."", $oob["macros"]))
  {
    array_unshift($expr,$oob["macros"][$name.""]);
    return compile(call_user_func_array("call",$expr), $oob); 
  }
  $buf=",";
  foreach($expr as $e)
    $buf.=compile(is_symbol($e) ? array(symbol("env"),$e) : $e,$oob).",";
  $buf=substr($buf,0,-1);
  $buf=($buf == ",") ? "" : $buf;
  if(function_exists($name."") or in_array($name."", $php_forms))
    return "".$name."(".substr($buf,1,strlen($buf)).")";
  return "call(".compile($name,$oob).$buf.")";
}

function compile_env ($expr, &$oob)
{
  global $ENVIRONMENT;
  list($_, $name) = $expr;
  return "\$".mangle($name."");
}

function compile_math ($expr, &$oob)
{
  $op=array_shift($expr)."";
  $buf="";
  foreach($expr as $e)
    $buf.=compile($e,$oob).$op;
  $buf=substr($buf,0,-1);
  $buf="(".$buf.")";
  return $buf;
}

function compile_if ($expr, &$oob)
{
  list($_, $pred, $true, $false) = $expr;
  return "((".compile($pred,$oob)." != null) ? (".compile($true,$oob).") : (" . compile($false,$oob)."))";
}

function compile_macro ($expr, &$oob)
{
  list($_, $name, $fn) = $expr;
  $m = array();
  $e = compile($fn,$m);
  eval($m[0]);
  $e=explode("\"", $e);
  array_pop($e);
  $nam=array_pop($e);
  array_shift($fn);
  $args = array_shift($fn);
  $fn = array(array(),$args,$nam);
  $oob["macros"][symbol_str($name)] = $fn;
}

function mangle ($name)
{
  static $x = array(
    "+" => "__PLUS__",
    "?" => "__QMARK__",
    "!" => "__BANG__",
    "-" => "__HYPHEN__",
    "=" => "__EQUAL__"
  );
  $name=symbol_str($name);
  foreach($x as $k => $v)
    $name=str_replace($k,$v,$name);
  return $name;
}

$ENVIRONMENT="\$E__";

$bootstrap = '
(« call "" "
  $x = func_get_args();
  $closure=first($x);
  $x=rest($x);
  if (function_exists($closure))
    return call_user_func_array($closure,$x);
  $args=array();
  foreach($x as $key => $value) $args[$closure[1][$key]] = $value;
  $closure[0] = ($closure[0] == null) ? array() : $closure[0];
  return call_user_func($closure[2], array_merge($closure[0],$args));
  ")
(« extend "" "
  $x = func_get_args();
  $env = array_shift($x);
  $env = ($env == null) ? array() : $env;
  $output=array();
  foreach ($x as $y)
  {
    if (array_key_exists($y,$env))
      $output[$y]=$env[$y];
  }
  return $output;
")
';

$out_of_band=array();
$bootstrap = Reader :: read (Reader :: tok ($bootstrap));
foreach($bootstrap as $form)
  compile($form, $out_of_band); 
foreach($out_of_band as $f)
  eval($f);

$out_of_band["macros"]=array();
$out_of_band["locals"]=array();
$c="";

foreach ($x as $r)
{
  $c.=compile($r,$out_of_band).";\n";
}

$out_of_band["locals"] = "";
$out_of_band["macros"] = "";
$out_of_band=implode("\n",$out_of_band);

echo "<?php\n";
echo "/* Input\n";
echo $buf;
echo "*/\n\n";
echo $ENVIRONMENT."=array();\n\n";
echo $out_of_band."\n\n";
echo $c."\n";
echo "?>";

// Generate a random character string
function rand_str($length = 32, $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz')
{
    static $num = 0;
    $num++;
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
    return $string.$num;
}
?>
