<?php

/*
   PHP FTP API

   A single file, single function, FTP Client.

   Doing ftp stuff in PHP is to either use PHP ftp functions directly or 
   to use an existing ftp API which is inevitably implemented as a class.

   The PHP example:

      $conn_id = ftp_connect($ftp_server);
      $login_result = ftp_login($conn_id, $ftp_user_name, $ftp_user_pass);
      echo ftp_pwd($conn_id);
      ftp_close($conn_id);

   A class example:

      $ftp = new \FtpClient\FtpClient();
      $ftp->connect($host);
      $ftp->login($login, $password);
      echo $ftp->pwd();
      $ftp->close();

   Seriously, what is the difference between those two examples? The PHP way 
   has an extra variable. That is all. (The test for connect success is not 
   part of the examples; but would be nearly identical.) The class way looks 
   better? Idunno. But the class way inevitably adds a whole lotta code!

   This is why I created this function, used like so:

      $res = ftpclient($ftp, 'connect');
      $res = ftpclient($ftp, 'login');
      echo ftpclient($ftp, 'pwd');
      ftpclient($ftp, 'close');

   The BIG difference is that the $ftp variable must exist as an array, like:

      $ftp = array(
          'host' => '',
          'user' => '',
          'pass' => '',
      );

   Which is suitable for a "config" file/data; such as in an INI format:

      ; basic, one off, or defaults, usage:
      host = localhost
      user = user
      pass = password

      ; example of multiple hosts
      [exam]
      host = example.com
      user = ms@example.com
      pass = Kns865Bfac!
      pasv = 1

   There can be many more FTP array values defined (if not, typical defaults 
   would be used).

   The function also has a few shortcut uses:

      $res = ftpclient($ftp); // 'connect', 'login' and 'pasv' (if it exists)
      $res = ftpclient();     // false if not connected else the FTP stream

   OKAY, there's the need of an array for host/user/passwd, but quite simple 
   to use. AND there's the use of string "constants": 'connect', login', etc.
   BUT it ain't a class and just one function of a couple hundred lines!

   SEE END for more discussion.
*/

function ftpclient($ftp = null, $cmd = '', $arg = null, $arg2 = null) {
static $conn = null;

	if ($ftp == null) {
		return $conn;
	}

	if ($cmd == '') {
		if (!$conn) {
			$res = ftpclient($ftp,'connect');
			if ($res) {
				$u = ftpclient_value($ftp,'user');
				if ($u && !ftpclient($ftp,'login')) {
					ftpclient($ftp,'close');
					$res = false;
				}
			}
			$conn = $res;
		}
		return $conn;
	}

	if ($cmd == 'connect') {
		$p = ftpclient_value($ftp,'port',21);
		$t = ftpclient_value($ftp,'timeout',90);
		$res = ftp_connect($ftp['host'],$p,$t);
		if ($res) {
			$conn = $res;
		}
		return $conn;
	}

	if (!$conn) {
		return $conn;
	}

	switch $cmd {
	case 'quit':
	case 'close':
		$res = ftp_close($conn);
		$conn = null;
		break;
	case 'login':
		$res = ftp_login($conn,$ftp['user'],$ftp['password']);
		if (!$res && empty($ftp['stay'])) {
			ftpclient($ftp,'close');
			$conn = null;
		}
		break;
	case 'pasv':
		$p = ftpclient_value($ftp,'pasv',true);
		$res = ftp_pasv($conn,$p);
		break;
	case 'pwd':
		$res = ftp_pwd($conn);
		break;
	case 'get':
		$m = ftpclient_value($ftp,'mode',FTP_BINARY);
		$res = ftp_get($conn,$arg,$arg2,$m);
		break;
	case 'put':
		$m = ftpclient_value($ftp,'mode',FTP_BINARY);
		$res = ftp_put($conn,$arg,$arg2,$m);
		break;
	case default:
		$res = false;
		break;
	}

	return $res;
}

function ftpclient_value($ftp, $val, $def = null) {
	return (isset($ftp[$val])) ? $ftp[$val] : $def;
}

// END

/*
   Obviously, not all PHP ftp functions are supported, but as can be seen 
   it would be near trivial to add them.

   The ftpclient_value() function just reduces overall code size; derived 
   from typical PHP code like:

      if (isset($ftp['port'])) {
         $p = $ftp['port'];
      } else {
         $p = 21;
      }

   or:

      $p = 21;
      if (isset($ftp['port'])) {
         $p = $ftp['port'];
      }

   or:

      $p = (isset($ftp['port'])) ? $ftp['port'] : 21;

   to:

      $p = ftpclient_value($ftp,'port',21);

   Maybe that's not much of a savings, but it is a reduction of complexity 
   for when adding more FTP function support (less typing; it could be named 
   _value() or something, but as named most likely avoids a name collision).

   The code block for $cmd == '' looks sloppy, and introduces recursion, but 
   this is about overall code reduction, and recursion works and it ain't 
   THAT sloppy...

   Having the $cmd == 'connect' outside of the switch is so that the next 
   test for $conn not valid avoids such a test inside some of the cases.

   (If one insists on code having a single return point, all I can say is 
   that doing so here will just make the code larger and more complex. Could 
   add a goto or two! But then, some people insist on not using goto!)

   There are a few error tests lacking, like 'login' assuming 'user' and 
   'pass' being set. 

   The use of single letter variable names is also a (most likely) frowned 
   upon thing, but their use are always temporary and a scope of about two 
   lines.

   However, as the code grows - if it does at all - a few "top" defined 
   "named nice" variables might be better, such as:

      $mode = ftpclient_value($ftp,'mode',FTP_BINARY);

   And then the two lines like that in the 'get' and 'put' cases go away.

   With only one extra "init" functin call, one can store the $ftp data as 
   a static, changing the function to:

      ftpclient($cmd = '', $arg = null, $arg2 = null) 

   And either:

      ftpclient('init',$ftp);

   Or perhaps requiring 'connect' to have $ftp as $arg. (That would require 
   require using the pair 'connect' and 'login' as the other examples above.
   Not such a bad thing I suppose.)

   Of course there are many other ways to implement this, like having it 
   ftpclient($cmd, $arg1='', $arg2='', ...) with enough arguments to cover 
   all the PHP functions (five, I think). One can also do:

      ftpclient($cmd) {
         $args = func_get_args();
         ...
      }

   With the internals one of:

      $res = ftp_get($conn,$arg1,$arg2,$m);

      $res = ftp_get($conn,$args[0],$args[1],$m);

   And the calls "backified" to more like the PHP ftp functions (the use of 
   the $ftp array eliminated):

      ftpclient('connect',$host,$user,$pass);
      ftpclient('put',$remotename,$filename,FTP_BINARY);
      ftpclient('close');

   But use of the $ftp array has it's uses (no pun intended), with expanded 
   members like:

      $ftp = array(
         'remote' => 'remotefile',
         'put' => 'localfile',
         'mode' => FTP_BINARY,
      }

   Then it can be:

      ftpclient($ftp);
      ftpclient($ftp,'put');
      ftpclient($ftp,'close');

   Upon reflection, the 'connect', 'login' can be done upon "first use", 
   eliminating that first line.

   The possibilities are endless, of course. The point being to eliminate 
   OOP constructs, reducing the same OOP capabilities to a single file, 
   single function, replacement. The FtpClient example above is from a 
   class of:

       956  3062 27641 FtpClient.php
        20    79   546 FtpException.php
       116   748  5223 FtpWrapper.php
      1092  3889 33410 total

   Anyway, this is my way of an example why use of classes are often just 
   more complex and excessive code than is really necessary. Plus, they near 
   always need a global variable statement to be used. Recognize this?

      function display_setup_form( $error = null ) {
         global $wpdb;
         ...
      }

*/
