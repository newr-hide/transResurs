<?php
  $config = array(
    // module name
    'name' => 'Параметры ТГ-1',
    // sql to create data table
    'data' =>
      'CREATE TABLE IF NOT EXISTS owen_tg1(time DATETIME NOT NULL PRIMARY KEY'.
      ',pct1 FLOAT'.
      ')',
    // sql items
    'item' => array('owen_tg1', 'time', 'pct1'),
    // line names
    'line' => array(
      'pct1' => array(0xE6194B, 'ТГ-1 - давление вакуума'.PHP_EOL.'конденсатора', 'кПа')
    ),
    // sensor data to test and show warning message about
    // this list may not be the same as one above because of some calculated values in database
    'test' => array(
      'pct1' => 'давление вакуума конденсатора ТГ-1'
    )
  );
