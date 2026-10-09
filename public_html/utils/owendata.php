<?php
  // user can't abort this script
  ignore_user_abort(true);
  // no time limit for this script
  set_time_limit(0);
  // require main module
  require('owenmain.php');
  // check auth
  $auth = strval(val_post('auth'));
  if (strcmp($auth, $auth_key)) {
    die('ERROR: AUTHENTICATION FAILED');
  }
  // service unique identifier
  $suid = intval(val_post('suid'));
  // get services list
  $suid_list = get_main_suid_list(0);
  // known or allowed service
  if (!array_key_exists($suid, $suid_list)) {
    die('ERROR: INVALID SUID');
  }
  // get data
  $csum = strval(val_post('csum'));
  $data = strval(val_post('data'));
  // check data integrity
  if (strcasecmp(md5($data), $csum)) {
    die('ERROR: INVALID DATA CHECKSUM');
  }
  // prepare data
  $data = @unserialize($data);
  if (($data === false) || (!is_array($data))) {
    die('ERROR: INVALID DATA FORMAT');
  }
  // connect to MySQL
  if (!sql_open()) {
    die('ERROR: MYSQL INITIALIZATION FAILED');
  }
  // prepare list
  $list = get_data($suid, 'item');
  $list = array_map('trim', $list);
  // shift out table name and add it to query
  $v = array_shift($list);
  $query = 'INSERT IGNORE INTO '.$v.' ('.implode(',', $list).') VALUES';
  // check that all required tables are existed
  $stop = sql_exec('SHOW TABLES');
  $stop = array_map('end_list', $stop);
  $stop = array_diff(array('owen_off', $v), $stop);
  if (!empty($stop)) {
    die('ERROR: MISSING REQUIRED MYSQL TABLES: '.implode(',', $stop));
  }
  // get stop intervals
  $stop = sql_exec(
    'SELECT UNIX_TIMESTAMP(`from`) AS `from`,'.
    'IF(till IS NULL,NULL,UNIX_TIMESTAMP(till)) '.
    'AS till FROM owen_off WHERE suid=%u ORDER BY `from`',
    $suid
  );
  // create values array
  $vals = array();
  foreach ($list as $v) {
    $vals[$v] = '';
  }
  // insert new data into the database
  foreach ($data as $item) {
    // check that all required fields exists
    $v = array_diff($list, array_keys($item));
    if (!empty($v)) {
      die('ERROR: REQUIRED KEYS NOT FOUND ['.implode(',', $v).']');
    }
    // check against stop time list
    $v = $item['time'];
    for ($i = 0; $i < count($stop); $i++) {
      // got into stop interval
      if (($stop[$i]['from'] <= $v) && (is_null($stop[$i]['till']) || ($v <= $stop[$i]['till']))) {
        // skip this row
        $v = null;
        break;
      }
    }
    // row not skipped
    if (!empty($v)) {
      // integer to time
      $item['time'] = date('Y-m-d H:i:s', $v);
      // generate values query string
      foreach ($list as $v) {
        // handle null values
        $vals[$v] = is_null($item[$v]) ? 'NULL' : '\''.sql_text(strval($item[$v])).'\'';
      }
      // execute MySQL query
      sql_exec($query.'('.implode(',', $vals).')');
    }
  }
  // done
  echo 'DATARECV_COMPLETED';
