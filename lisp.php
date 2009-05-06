<?php

function array_push_assoc($array, $key, $value){
 $array[$key] = $value;
  return $array;
  }

class Node {
  private $value = null;
  private $next = null;
  function __construct($v, &$ptr=null){
    $this->value = $v;
    $this->next = $ptr;}
  function __toString() {
    return "".$this->value;}
  function &car () {
    return $this->value;}
  function &cdr () {
    return $this->next;}}

class LList {
  private $list = null;
  private $len = null;
  function __construct (&$x=null) {
    if ($x != null) {
      $this->list = $x;}
    else {
      $this->list = null;}}
  function cons ($item){
    return new LList(new Node($item, $this->list));}
  function first () {
    if ($this->list == null)
      return null;
    else 
      return $this->list->car();}
  function rest () {
    if ($this->list == null)
      return $this;
    else
      return new LList($this->list->cdr());}
  function Pempty () {
    if ($this->list == null)
      return true;
    else
      return false;}
  function toArray () {
    $a=array();
    for($n=$this;!$n->Pempty();$n=$n->rest())
      array_push($a,$n->first());
    return $a;}
  function length () {
    if ($this->len == null){
      $i=0;
      if($this->list != null)
        for($i++,$n=$this->list;$n->cdr() != null; $n=$n->cdr()) { $i++;}
      $this->len=$i;}
    return $this->len;}
  function reverse () {
    $l=new LList();
    for($n=$this;!$n->Pempty();$n=$n->rest()) {
      $l=$l->cons($n->first());}
    return $l;}
  function __toString () {
    $s="";
    for($n=$this;!$n->Pempty();$n=$n->rest()) {
      $s=$s." ".$n->first();}
    return "(".trim($s).")";}
  public static function llist () {
    $x=func_get_args();
    $x=array_reverse($x);
    $y=new LList();
    foreach($x as $i)
      $y=$y->cons($i);
    return $y;}
  function type () {return "list";}}

class Symbol {
  private $string=null;
  function __construct ($string) {
    $this->string=$string;}
  function get () {
    return "$".$this->string;}
  function __toString() {
    return "".$this->string;}
  function type () {return "symbol";}}

class AArray {
  private $arr=null;
  function __construct ($arr) {
    $this->arr=$arr;}
  function get () {
    return $this->arr;}
  function __toString () {
    $buf="";
    for($i=0;$i<sizeof($this->arr);$i++) {
      $buf.=" ".$this->arr[$i];}
    return "[".trim($buf)."]";}}

class Keyword {
  private $name=null;
  function __construct ($name) {
    $this->name = $name;}
  function __toString () {
    return ":".$this->name;}
  function type () {return "keyword";}}

class ImutableMap {
  private $keys = array();
  private $values = array();
  function __construct ($keys=null, $values=null) {
    if ($keys != null and $values != null) {
      $this->keys=$keys;
      $this->values=$values;}}
  function assoc ($key, $value) {
      $k = $this->keys;
      $v = $this->values;
      array_push($k, $key);
      array_push($v, $value);
      return new ImutableMap($k, $v);}
  function __toString () {
    $buf="";
    for($i=0;$i<sizeof($this->keys);$i++) {
      $buf.=" ".$this->keys[$i]." ".$this->values[$i].",";}
    $buf=substr($buf,0,strlen($buf)-1);
    return "{".trim($buf)."}";}
  function get ($key) {
    $x = array_search($key, $this->keys);
    if ($x === false)
      return null;
    else
      return $this->values[$x];}}

class RT {

  const special_chars = "\"(){}[]";
  public static $root = null;
  public static $bindings = null;

  public static function tokenizer ($string) {
    $tokens=new LList();
    while(strlen($string) > 0){
      $tokens=$tokens->cons($string[0]);
      $string=substr($string,1,strlen($string));}
    return $tokens->reverse();}

  public static function coaless ($list) {
    $result = new LList ();
    while (!$list->Pempty()) {
      if (is_numeric($list->first())) {
        $buf="";
        while (!$list->Pempty() && is_numeric($list->first())) {
          $buf.=$list->first();
          $list=$list->rest();}
        $result=$result->cons(intval($buf));}
      elseif ($list->first() != " " && (strstr(self::special_chars, $list->first()) === false)) {
        $buf="";
        while (!$list->Pempty() && $list->first() != " " && (strstr(self::special_chars, $list->first()) === false)) {
          $buf.=$list->first();
          $list=$list->rest();}
        $result=$result->cons(new Symbol($buf));}
      elseif ($list->first() == "\"") {
		$buf="";
		$list=$list->rest();
		for(;!$list->Pempty() and $list->first() != "\"";$list=$list->rest())
		  $buf.=$list->first();
		$result=$result->cons($buf);
	  }
      elseif ($list->first() == "(") {
        $buf=new LList ();
        $list=$list->rest();
        for($i=1;$i>0;$list=$list->rest()) {
          $buf=$buf->cons($list->first());
          if ($list->first() == "(")
            $i++;
          elseif ($list->first() == ")")
            $i--;}
        $buf=$buf->rest();
        $result=$result->cons(self::coaless($buf->reverse()));}
      elseif ($list->first() == "[") {
        $buf=new LList ();
        $list=$list->rest();
        for($i=1;$i>0;$list=$list->rest()) {
          $buf=$buf->cons($list->first());
          if ($list->first() == "[")
            $i++;
          elseif ($list->first() == "]")
            $i--;}
        $a=array();
        for($buf=self::coaless($buf->rest());!$buf->Pempty();$buf=$buf->rest()) {
          array_push($a,$buf->first());}
        $result=$result->cons(new AArray ($a));}
      else {
        if ($list->first() != " ")
          $result=$result->cons($list->first());
        $list=$list->rest();}}
    return $result->reverse();}
  public static function read($string) {
    return RT::coaless(RT::tokenizer($string));}
  public static function read_one($string) {
    $x=self::read($string);
    return $x->first();}
  public static function p_eval($form,$env=null) {
	print $form."\n";
    if (is_object($form) && $form->type() == "list") {
	  print $form."\n";
      $s = self::resolve($form->first(),$env);
	  $a = new LList();
	  for($n=$form->rest();!$n->Pempty();$n=$n->rest())
		$a=$a->cons(self::p_eval($n->first()));
      return $s->call_array($a->reverse()->toArray());}
    elseif (is_object($form) and $form->type() == "symbol") {
	  print $form."\n";
	  print RT::$root."\n";
	  print self::resolve($form,$env)."\n";
      return self::resolve($form,$env);}
    else
      return $form;}
  public static function def ($symbol, $value) {
    if (self::$root == null)
      self::$root = new ImutableMap();
    self::$root = self::$root->assoc($symbol,$value);
    return null;}
  public static function resolve ($symbol,$env=null) {
    if ($env == null) {
      return self::$root->get($symbol);}
    else {
      for($n=$env;!$n->Pempty();$n=$n->rest()) {
        $x=$n->first()->get($symbol);
        if($x != null)
          return $x;}
	  return self::resolve($symbol);}}
  public static function new_frame() {
    if (self::$bindings == null)
      self::$bindings = new LList();}
  public static function pop_bindings() {
    self::$bindings=self::$bindings->rest();}
  public static function push_bindings ($symbols,$values) {
    self::new_frame();
    $c=new ImutableMap();
    while (!$symbols->Pempty()) {
      $c=$c->assoc($symbols->first(),$values->first());
      $symbols=$symbols->rest();
      $values=$values->rest();}
    self::$bindings=self::$bindings->cons($c);}}

class Primitive {
  private $func = null;
  private $macro = null;
  function __construct ($args, $body, $macro=null) {
    $a="";
    foreach($args->get() as $val)
      $a.=$val->get().",";
    $a=substr($a,0,strlen($a)-1);
    $this->func=create_function($a, $body);
	if ($macro != null)
	  $this->macro = true;
	else
	  $this->macro = false;
    return null;}
  function Pmacro() {
	return $this->macro;}
  function call_array($a) {
    return call_user_func_array($this->func, $a);}
  function call () {
    $x=func_get_args();
    return $this->call_array($x);}
  function __toString () {return "&lt;PRIMITIVE&gt;";}}

class FFunction {
 private $code = null; 
 private $args = null;
 private $env = null;
 function __construct ($args, $code, $env=null) {
  $this->args=$args;
  $this->code=$code;
  $this->env=$env;}
}

print "<pre>";

$y=RT::read_one("[]");
$x=new Primitive($y, '$a=func_get_args();$b=0;foreach($a as $c) $b+=$c;return $b;');
$z=new Symbol("+");
RT::def($z, $x);
print RT::$root;
print "\n";
print RT::resolve($z)->call(1 ,2);
print "\n";
$s = LList::llist(new Symbol("a"), new Symbol("b"));
$v = LList::llist(1, 2);
RT::push_bindings($s,$v);
print RT::$bindings;
print "\n";
print RT::resolve(new Symbol("a"), RT::$bindings);
print "\n";

RT::def(new Symbol("resolve"), new Primitive(RT::read_one("[a]"),'return RT::resolve($a);'));
RT::def(new Symbol("def"), new Primitive(RT::read_one("[a b]"),'return RT::def($b, $a);', true));
RT::def(new Symbol("p_eval"), new Primitive(RT::read_one("[form]"),'return RT::p_eval($form);'));
RT::def(new Symbol("list"), new Primitive(RT::read_one("[]"),
										 '$a=func_get_args();
										  return call_user_func_array("LList::llist",$a);'));
RT::def(new Symbol("cons"), new Primitive(RT::read_one("[a b]"),
										   'return $a->cons($b);'));
RT::p_eval(RT::read_one("(def x 1)"));
RT::p_eval(RT::read_one('(def y "hello")'));
print "\n";
print RT::resolve(new Symbol("y"));
print "\n";
print RT::p_eval(RT::read_one('(cons y (list 1 2 3 4))'), RT::$bindings);
?>
