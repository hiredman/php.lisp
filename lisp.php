<?php
class Symbol {
  static $table = null;
  private $name = null;
  public $symbol = true;
  public $macro = false;
  static function init () {
    self :: $table = array ();}
  static function pull ($name) {
    if (array_key_exists ($name,self :: $table))
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

interface Callable {
  function call ($args);}

class Func implements Callable {
  private $code = null;
  private $environment = null;
  private $parameters = null;
  private $name = null;
  function __construct ($parameters, $code, $environment, $name) {
    array_unshift ($code, Symbol :: pull ("do"));
    $this -> code = $code;
    $this -> parameters = $parameters;
    $this -> environment = $environment;
    $this -> name = $name;}
  function __toString () {
    return "<#FUNCTION ".$this -> name." #>";}
  function call ($args) {
    $params = $this -> parameters;
    if (in_array (Symbol :: pull ("&") , $params)) {
      $name = array_pop ($params);
      array_pop ($params);
      $first = array_slice ($args, 0, sizeof($params));
      $rest = array_slice ($args, sizeof($first), sizeof($args));
      array_push ($first, $rest);
      $args = $first;
      array_push ($params, $name);}
    if (sizeof ($args) != sizeof ($params))
      die("PARAM COUNT MISMATCH");
    $e = $this -> environment;
    $tmp = Lisp :: let_ ($params, $args, $this -> code, $e, false);
    while ($tmp instanceof Recur)
      $tmp = Lisp :: let_ ($params, $tmp -> values, $this -> code, $e, false);
    return $tmp;}}

class Primitive implements Callable {
  private $name = null;
  public $macro = false;
  function __toString () {
	return "&lt;PRIMITIVE".$this -> name."&gt;";}
  function __construct ($args, $body) {
	$this -> name = create_function ($args, $body);}
  function call ($args) {
	return call_user_func_array ($this -> name, $args);}}

class Recur {
  public $values = null;
  function __construct ($v) {
    $this -> values = $v;}}

class Atom {
  private $value = null;
  function __construct ($v) {
    $this->value = $v;}
  function getValue () {
    return $this -> value;}
  function setValue ($v) {
    $this->value = $v;
    return null;}}

function str ($form) {
  if (is_array ($form)) {
    $buf="";
    foreach ($form as $i)
      $buf.=" ".str ($i);
    return "(".trim ($buf).")";}
  elseif (is_string ($form))
    return '"'.str_replace ('"', '\"', $form).'"';
  else if ($form == null)
    return "nil";
  else
    return $form."";}

function pr ($form) {
  print str ($form);}

function prn ($form) {
  pr ($form);
  print "\n";}

/****************************************************************************/
class Lisp {
  static $root = null;
  static function init () {
    self :: $root = array ();
    Symbol :: init ();}
  static function def ($symbol, $value) {
    self :: $root [$symbol.""] = $value;
    return $symbol;}
  static function eval1 ($expression, $environment=null) {
    $environment = ($environment == null) ? array () : $environment;
    if (!is_array ($expression)) {
      if (is_object ($expression))
        if ($expression instanceof Symbol)
          return $expression -> lookup_in ($environment);
        else
          return $expression;
      else
        return $expression;}
    else {
      switch ($expression [0]."") {
        case "quote":
          return self :: quote ($expression, $environment);
        case "if":
          return self :: if_ ($expression, $environment);
        case "do":
          return self :: do_ ($expression, $environment);
        case "fn*":
          return self :: fn ($expression, $environment);
        case "let":
          return self :: let ($expression, $environment);
        case "def":
          return self :: def ($expression [1], self :: eval1 ($expression [2], $environment));
        case "new":
          return self :: new_ ($expression, $environment);
        case "php":
          return self :: php ($expression, $environment);
        case "loop":
          return self :: loop ($expression, $environment);
        case "recur":
          return self :: recur ($expression, $environment);
        case ".":
          return self :: dot ($expression, $environment);
        default:
          $b = array ();
          foreach ($expression as $part)
            array_push ($b, self :: eval1 ($part, $environment));
          $op = array_shift ($b);
          return self :: apply1 ($op, $b);
          break;}}
    return null;}
  static function dot ($expression, $environment) {
    array_shift ($expression);
    $obj = array_shift ($expression);
    $obj = self :: eval1 ($obj, $environment);
    $class = new ReflectionClass (get_class($obj));
    $method = array_shift ($expression)."";
    $expression = self :: map_eval ($expression, $environment);
    if ($class -> hasMethod ($method))
      return $class -> getMethod ($method) -> invokeArgs ($obj, $expression);
    else {
      if (sizeof ($expression) > 0) {
        $class -> getProperty ($method) -> setValue ($obj, $expression [0]);
        return $obj;}
      else
        return $class -> getProperty ($method) -> getValue ($obj);}
    return null;}
  static function fn ($expression, $environment) {
    array_shift ($expression);
    $name = Symbol :: pull ("this");
    if (is_array ($expression [0]))
      $args = array_shift ($expression);
    else {
      $name = array_shift ($expression);
      $args = array_shift ($expression);}
    return new Func ($args, $expression, $environment, $name);}
  static function do_ ($expression, $environment) {
    array_shift ($expression);
    $b = null;
    foreach ($expression as $part)
      $b = self :: eval1 ($part, $environment);
    return $b;}
  static function recur ($expression, $environment) {
    array_shift ($expression);
    return new Recur (self :: map_eval ($expression, $environment));}
  static function loop ($expression, $environment) {
    array_shift ($expression);
    $bindings = array_shift ($expression);
    list ($names, $values) = self :: binding_form ($bindings);
    array_unshift($expression, Symbol :: pull ("do"));
    $tmp = self :: let_ ($names, $values, $expression, $environment);
    while ($tmp instanceof Recur)
      $tmp = self :: let_ ($names, $tmp -> values, $expression, $environment, false);
    return $tmp;}
  static function let_ ($names, $values, $expression, $environment, $eval = true) {
    $frame = array ();
    array_push ($environment, $frame);
    foreach ($names as $key => $name) {
      $frame [$name.""] = $eval ? self :: eval1 ($values [$key], $environment) : $values [$key];
      array_pop ($environment);
      array_push ($environment, $frame);}
    return self :: eval1 ($expression, $environment);}
  static function binding_form ($form) {
    $names = array ();
    $values = array ();
    foreach ($form as $key => $value)
      if ($key == 0 or ($key % 2) == 0)
        array_push ($names, $value);
      else
        array_push ($values, $value);
    return array ($names, $values);}
  static function let ($expression, $environment) {
    array_shift ($expression);
    $bindings = array_shift ($expression);
    list ($names, $values) = self :: binding_form ($bindings);
    array_unshift($expression, Symbol :: pull ("do"));
    return self :: let_ ($names, $values, $expression, $environment);}
  static function if_ ($expression, $environment) {
    if (self :: eval1 ($expression [1], $environment) != null)
      return self :: eval1 ($expression [2], $environment);
    else
      return self :: eval1 ($expression [3], $environment);}
  static function quote ($expression, $environment) {
    array_shift ($expression);
    return self :: unquote ($expression [0], $environment);}
  static function unquote ($expression, $environment) {
    if (is_array ($expression))
      if ($expression [0]."" == "unquote")
        return self :: eval1 ($expression [1], $environment);
      else {
        $tmp = array ();
        foreach ($expression as $e)
          array_push ($tmp, self :: unquote ($e, $environment));
        return $tmp;
      }
    else
      return $expression;
    return $expression;
  }
  static function php ($expression, $environment) {
    return eval ($expression [1]);}
  static function new_ ($expression, $environment) {
    array_shift ($expression);
    $name = array_shift ($expression)."";
    $class = new ReflectionClass ($name);
    return $class -> newInstanceArgs (self :: map_eval ($expression, $environment));}
  static function map_eval ($list_, $environment) {
    $tmp = array ();
    foreach ($list_ as $l)
      array_push ($tmp, self :: eval1 ($l, $environment));
    return $tmp;}
  static function apply1 ($op, $args) {
    if ($op instanceof Callable)
      return $op -> call ($args);
    return call_user_func_array ($op, $args);}}

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
/****************************************************************************/
Lisp :: init ();
$x = file_get_contents("php.lisp");
foreach(Reader :: read (Reader :: tok ($x)) as $form) {
 Lisp :: eval1 (Reader :: macro_expand ($form));}
?>
