<?php
error_reporting(7);
function d()
{
// 	echo	'CLASS:'.__CLASS__ .'<br />';
// 	echo	'DIR::'. __DIR__ .'<br />';
	echo	'FILE:'. __FILE__ .' LINE:'. __LINE__ .'<br />';
// 	echo	'METHOD:'. __METHOD__ .'<br />';
// 	echo	'NAMESPACE:'. __NAMESPACE__ .'<br />';
	
}

define(d, __FILE__ .' '. __LINE__);
if (isset($HTTP_GET_VARS['explain']) OR isset($HTTP_POST_VARS['explain'])) {
	
  $showqueries = 1;
  $explain = 1;
}
echo d;
if (isset($HTTP_GET_VARS['showqueries']) OR isset($HTTP_POST_VARS['showqueries']) or isset($showqueries)) {
  $showqueries = 1;
  $pagestarttime = microtime();
} else {
  $pagestarttime = 0;
}

$incp=1;

// allow script to work with registerglobals off
if ( function_exists('ini_get') ) {
	$onoff = ini_get('register_globals');
} else {
	$onoff = get_cfg_var('register_globals');
}
if ($onoff != 1) {
	@extract($HTTP_SERVER_VARS, EXTR_SKIP);
	@extract($HTTP_COOKIE_VARS, EXTR_SKIP);
	@extract($HTTP_POST_FILES, EXTR_SKIP);
	@extract($HTTP_POST_VARS, EXTR_SKIP);
	@extract($HTTP_GET_VARS, EXTR_SKIP);
	@extract($HTTP_ENV_VARS, EXTR_SKIP);
}

// get rid of slashes in get / post / cookie data
function stripslashesarray (&$arr) {
  while (list($key,$val)=each($arr)) {
    if ((strtoupper($key)!=$key or "".intval($key)=="$key") and $key!="templatesused" and $key!="argc" and $key!="argv") {
			if (is_string($val)) {
				$arr[$key]=stripslashes($val);
			}
			if (is_array($val)) {
				$arr[$key]=stripslashesarray($val);
			}
	  }
  }
  return $arr;
}
if (get_magic_quotes_gpc() and is_array($GLOBALS)) {
	echo '__LINE__' . '__FUNCTION__';
  // variables created from attachments aren't escaped properly it seems...
  if (isset($attachment)) $GLOBALS['attachment'] = addslashes($GLOBALS['attachment']);
  if (isset($avatarfile)) $GLOBALS['avatarfile'] = addslashes($GLOBALS['avatarfile']);
  if (isset($iconfile)) $GLOBALS['iconfile'] = addslashes($GLOBALS['iconfile']);
  if (isset($smiliefile)) $GLOBALS['smiliefile'] = addslashes($GLOBALS['smiliefile']);
  if (isset($stylefile)) $GLOBALS['stylefile'] = addslashes($GLOBALS['stylefile']);

  $GLOBALS = stripslashesarray($GLOBALS);
}
set_magic_quotes_runtime(0);

// version numbers:
$codeversionnumber="2.3.11";
$codeinfo="";

// initialise variables
unset($forumcache);
unset($threadcache);
unset($postcache);
unset($urlSearchArray);
unset($urlReplaceArray);
unset($emailSearchArray);
unset($emailReplaceArray);
unset($iforumcache);
unset($ipermcache);
unset($iaccesscache);
unset($usergroupdef);
unset($noperms);
unset($usergroupcache);
unset($vars);
unset($usercache);
unset($forumarraycache);
unset($permscache);
unset($foruminfo);

if ($HTTP_GET_VARS['HTTP_POST_VARS']['action'] == $HTTP_POST_VARS['action']) {
  unset($HTTP_POST_VARS['action']);
}
$HTTP_POST_VARS['action'] = trim($HTTP_POST_VARS['action']);

if ($HTTP_GET_VARS['HTTP_COOKIE_VARS']['bbadminon'] == $HTTP_COOKIE_VARS['bbadminon']) {
  unset($HTTP_POST_VARS['action']);
}

// ###################### Start init #######################

unset($dbservertype);
unset($debug);
//load config
require("./config.php");
if ($debug != 1) {
	unset($showqueries);
	unset($explain);
}

// init db **********************
// load db class
$dbservertype = strtolower($dbservertype);
$dbclassname="./db_$dbservertype.php";
require($dbclassname);

$DB_site=new DB_Sql_vb;

$DB_site->appname="vBulletin Control Panel";
$DB_site->appshortname="vBulletin (cp)";
$DB_site->database=$dbname;
$DB_site->server=$servername;
$DB_site->user=$dbusername;
$DB_site->password=$dbpassword;

$DB_site->connect();

$dbpassword="";
$DB_site->password="";
// end init db

// load options
$optionstemp=$DB_site->query_first("SELECT template FROM template WHERE title='options'");
eval($optionstemp[template]);
$versionnumber=$templateversion;

// ###################### Start headers #######################
/*
$noheader=1;
if ($addheaders and !$noheader) {
  // default headers
  header("HTTP/1.0 200 OK");
  header("HTTP/1.1 200 OK");
  header("Content-type: text/html");
}
*/

if ($nocacheheaders and !$noheader) {
  // no caching
  header("Expires: Mon, 26 Jul 1997 05:00:00 GMT");             // Date in the past
  header("Last-Modified: " . gmdate("D, d M Y H:i:s") . " GMT"); // always modified
  header("Cache-Control: no-cache, must-revalidate");           // HTTP/1.1
  header("Pragma: no-cache");                                   // HTTP/1.0
}

// ###################### Start functions #######################
require("./functions.php");
require("./adminfunctions.php");

// ###################### Start sessions #######################
/*if (!isset($bbadminon) and !$bbadminon) {
  $sessionhash="";
  $bbuserinfo[userid]="";
  $bbuserinfo[password]="";
}*/

require("./sessions.php");

/*
if (isset($loginusername)) {
  if ($user=$DB_site->query_first("SELECT userid,password FROM user WHERE username='".addslashes($loginusername)."'")) {
    if ($user[password]==$password) {
      $userid=$user[userid];
      $bbuserinfo=getuserinfo($userid);
      $DB_site->query("UPDATE session SET userid='$userid' WHERE sessionhash='$session[dbsessionhash]'");
    }
  }
}
*/

if ( isset( $redirect ) ) {
	$redirect = htmlspecialchars(str_replace('javascript', 'java script', $redirect));
}

$getperms=$DB_site->query_first("SELECT cancontrolpanel FROM user,usergroup WHERE user.usergroupid=usergroup.usergroupid AND user.userid='$bbuserinfo[userid]'");
if ($getperms[cancontrolpanel]!=1) {
  $bbuserinfo[userid]=0;
}

if ($bbuserinfo[userid]!=0 and $loginusername and !$createanonsession) {
  vbsetcookie('bbadminon', 1, 0);
  $HTTP_COOKIE_VARS['bbadminon']=1;
} else {
  if ($bbuserinfo[userid]==0) {
    $HTTP_COOKIE_VARS['bbadminon']=0;
  }
}

if ($debug!=1) {
  // check for files existance. Potential security risks!
	if (file_exists("install.php")==1) {
		echo "<html><body><p>Security alert! install.php still remains in the admin directory. This poses a security risk, so please delete that file immediately. You cannot access the control panel until you do.</p></body></html>";
		exit;
	}

	if (file_exists("upgrade1.php")==1 and substr($PHP_SELF,-strlen("upgrade1.php"))!="upgrade1.php") {
		echo "<html><body><p><a href=\"upgrade1.php?s=$session[sessionhash]\">upgrade1.php</a> exists. If you have already upgraded fully, please delete it. Otherwise, run it now.</p></body></html>";
		exit;
	}
}

$checkpwd=1;
if ($HTTP_COOKIE_VARS['bbadminon']==0 and substr($PHP_SELF,-strlen("upgrade1.php"))!="upgrade1.php" and $checkpwd) {
  $bbuserinfo[userid]=0;
} else {
  if ($bbuserinfo['userid']!=0  and $loginusername and !$createanonsession) {
    setcookie("bbadminon",1,0,'/');
    $HTTP_COOKIE_VARS['bbadminon']=1;
  }
}

if ($bbuserinfo[userid]==0 and $checkpwd) {

  cpheader("<title>Forums admin</title>");
?><br><br><br>
<table cellpadding="1" cellspacing="0" border="0" class="tblborder" align="center" width="450"><tr><td>
<table cellpadding="4" cellspacing="0" border="0" width="100%">
<?php maketableheader("Please Log in:","login",0,1); ?>
<tr class="firstalt" id="submitrow"><td align="center" nowrap><p>You are either not a valid administrator or have not logged in.<?php var_dump($_SESSION); ?></p>
<form action="../admin/index.php" method="post" id="submitrow">
<input type="hidden" name="s" value="<?php echo $session[sessionhash]; ?>">
<input type="hidden" name="action" value="login">
<!-- <input type="hidden" name="explain" value="1"> -->
<input type="hidden" name="redirect" value="<?php

if ($HTTP_SERVER_VARS['REQUEST_URI']!="") {
  $url = $HTTP_SERVER_VARS['REQUEST_URI'];
} else {
  if ($PATH_INFO) {
    $url = $PATH_INFO;
  } else {
    $url = $PHP_SELF;
  }

  if ($QUERY_STRING) {
    $url .= "?$QUERY_STRING";
  }
}

  $url=ereg_replace("sessionhash=[a-z0-9]{32}&","",$url);
  $url=ereg_replace("\\?sessionhash=[a-z0-9]{32}","",$url);
  $url=ereg_replace("s=[a-z0-9]{32}&","",$url);
  $url=ereg_replace("\\?s=[a-z0-9]{32}","",$url);

echo htmlspecialchars(str_replace('javascript', 'java script', $url));

?>">
<table cellpadding="0" cellspacing="1" border="0">
<tr>
	<td><input type="text" name="loginusername"></td>
	<td><input type="password" name="loginpassword"></td>
	<td><input type="submit" value="   Log in   " accesskey="s"></td>
</tr>
<tr>
	<td><font size="1">Username</font></td>
	<td colspan="2"><font size="1">Password</font></td>
</tr>
</table>
</form>
</td></tr></table>
</td></tr></table>
<p align="center"><font size="1">vBulletin v<?php echo $templateversion ?> Administrator Control Panel</font></p>
<?php
  cpfooter();
  exit;
}

?>