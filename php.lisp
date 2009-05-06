(def symbol (Primitive. "$x" "return Symbol :: pull ($x);"))
(def + (Primitive. "" "$x=func_get_args();return array_sum($x);"))
(def first (Primitive. "$x" "return $x[0];"))
(def rest (Primitive. "$x" "array_shift($x); return $x;"))
(def cons (Primitive. "$element,$list" "array_unshift($list,$element); return $list;"))
(def snoc (Primitive. "$element,$list" "array_push($list,$element); return $list;"))
(def list (Primitive. "" "$x=func_get_args();return $x;"))
(def = (Primitive. "" "$x=func_get_args(); $tmp=array_shift($x); foreach($x as $y) if ($y != $tmp) return null; return true;"))
(def - (Primitive. "" "$x=func_get_args(); $tmp=array_shift($x); foreach($x as $num) $tmp -= $num; return $tmp;"))
(def / (Primitive. "" "$x=func_get_args(); $tmp=array_shift($x); foreach($x as $num) $tmp = $tmp / $num; return $tmp;"))
(def * (Primitive. "" "$x=func_get_args(); $tmp=1; foreach($x as $num) $tmp *= $num; return $tmp;"))
(def mod (Primitive. "$a, $b" "return $a % $b ;"))
(def die (Primitive. "$m" "die($m);"))
(def read (Primitive. "$x" "return Reader :: read (Reader :: tok ($x));"))
(def fn? (Primitive. "$x" "return ($x instanceof Callable);"))
(def aset (Primitive. "&$a,$idx,$v" "$a[$idx]=$v;return $a;"))
(def GET (Primitive. "$x" "return $_GET[$x];"))
(def print (Primitive. ""
                       "$x=func_get_args();
                        $tmp='';
                        foreach($x as $a) $tmp.=$a;
                        print $tmp;"))
(def load (Primitive. ""
                      "$x=func_get_args();
                       $tmp=null;
                       foreach($x as $file)
                        foreach(Reader :: read (Reader :: tok (file_get_contents ($file))) as $form)
                          $tmp = Lisp :: eval1 (Reader :: macro_expand ($form));
                       return $tmp;"))
(def set-macro! (Primitive. "$s" "$s->macro=true;return $s;"))
(set-macro! 'set-macro!)
(def defmacro
  (fn* (name_ args_ & body_)
    (list 'do
          (list 'def name_ (list 'fn* args_ (cons 'do body_)))
          (list 'set-macro! name_))))

(set-macro! defmacro)

(defmacro defn [name args & body]
  '(def ~name (fn* ~args ~(cons 'do body))))

(defn atom [v]
  (Atom. v))

(defn set! [a v]
  (. a setValue v))

(defn get [a]
  (. a getValue))

(defmacro or [a b]
  (list 'let (list 'or_a a 'or_b b)
        (list 'if 'or_a 'or_a (list 'if 'or_b 'or_b nil))))

(defn trampoline [fn] (let [x (fn)] (if (fn? x) (recur x) x)))

(def identity (fn* [x] x))

(defn f [x]
  (or (= 0 (mod x 3)) (= 0 (mod x 5))))

(defn dec [x] (- x 1))
(defn inc [x] (+ x 1))

(defn map [fn li]
  (loop [f fn l li a '()]
    (if (= 0 (sizeof l))
      a
      (recur f (rest l) (snoc (f (first l)) a)))))

(defn reduce [f l]
  (let [x1 (first l)
        x2 (first (rest l))
        x3 (rest (rest l))
        x4 (f x1 x2)]
    (if (= (sizeof x3) 0)
      x4
      (recur f (cons x4 x3)))))

(defn filter [f l]
  (reduce (fn* [a x]
               (if (f x)
                 (snoc x a)
                 a))
          (cons '() l)))

(defn f [x]
  (if (or (= 0 (mod x 3)) (= 0 (mod x 5)))
    x
    nil))

(defn g [x y] (reduce + (filter f (range x y))))

(defn h [x]
  (loop [x x a '()]
    (if (= 1000 x)
      a
      (recur (+ x 100) (cons (g x (+ x 99)) a)))))

(print "
<!DOCTYPE html PUBLIC \"-//W3C//DTD XHTML 1.0 Strict//EN\" \"http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd\">
<html xmlns=\"http://www.w3.org/1999/xhtml\" xml:lang=\"en\" lang=\"en\">
  <head><title>PHP Lisp</title></head>
  <body>
    <h1>" (time) "</h1>
    <ul>
     <li><a href=\"php.lisp\">Lisp Source</a></li>
     <li><a href=\"lisp5.phps\">PHP Source</a></li>
     <li>Project Euler Problem #1 " (reduce + (h 0)) "</li>
     <li>" (str (first (read "(foo bar baz baz)"))) "</li>
     <li>PHP says: " (php "return \"Hello World\";" ) "</li>
     <li>" (str (map inc (range 0 9))) "</li>
     <li>" (let [x (atom 1)] (set! x 2) (get x)) "</li>
     <li>" (str ((fn* [a & b] b) 3 3 3 3 3 3)) "</li>
     <li>" (trampoline (fn* [] (fn* [] 1))) "</li>
     <li>" (str (let [x 1] '(a b c ~x))) "</li>
     <li>" (str (. (Recur. '(1 2 3)) values)) "</li>
     <li>" (load "foo.lisp") "</li>
    </ul>
    F&ograve;OBAR
  </body>
</html>")
