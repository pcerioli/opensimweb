<?php
if (!defined('OSW_IN_SYSTEM')) {
	exit;
}

class Sessions {

var $osw;

	function Sessions(&$osw) {
		$this->osw = &$osw;
	}

	function clear_old_sessions() {
        $time_minus_defined = time() - $this->osw->config['cookie_length'];
        $this->osw->SQL->query("DELETE FROM `{$this->osw->config['db_prefix']}sessions` WHERE time < '$time_minus_defined'");
	}

	function create_session($uuid, $remember) {
		$time = time();

		$code = $this->osw->getNewUUID();

		$sesscheckq = $this->osw->SQL->query("SELECT * FROM `{$this->osw->config['db_prefix']}sessions` WHERE id = '$uuid'");
		$sesschecker = $this->osw->SQL->num_rows($sesscheckq);
		if ($sesschecker) {
			$this->osw->SQL->query("UPDATE `{$this->osw->config['db_prefix']}sessions` SET code = '$code', time = '$time' WHERE id = '$uuid'");
		}else{
			$this->osw->SQL->query("INSERT INTO `{$this->osw->config['db_prefix']}sessions` (id, code, time) VALUES ('$uuid','$code','$time')");
		}
		$this->osw->SQL->query("UPDATE `{$this->osw->config['robust_db']}`.auth SET webLoginKey = '$code' WHERE UUID = '$uuid'");
		
		if ($remember == "true") {
			setcookie($this->osw->config['cookie_prefix'] . 'id', $uuid, $time + $this->osw->config['cookie_length'], $this->osw->config['cookie_path'], $this->osw->config['cookie_domain'], false, false);
			setcookie($this->osw->config['cookie_prefix'] . 'time', sha1($time), $time + $this->osw->config['cookie_length'], $this->osw->config['cookie_path'], $this->osw->config['cookie_domain'], false, false);
			setcookie($this->osw->config['cookie_prefix'] . 'code', $code, $time + $this->osw->config['cookie_length'], $this->osw->config['cookie_path'], $this->osw->config['cookie_domain'], false, false);
		}

		$_SESSION[$this->osw->config['cookie_prefix'] . 'id'] = $uuid;
		$_SESSION[$this->osw->config['cookie_prefix'] . 'time'] = sha1($time);
		$_SESSION[$this->osw->config['cookie_prefix'] . 'code'] = $code;
	}

	function fetch_session($information) {
		$uid = $information[0];
		$utime = $information[1];
		$ucode = $information[2];
        $session_infoq = $this->osw->SQL->query("SELECT * FROM `{$this->osw->config['db_prefix']}sessions` WHERE code = '$ucode'");
        $session_info = $this->osw->SQL->fetch_array($session_infoq);
        $sess_time = sha1($session_info['time']);
		if ($session_info['id'] == $uid) {
			if ($sess_time == $utime) {
			    return true;
			}else{
			    return false;
			}
		}else{
		   return false;
		}
	}

	function validate_session($information) {
		if (is_array($information)) {
			$uid = $information[0];
			$utime = $information[1];
			$ucode = $information[2];

			if ($this->fetch_session($information)) {
                    $new_time = time();
                    $sha1_time = sha1($new_time);

                    if (isset($_COOKIE[$this->osw->config['cookie_prefix'] . 'time'])) {
                        setcookie($this->osw->config['cookie_prefix'] . 'time', $sha1_time, $new_time + $this->osw->config['cookie_length'], $this->osw->config['cookie_path'], $this->osw->config['cookie_domain'], false, false);
                    }

                    $_SESSION[$this->osw->config['cookie_prefix'] . 'time'] = $sha1_time;

                    $this->osw->SQL->query("UPDATE `{$this->osw->config['db_prefix']}sessions` SET time = '$new_time' WHERE id = '$uid'");

					$q2 = $this->osw->SQL->query("SELECT * FROM `{$this->osw->config['robust_db']}`.auth WHERE webLoginKey = '$ucode'");
					$r2 = $this->osw->SQL->fetch_array($q2);
					$aviuuid = $r2['UUID'];
					$q = $this->osw->SQL->query("SELECT * FROM `{$this->osw->config['robust_db']}`.UserAccounts WHERE PrincipalID = '$aviuuid'");
                	$r = $this->osw->SQL->fetch_array($q);
                    foreach ($r as $key => $value) {
						$this->osw->user_info[$key] = $value;
					}

				    return true;
			}else{
				return false;
			}
		}else{
		    return false;
		}
	}

	function find_session() {
		if (isset($_COOKIE[$this->osw->config['cookie_prefix'] . 'id'])) {
            $information = array(
                $_COOKIE[$this->osw->config['cookie_prefix'] . 'id'],
                $_COOKIE[$this->osw->config['cookie_prefix'] . 'time'],
                $_COOKIE[$this->osw->config['cookie_prefix'] . 'code']
            );
            return $this->validate_session($information);
		}else if (isset($_SESSION[$this->osw->config['cookie_prefix'] . 'id'])) {
            $information = array(
                $_SESSION[$this->osw->config['cookie_prefix'] . 'id'],
                $_SESSION[$this->osw->config['cookie_prefix'] . 'time'],
                $_SESSION[$this->osw->config['cookie_prefix'] . 'code']
            );
            return $this->validate_session($information);
		}else{
            return $this->validate_session('');
		}
	}
}
?>