<?php
/* vim: set noexpandtab tabstop=2 softtabstop=2 shiftwidth=2: */

/**
 * Finish Payout plugin for XAseco  (v1.1, PHP 8 port)
 *
 * - pays coppers for finishing a map for the first time (optional)
 * - pays coppers for beating the gold time for the first time
 *   (to use author time instead, replace "goldtime" with "authortime" below)
 * - optional payment logging
 * - ignored login list
 *
 * Based on the lotto plugin by Mistral, ignored-players mod and tweaks by
 * Dr. Kaputnik, original Finish Payout (v1.0) by Naturtalent.
 *
 * Dependencies: XASECO with PHP 8 compatibility (get @ https://codeberg.org/hanouta/xaseco)
 * plugin.localdatabase.php, plugin.rasp.php in /xaseco/plugins.xml.
 *
 * !!! IMPORTANT: list this plugin in /xaseco/plugins.xml plugins.xml BEFORE
 * plugin.localdatabase.php and plugin.rasp.php. Those plugins store the finish
 * time and update the records list on the same event; if they run first,
 * this plugin can no longer tell whether it was a first finish.
 * A warning is logged at startup if the order is wrong.
 */

global $enablethefinishpayout, $finish_settings, $finish_paid;

$enablethefinishpayout = 1;
$finish_settings = array();
$finish_paid = array();

if ($enablethefinishpayout == 1) {
	Aseco::registerEvent('onStartup', 'finishpayout');
	Aseco::registerEvent('onSync', 'finishpayout_sync');
	Aseco::registerEvent('onNewChallenge', 'finishpayout_newchallenge');
	Aseco::registerEvent('onPlayerFinish', 'CheckFinishPayment');
}

function finishpayout($aseco) {
	global $finish_settings;

	$finish_settings = array();
	$file = 'finish_config.xml';

	$xml = file_exists($file) ? @simplexml_load_file($file) : false;
	if ($xml === false) {
		trigger_error('[FinishPayout] Could not read/parse ' . $file . ' - plugin disabled!', E_USER_WARNING);
		return;
	}

	$ignored = array();
	foreach ($xml->ignoredplayers->ignoredlogin as $login) {
		$login = trim((string)$login);
		if ($login != '') $ignored[] = $login;
	}

	$finish_settings['ServerName']     = trim((string)$xml->servername);
	$finish_settings['MaxWin']         = max(0, (int)(string)$xml->maxwin);
	$finish_settings['MinWin']         = max(0, (int)(string)$xml->minwin);

	$finish_settings['GoldFirstWin']   = (trim((string)$xml->goldfirstwin) !== '')
	                                     ? max(0, (int)(string)$xml->goldfirstwin)
	                                     : $finish_settings['MaxWin'] + $finish_settings['MinWin'];
	$finish_settings['Log']            = ((int)(string)$xml->log == 1) ? 1 : 0;
	$finish_settings['PayFirstFinish'] = ((int)(string)$xml->payfirstfinish == 1) ? 1 : 0;
	$finish_settings['IgnoredPlayers'] = $ignored;

	if ($finish_settings['ServerName'] == '') $finish_settings['ServerName'] = 'a nice server';

	$aseco->console('## ## ##  FinishPayout initialized! Ignored players: {1}  ## ## ##', count($ignored));
}

function finishpayout_sync($aseco) {
	$aseco->plugin_versions[] = array(
		'plugin'  => 'plugin.finish_payout.php',
		'author'  => 'Naturtalent (PHP 8 port)',
		'version' => '1.1'
	);

	$mine = array_search('plugin.finish_payout.php', $aseco->plugins);
	if ($mine === false) return;
	foreach (array('plugin.localdatabase.php', 'plugin.rasp.php') as $other) {
		$pos = array_search($other, $aseco->plugins);
		if ($pos !== false && $pos < $mine) {
			trigger_error('[FinishPayout] plugin.finish_payout.php must be listed BEFORE ' . $other .
			              ' in plugins.xml, otherwise first finishes cannot be detected!', E_USER_WARNING);
		}
	}
}

function finishpayout_newchallenge($aseco, $challenge) {
	global $finish_paid;

	$finish_paid = array();
}

function finishpayout_besttime($aseco, $finish_item, $stunts) {
	global $maxrecs;

	$login = $finish_item->player->login;

	$max = isset($maxrecs) ? (int)$maxrecs : 30;
	for ($i = 0; $i < $max; $i++) {
		$rec = $aseco->server->records->getRecord($i);
		if ($rec === false) break;
		if ($rec->player->login == $login) return (int)$rec->score;
	}

	$pid = (int)$finish_item->player->id;
	$cid = (isset($finish_item->challenge->id) ? (int)$finish_item->challenge->id : (int)$aseco->server->challenge->id);
	if ($pid <= 0 || $cid <= 0) return false;

	$query = 'SELECT Score FROM rs_times WHERE PlayerId=' . $pid . ' AND ChallengeId=' . $cid .
	         ' ORDER BY Score ' . ($stunts ? 'DESC' : 'ASC') . ' LIMIT 1';
	$result = $aseco->db->query($query);
	if ($result === false) {
		trigger_error('[FinishPayout] Could not read times! (' . $aseco->db->errno . ': ' . $aseco->db->error . ')' . CRLF . 'sql = ' . $query, E_USER_WARNING);
		return false;
	}

	$best = 0;
	if ($result->num_rows > 0) {
		$row = $result->fetch_row();
		$best = (int)$row[0];
	}
	$result->free();
	return $best;
}

function CheckFinishPayment($aseco, $finish_item) {
	global $finish_settings, $finish_paid;

	if (empty($finish_settings)) return;

	if ($finish_item->score <= 0) return;

	$mode = $aseco->server->gameinfo->mode;
	if ($mode == Gameinfo::LAPS && empty($finish_item->new)) return;
	$stunts = ($mode == Gameinfo::STNT);

	$player = $finish_item->player;
	$login = $player->login;
	$nickname = $player->nickname;
	$goldtime = (int)$aseco->server->challenge->goldtime;

	$best = finishpayout_besttime($aseco, $finish_item, $stunts);
	if ($best === false) return;

	$gold = (!$stunts && $goldtime > 0 && $finish_item->score <= $goldtime &&
	         ($best == 0 || $best > $goldtime));
	$first = ($best == 0 && $finish_settings['PayFirstFinish'] == 1);

	if ($gold && isset($finish_paid[$login]['gold'])) $gold = false;
	if ($first && isset($finish_paid[$login]['first'])) $first = false;

	$ignored = in_array($login, $finish_settings['IgnoredPlayers'], true);

	if ($gold) {
		if ($ignored) {
			$message = '$FF0>> ' . $nickname . '$z$0F0$s beats the gold time for the first time, but they\'re too rich to get coppers $F04:p';
		} elseif ($best == 0) {
			$message = '$FF0>> ' . $nickname . '$z$0F0$s beats the gold time with the first finish';
		} else {
			$message = '$FF0>> ' . $nickname . '$z$0F0$s finally beats the gold time. Good job.';
		}
		$aseco->client->query('ChatSendServerMessage', $aseco->formatColors($message));
	}

	if ($ignored) return;

	if ($gold && $first)  $amount = $finish_settings['GoldFirstWin'];  // gold on the first finish
	elseif ($gold)        $amount = $finish_settings['MaxWin'];        // gold time
	elseif ($first)       $amount = $finish_settings['MinWin'];        // plain first finish
	else                  $amount = 0;
	if ($amount <= 0) return;

	if ($aseco->server->getGame() != 'TMF' || !$aseco->server->rights || !$player->rights) return;

	$win = min($amount, 500);
	$win = intval($win / 5) * 5;
	if ($win < 5) $win = 5;
	$tax = intval($win / 20) + 3;

	$aseco->client->query('GetServerCoppers');
	$coppers = $aseco->client->getResponse();
	if (!is_numeric($coppers) || $coppers <= ($win + $tax)) {
		if ($gold) {
			$message = '$ff0>> $0F0Fantastic! But sorry there are no coppers left on the server.';
			$aseco->client->query('ChatSendServerMessage', $aseco->formatColors($message));
		} else {
			$message = 'Nice one, but there are no coppers left on the server :(';
			$aseco->client->query('ChatSendServerMessageToLogin', $aseco->formatColors($message), $login);
		}
		return;
	}

	if ($gold)  $finish_paid[$login]['gold'] = true;
	if ($first) $finish_paid[$login]['first'] = true;

	$label = 'You won coppers on ' . $finish_settings['ServerName'] . '$z for your amazing driving skills!';
	if (!$aseco->client->query('Pay', $login, $win, $label)) {
		trigger_error('[FinishPayout] Could not pay ' . $win . ' coppers to ' . $login . '! (' .
		              $aseco->client->getErrorCode() . ': ' . $aseco->client->getErrorMessage() . ')', E_USER_WARNING);

		if ($gold)  unset($finish_paid[$login]['gold']);
		if ($first) unset($finish_paid[$login]['first']);
		return;
	}

	if ($gold) {
		$message = '$ff0>> $FFF' . $nickname . '$z$0F0$s wins $fff' . $win . ' $0f0coppers for superb driving :)';
		$aseco->client->query('ChatSendServerMessage', $aseco->formatColors($message));
		if ($finish_settings['Log'] == 1)
			$aseco->console('Finishpayout: {1} wins {2} coppers for goldtime' . ($first ? ' and first finish' : ''), $login, $win);
	} else {
		$message = '$ff0>> $FFFYou$z$0F0$s win $fff' . $win . ' $0f0coppers for finishing the track the first time :)';
		$aseco->client->query('ChatSendServerMessageToLogin', $aseco->formatColors($message), $login);
		if ($finish_settings['Log'] == 1)
			$aseco->console('Finishpayout: {1} wins {2} coppers for finishing', $login, $win);
	}
}
?>
