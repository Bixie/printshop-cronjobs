<?php
/**
 *	com_bix_printshop - Online-PrintStore for Joomla and VirtueMart
 *  Copyright (C) 2010-2013 Matthijs Alles
 *	Bixie.org
 *
 */
 
define('_N_',"\n");
define('DS',DIRECTORY_SEPARATOR);

if (count($argv) < 2) die('Geen argumenten gegeven!'._N_);

$task = $argv[1];
switch ($task) {
	case 'dumpdb':
		$cronClass = new BixiePrintShopCronJobs($argv);
		$cronClass->dumpdb();
	break;
	default:
		BixiePrintShopCronJobs::log(sprintf('Task %s niet gevonden!'._N_,$task));
	break;
}


/*class cronjobs*/
class BixiePrintShopCronJobs {

	private $argv;
	private static $_DBFOLDER = 'dbdumps';
	private static $_HTMLFOLDER = 'public_html';
	private static $_LOGFOLDER = '../logs/bps_logs';

	public function __construct($argv) {
		$this->argv = $argv;
		$this->argc = count($argv);
	}
	
	private function _getArguments($aArgMap) {
		$aArguments = array();
		for ($i=0;$i<$this->argc;$i++) { 
			$sArgument = $this->argv[$i];
			if (isset($aArgMap[substr($sArgument,0,2)])) {
				if (strlen($sArgument) == 2) { //alleen switch, waarde is volgende argv
					$aArguments[$aArgMap[$sArgument]] = $this->argv[$i+1];
					$i++;
				} else { //waarde eraan vast
					$aArguments[$aArgMap[substr($sArgument,0,2)]] = substr($sArgument,2);
				}
			}
		}
		return $aArguments;
	}
	
	public function dumpdb() {
		//init vars
		$aArguments = $this->_getArguments(array('-f'=>'folder','-m'=>'max','-t'=>'tablegroup'));
		$basePath = dirname(__FILE__);
		$rootPath = str_replace(DS.basename(__DIR__),'',$basePath);
		$subFolder = isset($aArguments['folder'])? DS.$aArguments['folder']: '';
		$maxItems = isset($aArguments['max'])?intval($aArguments['max']):14;
		$tablegroup = !empty($aArguments['tablegroup'])?$aArguments['tablegroup']:'all';
		$tablegroups = array(
			'freq'=>'dhn2_bps_order dhn2_bps_orderoption dhn2_bps_orderoptionvalues dhn2_bps_besteladres dhn2_bps_bestelling dhn2_users dhn2_user_profiles dhn2_user_usergroup_map',
			'all'=>''
		);
		$tables = isset($tablegroups[$tablegroup])?$tablegroups[$tablegroup]:'';
		$sDumpPath = $rootPath.DS.self::$_DBFOLDER.$subFolder;
		if (!file_exists($sDumpPath)) {
			mkdir($sDumpPath, 0755, true);
		}
		//huidige dumps bekijken
		$allDates = array();
		if ($handle = opendir($sDumpPath)) {
			while (false !== ($entry = readdir($handle))) {
				if ($entry != "." && $entry != "..") {
					$allDates[$entry] = str_replace(array('dump_','.sql.zip','-'),'',$entry); //houdt datum over
				}
			}
			closedir($handle);
		}
		asort($allDates);
		if (count($allDates) > $maxItems - 1) {
			foreach ($allDates as $file=>$date) {
				@unlink($sDumpPath.DS.$file);
				self::log("$file verwijderd."._N_);
				break;
			}
		}
		$date = new DateTime('NOW',new DateTimeZone('UTC'));
		$now = $date->format('Ymd-His');
		$file = $sDumpPath.DS.'dump_'.$now.'.sql.zip';
		$logFile = $rootPath.DS.self::$_HTMLFOLDER.DS.'dbdumps_'.$date->format('Y-W').'.log';
		//db-gegevens
		$webFolder = $rootPath.DS.self::$_HTMLFOLDER;
		if (file_exists($webFolder.DS.'configuration.php')) {
			require_once $webFolder.DS.'configuration.php';
			$oJConfig = new JConfig();
			$command = "mysqldump --opt -h {$oJConfig->host} -u {$oJConfig->user} -p{$oJConfig->password} {$oJConfig->db} {$tables} | zip > {$file}";
			system($command, $retval);
			if ($retval == 0) {
				$sLog = $date->format('d-m-Y H:i:s').": Backup succesvol."._N_;
				$sLog .= "$command"._N_;
				self::log($sLog);
			} else {
				$sLog = $date->format('d-m-Y H:i:s').": Backup mislukt!."._N_;
				$sLog .= "$command"._N_;
				self::log($sLog);
			}
		} else {
			self::log("Configuratiebestand niet gevonden!"._N_);
		}
		die();
	}
	
	public static function log($message) {
		$date = new DateTime('NOW',new DateTimeZone('UTC'));
		$logPath = dirname(__FILE__).DS.self::$_LOGFOLDER;
		if (!file_exists($logPath)) {
			mkdir($logPath, 0755, true);
		}
		$logFile = $logPath.DS.'dbdumps_'.$date->format('Y-W').'.log';
		file_put_contents($logFile,$message,FILE_APPEND);
	}
}
