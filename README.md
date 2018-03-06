# SubMuncher

Handy tool for consolidating subnets into as few subnets as possible

[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/andrewandante/submuncher/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/andrewandante/submuncher/?branch=master)
[![Build Status](https://scrutinizer-ci.com/g/andrewandante/submuncher/badges/build.png?b=master)](https://scrutinizer-ci.com/g/andrewandante/submuncher/build-status/master)

## Installation

`composer require andrewandante/submuncher`

## Using it

The most basic usage is to pass an array of IPv4 addresses into `AndrewAndante\SubMuncher\SubMuncher::consolidate()`

There are also a bunch of helper IP utility functions in the `Validator` and `Util` classes, should you need to do some tweaking.

You can also pass a second parameter that limits the number of rules returned. This invokes some magic to combine some subnets in a way that introduces the least number of additional IP addresses into the range as possible.

## Limitations

Only tested on IPv4 at present.