<?php
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


?>
