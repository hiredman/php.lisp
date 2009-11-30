<?php
require_once('symbol.php');
require_once('reader.php');

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
      case "recur":
        return compile_recur($expr, $oob);
      case "set!":
        return compile_set($expr, $oob);
      case "ref":
        return compile_ref($expr, $oob);
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

function compile_set ($expr, &$oob)
{
  list($_, $L, $R) = $expr;
  return compile(array(symbol("env"),$L),$oob)."=".compile($R,$oob);
}

function compile_fn ($expr, &$oob)
{
  global $ENVIRONMENT;
  global $RECUR_KEY;
  $name=($oob["name"] == null) ? $name=gensym() : $oob["name"];
  $name_args = $oob["name_args"];
  $oob["name"] = null;
  $oob["name_args"] = null;
  $args=$expr[1];
  array_push($oob["locals"], $args);
  array_shift($expr);
  array_shift($expr);
  $syms = extract_symbols($expr);
  $tail = array_pop($expr);
  array_unshift($expr,symbol("do"));
  $body=compile($expr, $oob);
  $tail_name=$ENVIRONMENT;
  $tail="".$tail_name."=".compile($tail, $oob).";\n";
  $body.="\n".$tail;
  $body="".$body."\n  }while(is_array(".$tail_name.") && ".$tail_name."[\"".$RECUR_KEY."\"] == true);\n";
  $body.="return ".$tail_name.";\n";
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
    $f.="function ".$name." (".$ENVIRONMENT.")\n{\n  do{";
    foreach(array_unique(array_merge($syms,$args)) as $s)
      if (!in_array($s."", array("+")))
        $f.="\$".mangle($s)."=".$ENVIRONMENT."[\"".$s."\"];";
  }
  else
  {
    $f.="function ".$name." (".mangle($buf3).")\n{\n  do{";
  }
  //$f.=$ENVIRONMENT."[\"".$RECUR_KEY."\"] = false;";
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
}

function compile_recur ($expr, &$oob)
{
  global $ENVIRONMENT;
  global $RECUR_KEY;
  array_shift($expr);
  $names=$oob["locals"][sizeof($oob["locals"])-1];
  $m="";
  for($i=0;$i<sizeof($names);$i++)
    $m.="\"".$names[$i]."\"=>".compile($expr[$i],$oob).",";
  $m.="\"".$RECUR_KEY."\" => true,";
  $m="array_merge(".$ENVIRONMENT.",array(".substr($m,0,-1)."))";
  return $m;
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
  //$last = array_pop($expr);
  $buf="";
  foreach($expr as $e)
    $buf.="  ".compile($e,$oob).";\n";
  //$buf.="  return ".compile($last, $oob).";";
  return $buf;
}

function compile_call ($expr, &$oob)
{
  static $php_forms = array("print","array");
  $name=array_shift($expr);
  $oob["macros"] = is_array($oob["macros"]) ? $oob["macros"] : array();
  if (array_key_exists(symbol_str($name), $oob["macros"]))
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
$RECUR_KEY=gensym();

$bootstrap = '
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
  $tmp=compile($r,$out_of_band);
  $c.=$tmp;
  $c.= ($tmp == "") ? "" : ";\n";
}

$out_of_band["locals"] = "";
$out_of_band["macros"] = "";
$out_of_band=implode("\n",$out_of_band);

echo "<?php\n";
echo $ENVIRONMENT."=&\$GLOBALS;\n\n";
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
