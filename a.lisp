(print "<pre>")

(function println [x]
  (print x)
  (print "\n"))

(function closure [environment args name]
  (array environment args name))

(function get [thing key] (inline "$thing[$key]"))

(function first [list] (get list 0))

(function rest [list-]
  (array_shift list-)
  list-)

(function = [a b]
  (inline "$a == $b"))

(function empty? [list]
  (if (= 0 (sizeof list))
    true
    false))

(def reduce
  (fn [fun init list-]
    (if (empty? list-)
      init
      (recur fun (fun init (first list-)) (rest list-)))))

(def + (fn [a b] (+ a b)))

;(function + [a b] (+ a b))

(def x
  (fn [w]
    (if (= 0 w)
      w
      (recur (- w 1)))))

(println (first '(1 2 3)))
(println (reduce + 0 '(1 2 3)))
(println (x 10))
(println "foo")

