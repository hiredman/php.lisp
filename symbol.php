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

function symbol($name)
{
  return Symbol::pull($name);
}

function is_symbol($x) {
  return ($x instanceof Symbol);
}

function symbol_str($symbol)
{
  return $symbol."";
}
?>
