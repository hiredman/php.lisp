;php ./compiler.php < a.lisp | beautify_php -d -i 2 > a.php

(function get (thing key) (inline "$thing[$key]"))

(function call_closure (closure x)
  (set! args (array))
  (inline "foreach($x as $key=>$value) $args[$closure[1][$key]] = $value")
  (set! t (inline "$closure[0] == null"))
  (set! w (if (env t) (array) (get closure 0)))
  (call_user_func (get closure 2) (array_merge w args)))

(function call ()
  (set! x (func_get_args))
  (set! closure (get x 0))
  (array_shift x)
  (if (function_exists closure)
    (call_user_func_array closure x)
    (call_closure closure x)))

(function err (x)
  (file_put_contents "php://stderr" x)
  nil)

(function pr (stream string)
  (file_put_contents stream string)
  nil)

;;;;;;;;;;;;;;;
(print "<pre>")
;;;;;;;;;;;;;;;

(function println (x)
  (print x)
  (print "\n")
  nil)

(define first (λ (list) (get list 0)))

(define rest (λ (list) (array_shift list) list))

(define = (λ (a b) (inline "$a == $b")))

(define empty?
  (λ (list) 
    (if (= 0 (sizeof list))
      true
      false)))

(define reduce
  (λ (fun init list)
    (if (empty? list)
      init
      (recur fun (fun init (first list-)) (rest list)))))

(define cons
  (λ (a b)
    ((λ (list) (array_unshift list a) list)
     (if (= null b) (array) b))))

(define map
  (λ (f l)
    (if (empty? l)
      l
      (cons (f (first l))
            (map f (rest l))))))

(define filter
  (λ (pred list)
    (if (pred (first list))
      (cons (first list) (filter pred (rest list)))
      (filter pred (rest list)))))

(define + (λ (a b) (+ a b)))

(define ++ (λ (x) (+ 1 x)))

(define -- (λ (x) (- x 1)))

(define a (λ (& x) x))

;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;;

(println (first '(1 2 3)))
(println (reduce + 0 '(1 2 3)))
(println (reduce + 0 (map ++ '(1 2 3))))
(println (((λ (x) (λ () x)) 10)))
(println "foo")
(pr "php://stdout" "bar\n")
