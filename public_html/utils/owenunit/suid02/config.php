<?php
  $config = array(
    // module name
    'name' => 'Температура воды',
    // sql to create data table
    'data' =>
      'CREATE TABLE IF NOT EXISTS owen_wtr(time DATETIME NOT NULL PRIMARY KEY'.
      ',tmpf FLOAT,tmp1 FLOAT,tmp2 FLOAT,tmpw FLOAT,tmpe FLOAT,tmph FLOAT'.
      ')',
    // sql items
    'item' => array('owen_wtr', 'time', 'tmpf', 'tmp1', 'tmp2', 'tmpw', 'tmpe', 'tmph'),
    // line names
    'line' => array(
      'tmpf' => array(0xFF0000, 'Подача', '&#176;C'),
      'tmp1' => array(0x8A2BE2, 'Обратный'.PHP_EOL.'трубопровод'.PHP_EOL.'городского'.PHP_EOL.'вывода № 1', '&#176;C'),
      'tmp2' => array(0x0000CD, 'Обратный'.PHP_EOL.'трубопровод'.PHP_EOL.'городского'.PHP_EOL.'вывода № 2', '&#176;C'),
      'tmpw' => array(0x228B22, 'Обратный'.PHP_EOL.'трубопровод'.PHP_EOL.'"Запад"', '&#176;C'),
      'tmpe' => array(0x00FFFF, 'Обратный'.PHP_EOL.'трубопровод'.PHP_EOL.'"Восток"', '&#176;C'),
      'tmph' => array(0xCD853F, 'Обратный'.PHP_EOL.'трубопровод'.PHP_EOL.'"Теплицы"', '&#176;C')
    ),
    // sensor data to test and show warning message about
    // this list may not be the same as one above because of some calculated values in database
    'test' => array(
      'tmpf' => 'подача',
      'tmp1' => 'обратный трубопровод городского вывода № 1',
      'tmp2' => 'обратный трубопровод городского вывода № 2',
      'tmpw' => 'обратный трубопровод "Запад"',
      'tmpe' => 'обратный трубопровод "Восток"',
      'tmph' => 'обратный трубопровод "Теплицы"'
    )
  );
