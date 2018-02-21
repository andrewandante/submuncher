# SubMuncher

Handy tool for consolidating subnets into as few subnets as possible

## Installation

`composer require andrewandante/submuncher`

## Using it

The most basic usage is to pass an array of IPv4 addresses into `AndrewAndante\SubMuncher\SubMuncher::consolidate()`

There are also a bunch of helper IP utility functions in the `Validator` and `Util` classes, should you need to do some tweaking.

## Limitations

Only tested on IPv4 at present.