<?php
  // database create SQL query in case table doesn't already exists
  $tablesql = array(
    // users table
    'CREATE TABLE IF NOT EXISTS owen_usr(id INT UNSIGNED NOT NULL PRIMARY KEY AUTO_INCREMENT,suid INT UNSIGNED,`time` DATETIME,auth VARCHAR(128),name VARCHAR(128))',
    // services off table
    'CREATE TABLE IF NOT EXISTS owen_off(suid INT UNSIGNED NOT NULL, `from` DATETIME NOT NULL, till DATETIME, PRIMARY KEY(suid, `from`))',
    // user configuration (preferred settings for suid pages)
    'CREATE TABLE IF NOT EXISTS owen_cfg(`user` INT UNSIGNED NOT NULL, suid INT UNSIGNED NOT NULL, data VARCHAR(255), PRIMARY KEY(`user`, suid))'
  );

  // require main module
  require('owenmain.php');
  // for $owen_pwd password hash and $owen_hdr title
  require('oweninfo.php');

  // test for authorized user
  $v = val_post('pass', '');
  define('OWEN_ADMIN', (!strcasecmp(sha1($v), $owen_pwd)) ? $v : null);
  // connect to MySQL
  if (!sql_open()) {
    die('ERROR: MYSQL INITIALIZATION FAILED');
  }
  // available services list
  $services = get_main_suid_list(0);
  // authorized users only
  if (OWEN_ADMIN) {
    // jQuery data
    if (!strcasecmp(val_key($_SERVER, 'HTTP_X_REQUESTED_WITH', ''), 'XMLHttpRequest')) {
      // done - default response code
      $v = 'D0N3';
      $d = val_post('data');
      // code action to take
      switch (val_post('code')) {
        case 'listuser':
          $l = array();
          // table exists
          if (sql_test('owen_usr')) {
            // generate html code list of available users
            $list = sql_exec('SELECT id,name FROM owen_usr ORDER BY name');
            for ($i = 0; $i < count($list); $i++) {
              extract($list[$i]);
              $l[] = sprintf(
                '<input type="radio" name="user" value="%u" id="user%u"><label for="user%u">%s</label>',
                $id, $id, $id, html_escape($name)
              );
            }
          }
          $v .= '-'.implode('<br>'.PHP_EOL, $l);
          break;
        case 'makeuser':
          // table exists
          if (sql_test('owen_usr')) {
            // create new user
            sql_exec(
              'INSERT INTO owen_usr VALUES(NULL,0,NULL,NULL,\'%s\')',
              sql_text(tocp1251($d))
            );
            $v .= '+';
          } else {
            $v = 'ERROR: TABLE NOT EXISTS';
          }
          break;
        case 'authuser':
          // generate new auth code for the user
          $d = intval($d);
          $t = 'u'.$d.'a'.newtoken();
          // +30 days
          sql_exec(
            'UPDATE owen_usr SET auth=\'%s\',`time`=NOW() + INTERVAL 30 DAY WHERE id=%u',
            sql_text($t), $d
          );
          $v .= '-'.$t;
          break;
        case 'dropuser':
          // delete user
          $d = intval($d);
          sql_exec(
            'DELETE FROM owen_usr WHERE id=%u',
            $d
          );
          // delete settings
          sql_exec(
            'DELETE FROM owen_cfg WHERE `user`=%u',
            $d
          );
          $v .= '+';
          break;
        case 'usersuid':
          // get user allowed suid mask
          $l = sql_exec(
            'SELECT suid FROM owen_usr WHERE id=%u LIMIT 1',
            intval($d)
          );
          $l = array_pop($l);
          $l = array_pop($l);
          $v .= '-'.strval(intval($l));
          break;
        case 'usersave':
          // apply access list mask for the user
          sql_exec(
            'UPDATE owen_usr SET suid=%u WHERE id=%u',
            intval(val_post('list')), intval($d)
          );
          $v .= '+';
          break;
        case 'listsuid':
          // get current services state
          $d = 0;
          // build state mask
          foreach ($services as $k => $l) {
            $k = intval($k);
            if (($k >= 1) && ($k <= 32)) {
              $d |= (1 << ($k - 1));
            }
          }
          // table exists
          if (sql_test('owen_off')) {
            $l = sql_exec('SELECT suid FROM owen_off WHERE till IS NULL');
            for ($i = 0; $i < count($l); $i++) {
              $k = intval($l[$i]['suid']);
              // drop active state mask
              if (($k >= 1) && ($k <= 32)) {
                $d ^= (1 << ($k - 1));
              }
            }
          }
          $v .= '-'.$d;
          break;
        case 'savesuid':
          // table exists
          if (sql_test('owen_off')) {
            // save services state
            $d = intval($d);
            foreach ($services as $k => $l) {
              $k = intval($k);
              if (($k >= 1) && ($k <= 32)) {
                if ($d & (1 << ($k - 1))) {
                  // start service
                  sql_exec(
                    'UPDATE owen_off SET till=\'%s\' WHERE (suid=%u) AND (till IS NULL)',
                    sql_text(date('Y-m-d H:i:s')), $k
                  );
                } else {
                  // stop service
                  $l = sql_exec(
                    'SELECT suid FROM owen_off WHERE (suid=%u) AND (till IS NULL) LIMIT 1',
                    $k
                  );
                  // check that service not stopped already
                  if (empty($l)) {
                    // if so - stop now
                    sql_exec(
                      'INSERT INTO owen_off VALUES(%u,\'%s\',NULL)',
                      $k, sql_text(date('Y-m-d H:i:s'))
                    );
                  }
                }
              }
            }
            $v .= '+';
          } else {
            $v = 'ERROR: TABLE NOT EXISTS';
          }
          break;
        case 'makedata':
          // create tables
          $list = get_main_suid_list(2);
          // merge with other tables
          $list = array_merge($tablesql, $list);
          for ($i = 0; $i < count($list); $i++) {
            sql_exec($list[$i]);
          }
          $v .= '+';
          break;
        default:
          $v = 'ERROR: INVALID CODE';
      }
      header('Content-Type: text/html; charset=windows-1251');
      die($v);
    }
    // server info
    $info = array(
      'web' => val_key($_SERVER, 'SERVER_SOFTWARE', 'N/A'),
      'sql' => function_exists('mysql_get_server_info') ? call_user_func('mysql_get_server_info') : 'N/A',
      'php' => defined('PHP_VERSION') ? constant('PHP_VERSION') : 'N/A',
      'gdl' => val_key(function_exists('gd_info') ? call_user_func('gd_info') : array(), 'GD Version', 'N/A')
    );
    // leave version only and place full info in hint text
    foreach ($info as $k => $v) {
      $info[$k] =
        '<span title="'.html_escape($v).'" class="d">'.
        html_escape(preg_replace('/^.*?([0-9.]+).*?$/', '$1', $v)).
        '</span>';
    }
  }
  // set correct code page
  header('Content-Type: text/html; charset=windows-1251');
?>
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01//EN">
<html>
<head>
<meta http-equiv="imagetoolbar" content="no">
<meta http-equiv="Content-Type" content="text/html; charset=windows-1251">
<title>Панель администратора сервисов <?php echo html_escape($owen_hdr); ?></title>

<script type="text/javascript" src="owenmisc/jquery-1.8.2.min.js"></script>

<style type="text/css">
<!--

h1, h2 {
  text-align: center;
  margin: 0;
}

h1 {
  font-size: 18pt;
}

h2 {
  font-size: 16pt;
  margin: 36pt 0 18pt 0;
}

a, a:link, a:visited {
  color: #0000ff;
}

a:active, a:hover {
  color: #ff0000;
}

body {
  overflow: auto;
}

table {
  border: 0;
  border-collapse: collapse;
  margin: auto;
}

td, th {
  border: 0;
  padding: 0 20pt 0 20pt;
  white-space: nowrap;
}

th {
  font-weight: normal;
}

.t span, label {
  position: relative;
  top: -3pt;
  padding-left: 3pt;
}

.d {
  cursor: help;
  border-bottom: 1px dotted #000000;
}

input[type="radio"],
input[type="checkbox"],
input[type="button"],
label {
  cursor: pointer;
}

.ui-datepicker * {
  font-size: 10pt;
}

noscript {
  display: block;
  text-align: center;
  font-weight: bold;
  border: 1px solid #800000;
  background-color: #e0c0c0;
  color: #800000;
  padding: 4pt;
}

-->
</style>
</head>
<body>

<h1>Панель администратора сервисов <?php echo html_escape($owen_hdr); ?></h1>

<?php if (!OWEN_ADMIN) { ?>

<h2>Вход в панель администратора</h2>
<form method="post">
<table class="t">
<tr><td>
  Пароль
  <input type="password" name="pass" value="">
  <input type="submit" value="Войти">
</td></tr>
</table>
</form>

<script type="text/javascript">
$(function() {
  $('input[name="pass"]:first').focus();
});
</script>

<?php } else { ?>

<noscript>Для работы необходим JavaScript!</noscript>

<h2>Управление пользователями</h2>
<table class="t"><tr>
  <td>
    <input type="hidden" name="pass" value="<?php echo html_escape(OWEN_ADMIN); ?>">
    <fieldset>
    <legend>Пользователи</legend>
    <div id="listuser"></div>
    </fieldset>
    <input type="button" name="makeuser" value="Добавить">
    <input type="button" name="authuser" value="Новый ключ">
    <input type="button" name="dropuser" value="Удалить">
  </td>
  <td>
    <fieldset>
    <legend>Доступ для пользователя <b id="nameuser">&nbsp;</b></legend>
<?php
  foreach ($services as $k => $v) {
    $k = html_escape($k);
    $v = html_escape($v);
?>
      <input type="checkbox" name="list<?php echo $k; ?>" id="list<?php echo $k; ?>"><label for="list<?php echo $k; ?>"><?php echo $v; ?></label><br>
<?php } ?>
    </fieldset>
    <input type="button" name="usersave" value="Изменить доступ"><br>
    Сервисы отмеченные <b>[v]</b> будут доступны пользователю.
  </td>
</tr></table>

<h2>Управление сервисами</h2>
<table class="t"><tr><td>
  <fieldset>
  <legend>Доступные сервисы</legend>
<?php
  foreach ($services as $k => $v) {
    $k = html_escape($k);
    $v = html_escape($v);
?>
    <input type="checkbox" name="suid<?php echo $k; ?>" id="suid<?php echo $k; ?>"><label for="suid<?php echo $k; ?>"><?php echo $v; ?></label><br>
<?php } ?>
  </fieldset>
  <input type="button" name="savesuid" value="Сохранить изменения"><br>
  Сервисы отмеченные <b>[v]</b> работают, в противном случае они <b>остановлены</b>.
</td></tr></table>

<h2>Управление базой данных</h2>
<table class="t"><tr><td>
  <input type="button" name="makedata" value="Создать таблицы в базе"><br>
  Создаёт таблицы в базе данных при первом использовании, установке или неполадках сервиса.<br>
  Уже существующие таблицы и содержащиеся в них данные <b>никак не затрагиваются</b>.<br><br>
</td></tr></table>

<h2>Конфигурация сервера</h2>
<table class="t">
<tr><th>Компонент</th><th>Версия</th><th>Требуется</th></tr>
<tr><th>Server</th><td><?php echo $info['web']; ?></td><th>---</th></tr>
<tr><th>MySQL</th><td><?php echo $info['sql']; ?></td><td>4.1 или выше</td></tr>
<tr><th>PHP</th><td><?php echo $info['php']; ?></td><td>5.2 или выше</td></tr>
<tr><th>GD</th><td><?php echo $info['gdl']; ?></td><td>2.0 или выше</td></tr>
</table>

<script type="text/javascript">
function mask_build(name) {
  var v = 0;
  var l = name.length;
  $('input[name^="' + name + '"]:checked').each(function(){
    var i = $(this).attr('name');
    i = i.substr(l, i.length - l);
    i = Math.abs(parseInt(i, 10) - 1);
    v |= Math.pow(2, i);
  });
  return(v);
}

function mask_apply(name, v) {
  v = Math.abs(parseInt(v, 10));
  var i = 0;
  var k = 1;
  for (i = 1; i != 33; i++) {
    /* awkward code to avoid "amp", "gt" and "lt" html characters */
    if (!((v | k) ^ v)) {
      $('input[name=' + name + i.toString() + ']').attr('checked', 'checked');
    }
    k *= 2;
  }
}

function post_data(data) {
  data = data ? $('input[name="pass"]').val() : '<?php echo html_escape(basename(__FILE__)); ?>';
  return(data);
}

function test_response(data, status) {
  if (status != 'success') {
    alert('POST ERROR: ' + status);
    data = null;
  } else {
    if (data.substr(0, 4) != 'D0N3') {
      alert(data);
      data = null;
    } else {
      data = data.substr(4, data.length - 4);
      if (data.substr(0, 1) == '+') { alert('Готово'); }
      data = data.substr(1, data.length - 1);
    }
  }
  return(data);
}

function list_disabled(state) {
  $('input[name="authuser"],input[name="dropuser"],input[name="usersave"]').prop('disabled', state ? true : false);
  $('input[name^="list"]').attr('checked', false).prop('disabled', state ? true : false);
}

function user_update() {
  list_disabled(true);
  $.post(post_data(0),
    {
      pass: post_data(1),
      code: 'listuser'
    },
    function(data, status) {
      data = test_response(data, status);
      if (data !== null) {
        $('#listuser').html(data);
        $('input[name="user"]:checked').each(function(){ list_disabled(false); });
        $('input[name="user"]').click(function(){
          $('#nameuser').text($('label[for="' + $(this).attr('id') + '"]').text());
          $('input[name^="list"]').attr('checked', false);
          $.post(post_data(0),
            {
              pass: post_data(1),
              code: 'usersuid',
              data: $(this).val()
            },
            function(data, status) {
              data = test_response(data, status);
              if (data !== null) {
                list_disabled(false);
                mask_apply('list', data);
              }
            }
          );
        });
      }
    }
  );
}

function suid_update() {
  $('input[name^="suid"]').attr('checked', false).prop('disabled', true);
  $('input[name="savesuid"]').prop('disabled', true);
  $.post(post_data(0),
    {
      pass: post_data(1),
      code: 'listsuid'
    },
    function(data, status) {
      data = test_response(data, status);
      if (data !== null) {
        $('input[name="savesuid"]').prop('disabled', false);
        $('input[name^="suid"]').prop('disabled', false);
        mask_apply('suid', data);
      }
    }
  );
}

$(function() {
  user_update();
  $('input[name="authuser"],input[name="dropuser"]').prop('disabled', true);
  $('input[name="usersave"]').click(function(){
    $.post(post_data(0),
      {
        pass: post_data(1),
        code: $(this).attr('name'),
        data: $('input[name="user"]:checked').val(),
        list: mask_build('list')
      },
      test_response
    );
  });
  $('input[name$="user"]').click(function(){
    var c = $(this).attr('name');
    var v = null;
    if (c == 'makeuser') {
      v = prompt('Введите имя нового пользователя', 'Пользователь');
      v = (v == '') ? null : v;
    } else {
      if (confirm(
        (c == 'authuser') ?
          'Создать новый ключ пользователю?' :
            'Удалить выбранного пользователя?'
      )) {
        v = $('input[name="user"]:checked').val();
      }
    }
    if (v !== null) {
      $.post(post_data(0),
        {
          pass: post_data(1),
          code: c,
          data: v
        },
        function(data, status) {
          data = test_response(data, status);
          if (data !== null) {
            if (c == 'authuser') {
              prompt(
                'Создан новый ключ доступа.\n'+
                'Скопируйте и отправьте ссылку ниже.\n'+
                'Все старые ссылки перестали работать!',
                '<?php echo html_escape(dirname(uri_self(null))); ?>/owenview.php?auth='+ data
              );
            } else {
              user_update();
            }
          }
        }
      );
    }
  });
  suid_update();
  $('input[name="savesuid"]').click(function(){
    $.post(post_data(0),
      {
        pass: post_data(1),
        code: $(this).attr('name'),
        data: mask_build('suid')
      },
      function(data, status) {
        data = test_response(data, status);
        if (data === null) {
          suid_update();
        }
      }
    );
  });
  $('input[name$="data"]').click(function(){
    $.post(post_data(0),
      {
        pass: post_data(1),
        code: $(this).attr('name')
      },
      test_response
    );
  });
});
</script>

<?php } ?>

</body>
</html>
