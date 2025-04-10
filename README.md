# OWST -- 1-Wire Switch Timer

OWST is an Open Source project performing as a programmable timer for
[1-Wire(R)](https://www.analog.com/en/product-category/1wire-devices.html)
switches such as [DS2408](https://www.analog.com/en/products/ds2408.html)
(8-Channel Addressable Switch) or [DS2413](https://www.analog.com/en/products/ds2413.html)
(Dual Channel Addressable Switch).


## Features

* Completely free and open source.
* Manages addressable switches in a 1-Wire network, every switch can be
  controlled by separate timer programs.
* Every switch may be in one of the three modes: constant ON, constant OFF,
  TIMER. Mode may be changed without affecting the programming of the timer.


### Timer

* Nearly unlimited time programs. Number of time programs are limited only by
  the resources of the server and/or the used database management system SQlite3.
* Time programming is based on weekly repetition.
* 7-day programming: time programs can be set to be valid for selected days of
  the week only.
* Time programs can be limited by a start and end date.
* Option to automatically delete time program after it's end date.
* Option for time program to override other active time programs when switching OFF.


### Web Interface

* Management of time programs (insert, update, delete)
* Cloning of time programs
* Interruption of time programs for a given time period (ideal for holidays etc.)
* Immediate actions such as switch-ON in given time for a given time (e.g. switch
  on in 00:30 for 02:15) or switch-OFF in given time (e.g. switch off in 01:45).
* View log
* View 'at' queue


## Technical Details

The script which actually switches on or off the 1-Wire switches may be
called periodically using cron or at the corresponding date and time using 'at'
(with automatic reprogramming of 'at').

OWS Timer Control works on a time resolution of minutes. Therefore time
programs might switch off/on up to 59 seconds to late/early. 


## Requirements

* [OWFS](https://github.com/owfs/owfs)
* A webserver with [PHP5+](https://www.php.net/)
* [Smarty Template Engine 3.x](https://www.smarty.net/)
* [SQLite3](https://www.sqlite.org/index.html)
* cron/at


## Installation

See doc/INSTALL.md for installation instructions.
