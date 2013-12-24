<?php
/**
 *	com_bix_printshop - Online-PrintStore for Joomla and VirtueMart
 *  Copyright (C) 2010-2013 Matthijs Alles
 *	Bixie.org
 *
 */



 
class BixiePrintShopCronJobs {

	function __construct() {
	}
	
	function dumpdb() {
		//$BPSconfig = BixTools::config();
		$basePath = JPATH_ROOT;
		$rootPath = str_replace('/public_html','',$basePath);
		$subFolder = JRequest::getVar('folder','')?'/'.JRequest::getVar('folder'):'';
		$maxItems = JRequest::getInt('max',0)?JRequest::getVar('max'):14;
		$allDates = array();
		if ($handle = opendir($rootPath.'/dbdumps'.$subFolder)) {
			while (false !== ($entry = readdir($handle))) {
				if ($entry != "." && $entry != "..") {
					$allDates[$entry] = str_replace(array('dump_','.sql.zip','-'),'',$entry);
				}
			}
			closedir($handle);
		}
		asort($allDates);
		if (count($allDates) > $maxItems - 1) {
			foreach ($allDates as $file=>$date) {
				@unlink($rootPath.'/dbdumps'.$subFolder.'/'.$file);
				break;
			}
		}
		$now = date('Ymd-His');
		$file = $rootPath.'/dbdumps'.$subFolder.'/dump_'.$now.'.sql.zip';
		system("mysqldump --opt -h localhost -u drukhetnu_live -pX2T6UEqO drukhetnu_live | zip > ".$file);
	
		die();
	
	}
	
	function regCron() {
		$bpsConfig = BixTools::config();
		$task = JRequest::getVar('task');
		//alerts checken
		$this->setAlerts($task);
		switch ($task) {
			case 'hour':
			break;
			case '3day':
			break;
			case 'daily':
				//alerts auto afsluiten
				if ($bpsConfig->alertAutoVerlopen) {
					$this->alertAutoVerlopen();
				}
			break;
			case 'week':
			break;
		}
		//offertes afsluiten
		if ($bpsConfig->offerteVerlopenFreq == $task && $bpsConfig->offerteVerlopenRemind) {
			$this->verlopenOfferte();
		}
		if (count($this->getErrors())) {
			//print voor cronlog
			pr($this->getErrors());
		}
	}
	
	function setAlerts($event) {
		$bpsConfig = BixTools::config();
		$dispatcher	   =& JDispatcher::getInstance();
		JPluginHelper::importPlugin('bps');
		$eventResults = $dispatcher->trigger('getEventAlerts',array($event));
		if (count($eventResults)) {
			foreach ($eventResults as $eventResult) {
				if ($eventResult == '') continue;
				if ($eventResult['result']->alerts) {
					foreach ($eventResult['result']->alertConfigs as $alertConfig) {
						$alertResults = $dispatcher->trigger('setAlerts',array($eventResult['prefix'],$alertConfig));
				//pr($alertResults);
						foreach ($alertResults as $alertResult) {
							if ($alertResult['result']->nrAlerts) echo 'Resultaat '.$alertResult['prefix'].': '.$alertResult['result']->nrAlerts.' meldingen.<br/>';
						}
						
					}
				}
		
			}
		}
		
	}
	
	function alertAutoVerlopen() {
		require_once BIX_PATH_ADMIN_CLASS.DS.'bixalert.php';
		$bpsConfig = BixTools::config();
		$db = JFactory::getDBO();
		$alertAutoVerlopen = $bpsConfig->alertAutoVerlopen > 0 ? (int)$bpsConfig->alertAutoVerlopen: 5;
		
		$query =  "SELECT alertID FROM #__bps_alerts WHERE state = 0 AND DATE_ADD(created, INTERVAL $alertAutoVerlopen DAY) < NOW() ORDER BY created DESC";
		//$query =  "SELECT DATE_ADD(created, INTERVAL $daysValid DAY) AS verlopen FROM #__bps_bestellingen ORDER BY created DESC LIMIT 0,10";
		$db->setQuery( $query );
		$alertVerlopen = $db->loadResultArray();
		foreach ($alertVerlopen as $id) {
			$alertItem = new BixAlertItem();
			$alertItem->load($id);
			if(!$alertItem->toggleState()) {
				$this->setError($bixAlert->getError());
				break;
			}
		}
	}
	
	function verlopenOfferte() {
		require_once BIX_PATH_ADMIN_CLASS.DS.'bixalert.php';
		$bpsConfig = BixTools::config();
		$db = JFactory::getDBO();
		$daysValid = $bpsConfig->offerteValidDays > 0 ? (int)$bpsConfig->offerteValidDays: 30;
		//UPDATE `drukhetnu_live`.`dhn_bps_bestellingen` SET `bestelStatus` = 'OFFERTE' WHERE `dhn_bps_bestellingen`.`bestelStatus` = 'VERL_OFFERTE';

		$query =  "SELECT b.*, DATE_ADD(b.created, INTERVAL $daysValid DAY) AS verl, a.alertID FROM #__bps_bestellingen  AS b
			LEFT JOIN #__bps_alerts AS a ON a.bestelID = b.bestelID AND a.type = 'offerteVerlopen'
			WHERE bestelType = 'OFFERTE' AND bestelStatus = 'OFFERTE' AND DATE_ADD(b.created, INTERVAL $daysValid DAY) < NOW() 
			AND a.alertID IS NULL
			ORDER BY b.created DESC";
		$db->setQuery( $query );
		$offerteVerlopen = $db->loadObjectList();
//echo $db->stderr();
		$alertList = array();
		foreach ($offerteVerlopen as $bestelRow) {
			$alertItem = new BixAlertItem();
			$alertItem->type = 'offerteVerlopen';
			$alertItem->prio = $bpsConfig->offerteVerlopenPrio;
			$alertItem->userID = $bestelRow->userID;
			$alertItem->bestelID = $bestelRow->bestelID;
			$alertItem->state = 1;
			//params
			$alertItem->params->link = 'index.php?option=com_bix_printshop&controller=bestelling&task=details&cid[]='.$bestelRow->bestelID;
			$alertItem->params->type = $bpsConfig->offerteVerlopenRemind;
			$alertItem->params->mailEvent = $bpsConfig->mail_offerteverlopen;
			//actions
			$action = new stdClass;
			$action->fired = false;
			$action->event = 'detection';
			$action->title = 'Offerte als verlopen markeren';
			$action->log = 'Offerte als verlopen gemarkeerd';
			$action->type = 'doAction';
			$action->className = 'BixBestel';
			$action->functionName = 'offerteVerlopen';
			$action->functionArgs = array('bestelID'=>$bestelRow->bestelID);
			$alertItem->addAction($action);
			//mailacties
			$alertItem->setMailActions();
			if (!$return = $alertItem->doAction('detection')) {
				$this->setError($alertItem->getError());
				$alertItem->prio--;
				$alertItem->title = JText::_('Offerte verlopen maar niet afgesloten');
				$alertItem->descr = JText::sprintf('Offerte %d is verlopen maar heeft fout bij afsluiten.',$bestelRow->bestelID);
			} elseif ($return->changed) {
				$alertItem->title = JText::_('Offerte verlopen en afgesloten');
				$alertItem->descr = JText::sprintf('Offerte %d is verlopen en is afgesloten.',$bestelRow->bestelID);
			}
			if (!$return = $alertItem->doMail('detection')) {
				$result->error = $alertItem->getError();
				$alertItem->prio--;
			}
			$alertList[] = $alertItem;
		}
//pr($alertList);
		$bixAlert = new BixAlert();
		$bixAlert->setAlertList($alertList);
		if (!$bixAlert->save()) {
			$this->setError($bixAlert->getError());
		}
	//pr($this->getErrors());	
	
	}
	
	
}
