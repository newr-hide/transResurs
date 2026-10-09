<?php
  $config = array(
    // module name
    'name' => 'Температура наружного воздуха',
    // sql to create data table
    'data' =>
      'CREATE TABLE IF NOT EXISTS owen_air(time DATETIME NOT NULL PRIMARY KEY'.
      ',tmpo FLOAT'.
      ')',
    // sql items
    'item' => array('owen_air', 'time', 'tmpo'),
    // line names
    'line' => array(
      'tmpo' => array(0x0000FF, 'Температура'.PHP_EOL.'воздуха', '&#176;C')
    ),
    // sensor data to test and show warning message about
    // this list may not be the same as one above because of some calculated values in database
    'test' => array(
      'tmpo' => 'температура воздуха'
    )
  );
