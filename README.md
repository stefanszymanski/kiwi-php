# kiwi-php

A PHP port of the [Kiwi Java](https://github.com/alexbirkett/kiwi-java) implementation of the
Cassowary constraint solving algorithm

## Background

This project was created by porting kiwi-java line for line to PHP. Later is was optimized by
adopting code from other ports, especially [Kiwi Haxe](https://github.com/Tw1ddle/haxe-kiwi).

It was created to be used in a framework for interactive cli applications.

## Example usage

```php
$solver = new Solver();
$x = new Variable('x');
$y = new Variable('y');

// x = 20
$solver->addConstraint(Symbolics::equals($x, 20.0));

// x + 2 = y + 10
$solver->addConstraint(Symbolics::equals(
    Symbolics::add($x, 2.0),
    Symbolics::add($y, 10.0)
));

$solver->updateVariables();

echo sprintf('x = %f.1 | y = %f.1', $x->getValue(), $y->getValue());
// x = 20.0 | y = 12.0
```

## Links

* [Kiwi C++](https://github.com/nucleic/kiwi) 
* [Kiwi Java](https://github.com/alexbirkett/kiwi-java)
* [Kiwi Haxe](https://github.com/Tw1ddle/haxe-kiwi)
* [overconstrained.io](https://overconstrained.io)