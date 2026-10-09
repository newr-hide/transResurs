<?php
  // configuration file

  // debugger mode
  if (file_exists(substr(__FILE__, 0, -strlen(basename(__FILE__))).'debugger')) {
    if (!strcmp(
      trim(strval(@file_get_contents(substr(__FILE__, 0, -strlen(basename(__FILE__))).'debugger'))),
      array_key_exists('REMOTE_ADDR', $_SERVER) ? $_SERVER['REMOTE_ADDR'] : ''
    )) {
      // show all errors
      error_reporting(-1);
      ini_set('display_errors', 1);
    }
  }

  // memory limitation
  ini_set('memory_limit', '256M');

  // first of all set default timezone
  ini_set('date.timezone', 'Etc/GMT-7'); // Asia/Novosibirsk

  // sha1 password hash to login into administrator page (http://onlinemd5.com/)
  $owen_pwd = '6d02a4ec5d8b4f321123985081380c607459ffcc';

  // authentication key for datasend/owendata
  $auth_key = '7oYl6I0yrbWt';

  // company name header
  $owen_hdr = '���'.chr(32).chr(171).'��������'.chr(187);

  // database credintals
  if (isset($sql_info) && is_array($sql_info) && empty($sql_info)) {
    $sql_info = array(
      // database server
      'host' => 'localhost',
      // database name
      'base' => 'cd67399_weather',
      // database username
      'user' => 'cd67399_weather',
      // database password
      'pass' => 'a20vzIQO',
      // database table prefix (FIXME: unused?)
      'pref' => 'owen',
      // commands to execute after connection
      'list' => array(
        // set correct time zone
        'SET time_zone = \'+7:00\'',
        // set code page
        'SET NAMES CP1251'
      )
    );
  }
