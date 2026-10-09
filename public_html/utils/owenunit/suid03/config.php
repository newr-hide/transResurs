<?php
  $config = array(
    // module name
    'name' => 'Технологические параметры',
    // sql to create data table
    'data' =>
      'CREATE TABLE IF NOT EXISTS owen_tlp(time DATETIME NOT NULL PRIMARY KEY'.
      ',wtc1 FLOAT,wtc2 FLOAT,pct3 FLOAT,cct3 FLOAT,wtpr FLOAT'.
      ',wtpl FLOAT,ctp4 FLOAT,wtcl FLOAT,fwtp FLOAT,eptp FLOAT'.
      ')',
    // sql items
    'item' => array('owen_tlp', 'time', 'wtc1', 'wtc2', 'pct3', 'cct3', 'wtpr', 'wtpl', 'ctp4', 'wtcl', 'fwtp', 'eptp'),
    // line names
    'line' => array(
      'wtc1' => array(0x000075, 'Градирня №1 -'.PHP_EOL.'температура воды', '&#176;C'),
      'wtc2' => array(0x4363D8, 'Градирня №2 -'.PHP_EOL.'температура воды', '&#176;C'),
      'pct3' => array(0xE6194B, 'ТГ-3 - давление вакуума'.PHP_EOL.'конденсатора', 'кПа'),
      'cct3' => array(0xF58231, 'ТГ-3 - расход конденсата', 'м&#179;'),
      'wtpr' => array(0x911EB4, 'Температура цирк'.PHP_EOL.'воды в слив.'.PHP_EOL.'труб. справа', '&#176;C'),
      'wtpl' => array(0x42D4F4, 'Температура цирк'.PHP_EOL.'воды в слив.'.PHP_EOL.'труб. слева', '&#176;C'),
      'ctp4' => array(0x469990, 'Температура'.PHP_EOL.'конденсата за ПНД-4', '&#176;C'),
      'wtcl' => array(0x3CB44B, 'Температура воды'.PHP_EOL.'в конденсаторе слева', '&#176;C'),
      'fwtp' => array(0x800000, 'Температура'.PHP_EOL.'подпиточной воды', '&#176;C'),
      'eptp' => array(0x9A6324, 'Температура выхлопного'.PHP_EOL.'патрубка ЦНД', '&#176;C')
    ),
    // sensor data to test and show warning message about
    // this list may not be the same as one above because of some calculated values in database
    'test' => array(
      'wtc1' => 'температура воды градирня №1',
      'wtc2' => 'температура воды градирня №2',
      'pct3' => 'давление вакуума конденсатора ТГ-3',
      'cct3' => 'расход конденсата ТГ-3',
      'wtpr' => 'температура цирк воды в сливном трубопроводе справа',
      'wtpl' => 'температура цирк воды в сливном трубопроводе слева',
      'ctp4' => 'температура конденсата за ПНД-4',
      'wtcl' => 'температура воды в конденсаторе слева',
      'fwtp' => 'температура подпиточной воды',
      'eptp' => 'температура выхлопного патрубка ЦНД'
    )
  );
