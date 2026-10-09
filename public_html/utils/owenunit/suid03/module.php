<?php
  require 'config.php';

// init graphics
function plt_init($work) {
  // create stubs just in case
  $view = array();
  $info = array();
  $data = array();
  // create initial variables
  extract($work);
  // load configuration
  require 'config.php';
  // =====================
  // rows info
  $info = array_merge($info, $config['line']);
  // get required plots mask
  $list = array_keys($config['line']);
  $v = val_gint('data', (1 << count($list)));
  // nothing - all
  if (!$v) { $v = ((1 << count($list)) - 1); }
  // muli-view
  $view['mi'] = 0;
  $view['xp'] = 70; // 40
  $view['x'] = 1020; // 1080
  $view = array($view);
  $ikey = null;
  // selected columns
  for ($i = 0; $i < count($list); $i++) {
    // this plot exists
    if ($v & (1 << $i)) {
      // same axes case
      if (($i != 2) && ($i != 3)) {
        // group not exists
        if (is_null($ikey)) {
          $ikey = count($data);
        } else {
          // group key exists
          $data[$ikey][] = $list[$i];
          continue;
        }
      }
      $data[] = array($list[$i]);
      $view[0]['mi']++;
      $view[$view[0]['mi']] = $view[0];
    }
  }
  // drop default values
  array_shift($view);
  // table name
  $t = $config['item'][0];
  // =====================
  foreach ($work as $k => $v) { $work[$k] = $$k; }
  return($work);
}

function htm_data($work) {
  // create initial variables
  extract($work);
  // load configuration
  require 'config.php';
  ob_start();
  // =====================
?>
    <table class="t"><tr><td>
    <?php echo html_escape($config['name']); ?><br>
<?php
  $i = 0;
  foreach ($config['line'] as $key => $value) {
    $i++;
    echo
      '    <input type="checkbox" name="data_'.$i.'" id="data'.$i.'">'.
      '<label for="data'.$i.'">'.preg_replace('/['.PHP_EOL.']+/s', ' ', $value[1]).'</label><br>'.PHP_EOL;
  }
?>
    </td></tr></table>
<?php
  // =====================
  $work = ob_get_contents();
  ob_end_clean();
  return($work);
}

function htm_more($work) {
  // create initial variables
  extract($work);
  ob_start();
  // =====================
  // only days
  $from = substr($from, 0, 10);
  $till = substr($till, 0, 10);
?>
      <br><br>
      <fieldset>
      <legend>Данные в табличном виде</legend>
        Начало: <input type="text" name="fromload" value="<?php echo $from; ?>" size="10" maxlength="10" readonly>
        Конец: <input type="text" name="tillload" value="<?php echo $till; ?>" size="10" maxlength="10" readonly>
        Тип: <select name="typeload">
          <option value="1">Все значения</option>
          <option value="2">Среднечасовые</option>
          <option value="3">Среднесуточные</option>
        </select>
        <input type="checkbox" name="fileload" id="file0"><label for="file0">Файл</label>
        <span>| <a
          href="<?php echo html_escape($_SERVER['REQUEST_URI'].'&out=1&fmt=0&from='.$from.'&till='.$till); ?>"
          target="_blank" id="load">Сформировать</a></span>
      </fieldset>
<?php
  // =====================
  $work = ob_get_contents();
  ob_end_clean();
  return($work);
}

function jsc_data($work) {
  // create initial variables
  extract($work);
  ob_start();
  // =====================
?>
      mask_build('data_')
<?php
  // =====================
  $work = ob_get_contents();
  ob_end_clean();
  return($work);
}

function jsc_init($work) {
  // create initial variables
  extract($work);
  ob_start();
?>
  $('*[name$="load"]').change(function() {
    var d = $('#load').attr('href');
    d = d.replace(/fmt=[0-9]+/, 'fmt=' + ($('input[name="fileload"]').attr('checked') ? '1' : '0'));
    d = d.replace(/out=[0-9]+/, 'out=' + $('select[name="typeload"]').val());
    d = d.replace(/from=[0-9\-]+/, 'from=' + $('input[name="fromload"]').val().trim());
    d = d.replace(/till=[0-9\-]+/, 'till=' + $('input[name="tillload"]').val().trim());
    $('#load').attr('href', d);
  }).change();
  $('input[name$="load"][type="text"]').datetimepicker({
    currentText: 'Сейчас',
    closeText: 'Закрыть',
    monthNames: ['Январь', 'Февраль', 'Март', 'Апрель', 'Май', 'Июнь', 'Июль', 'Август', 'Сентябрь', 'Октябрь', 'Ноябрь', 'Декабрь'],
    dayNamesMin: ['Вс', 'Пн', 'Вт', 'Ср', 'Чт', 'Пт', 'Сб'],
    dateFormat: 'yy-mm-dd',
    timeFormat: '',
    showTime: false,
    showHour: false,
    showMinute: false,
    showSecond: false,
    showMillisec: false,
    showTimezone: false,
    firstDay: 1,
    minDate: '<?php echo $tmin; ?>',
    maxDate: '<?php echo $tmax; ?>'
  });
  $('input[name^="data_"]').attr('checked', 'checked');
  $('input[name^="data_"]').click(function() {
    $('input[type="button"]:first').click();
  });
<?php if (!is_null($data)) {?>
  var i = 0;
  var m = 1;
  for (i = 1; i != 33; i++) {
    if (!(<?php echo $data; ?> & m)) {
      $('input[name="data_' + i + '"]').removeAttr('checked');
    }
    m *= 2;
  }
<?php } ?>
<?php
  // =====================
  $work = ob_get_contents();
  ob_end_clean();
  return($work);
}

function out_data($work) {
  // create initial variables
  extract($work);
  // load configuration
  require 'config.php';
  // some variables
  $crlf = chr(13).chr(10);
  $page =
    '<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01//EN">'.$crlf.
    '<html><head><meta http-equiv="Content-Type" content="text/html; charset=windows-1251">'.$crlf.
    '<title><!--name--></title><style type="text/css"><!--'.$crlf.
    'body { border: 0; margin: 0; padding: 0; overflow: auto; }'.$crlf.
    'table { border: 0; border-collapse: collapse; margin: auto; }'.$crlf.
    'th,td { border: 1px solid #000000; padding: 4px; }'.$crlf.
    '--></style>'.$crlf.
    '</head><body><!--head--><table>'.$crlf.
    '<!--body-->'.
    '</table><!--code--></body></html>'.$crlf;
  // check if we request something
  $out = intval(val_get('out'));
  if (($out >= 1) && ($out <= 3)) {
    $out--;
    // output format type
    $fmt = val_gint('fmt', 2);
    // from and till dates
    $from = strtotime(val_get('from').' 00:00:00');
    $till = strtotime(val_get('till').' 00:00:00') - 1;
    // build columns list
    $item = array('time' => array(0, 'Дата и время', ''));
    $item = array_merge($item, $config['line']);
    $data = array(
       array('', '', 'a'),
       array('AVG', 'GROUP BY HOUR(time)', 'h'),
       array('AVG', 'GROUP BY DAY(time)', 'd')
    );
    $list = array();
    foreach ($item as $k => $v) {
      $item[$k] = str_replace(PHP_EOL, ' ', $v[1]);
      if (empty($list)) {
        // time
        $list[] = $k;
      } else {
        // data
        $list[] = sprintf(
          'REPLACE(REPLACE(FORMAT(%s(%s), 10), \',\', \'\'), \'.\', \',\') AS %s',
          $data[$out][0], $k, $k
        );
      }
    }
    // get data
    $list = sql_exec(
      'SELECT %s FROM %s WHERE time BETWEEN FROM_UNIXTIME(%u) AND FROM_UNIXTIME(%u) %s ORDER BY time',
      implode(',', $list),
      $config['item'][0],
      $from, $till,
      $data[$out][1]
    );
    $name = date('Ymd', $from).'-'.date('Ymd', $till).'-'.$data[$out][2];
    if (empty($list)) {
      $list = 'NOTHING FOR INTERVAL '.$name;
    } else {
      // got something
      for ($i = 0; $i < count($list); $i++) {
        if (!$fmt) {
          // page
          $list[$i] = '<tr><td>'.implode('</td><td>', $list[$i]).'</td></tr>';
        } else {
          // file
          $list[$i] = implode(';', $list[$i]);
        }
        // connection lost or user aborted
        if (connection_aborted() || connection_status()) {
          $list = array();
          break;
        }
      }
      $list = implode($crlf, $list).$crlf;
      // page of file
      if (!$fmt) {
        // page
        header('Content-Type: text/html; charset=windows-1251');
        $page = str_replace('<!--name-->', $name, $page);
        $list = str_replace('<!--body-->', '<tr><th>'.implode('</th><th>', $item).'</th></tr>'.$crlf.$list, $page);
      } else {
        // file
        header('Content-Type: application/octet-stream');
        header('Content-Transfer-Encoding: binary');
        header('Content-Disposition: attachment; filename="'.$name.'.csv"');
        $list = implode(';', $item).$crlf.$list;
      }
    }
    die($list);
  }
  // return nothing
  return('');
}
