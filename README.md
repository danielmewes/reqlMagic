# reqlMagic
A "proof of concept" program that automatically evolves ReQL functions to match provided example input+output pairs.

You'll need a running RethinkDB server for this to work.

Run `test.php` to evolve a ReQL function. It will go through a number of iterations and then print out the best candidates.

The input+output pairs are currently hard-coded in traversal.php.
A few examples:
```php
// E.g. `function($x) { return [$x, $x->mul($x)]; }`
$inputs = array(1, 2, 3, 4);
$outputs = array(array(1, 1), array(2, 4), array(3, 9), array(4, 16));

// E.g. `function($x) { return $x->nth(0)->add($x->nth(1)); }`
$inputs = array(array(1, 2), array(5, 5), array(2, 3));
$outputs = array(3, 10, 5);

// E.g. `function($x) { return [$x->nth(1), $x->nth(0)]; }`
$inputs = array(array(1, 2), array(5, 2));
$outputs = array(array(2, 1), array(2, 5));
```

The supported terms are currently pretty limited.
