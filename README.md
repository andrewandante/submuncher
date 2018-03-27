# SubMuncher

Handy tool for consolidating subnets into as few subnets as possible

[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/andrewandante/submuncher/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/andrewandante/submuncher/?branch=master)
[![Build Status](https://scrutinizer-ci.com/g/andrewandante/submuncher/badges/build.png?b=master)](https://scrutinizer-ci.com/g/andrewandante/submuncher/build-status/master)

## Installation

`composer require andrewandante/submuncher`

## Using it

The most basic usage is to pass an array of IPv4 addresses into `AndrewAndante\SubMuncher\SubMuncher::consolidate()`

The most useful usage is to pass an array of CIDRs into `AndrewAndante\SubMuncher\SubMuncher::consolidate_subnets()`

There are also a bunch of helper IP utility functions in the `Util` classe should you need to do some tweaking.

You can also pass a second parameter that limits the number of rules returned. This invokes some magic to combine some subnets in a way that introduces the least number of additional IP addresses into the range as possible.

For example, to reduce your list of subnets down to 25 total rules, try `AndrewAndante\SubMuncher\SubMuncher::consolidate_subnets($cidrsArray, 25);`

## Debugging

There are verbose methods which will give you the initial list of IPs covered by your CIDRs, and the ultimate list covered. That will give you the opportunity to compare, so you can see what additional IPs have been introduced. This is in case you are using them for a whitelist or something that requires exact knowledge of the IPs.

<p style="color: red;">
This is significantly slower when calculating subnets, as it expands out all the individual IPs in the subnet, rather than just using the first and last IP
</p>

## Limitations

Only tested on IPv4 at present.