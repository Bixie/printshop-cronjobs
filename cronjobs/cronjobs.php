<?php
/**
 *	com_bixprintshop - Online-PrintStore for Joomla
 *	Copyright (C) 2012-2013 Matthijs Alles
 *	Bixie.nl
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
		BixiePrintShopCronJobs::log(sprintf('Task %s niet gevonden!'._N_,$task),'dbdumps');
		break;
}


/*class cronjobs*/
class BixiePrintShopCronJobs {

	private $argv;
	private $argc;
	
	private static $_DBFOLDER = '../dbdumps';
	private static $_HTMLFOLDER = '../public_html';
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
		$subFolder = isset($aArguments['folder'])? DS.$aArguments['folder']: '';
		$maxItems = isset($aArguments['max'])?intval($aArguments['max']):14;
		$tablegroup = !empty($aArguments['tablegroup'])?$aArguments['tablegroup']:'all';
		$tablegroups = array(
			'freq'=>'dhn2_bps_order dhn2_bps_orderoption dhn2_bps_orderoptionvalues dhn2_bps_besteladres dhn2_bps_bestelling dhn2_users dhn2_user_profiles dhn2_user_usergroup_map',
			'all'=>''
		);
		$tables = isset($tablegroups[$tablegroup])?$tablegroups[$tablegroup]:'';
		$sDumpPath = $basePath.DS.self::$_DBFOLDER.$subFolder;
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
			foreach ($allDates as $file=>$date) { //of iets van array_shift(array_keys($allDates)) ??
				@unlink($sDumpPath.DS.$file);
				self::log("Bestand $file verwijderd."._N_,'dbdumps');
				break;
			}
		}
		$date = new DateTime('NOW',new DateTimeZone('UTC'));
		$now = $date->format('Ymd-His');
		$file = $sDumpPath.DS.'dump_'.$now.'.sql.zip';
		//db-gegevens uit Joomla config
		if (file_exists($basePath.DS.self::$_HTMLFOLDER.DS.'configuration.php')) {
			require_once $basePath.DS.self::$_HTMLFOLDER.DS.'configuration.php';
			$oJConfig = new JConfig();
			$command = "mysqldump --opt -h {$oJConfig->host} -u {$oJConfig->user} -p{$oJConfig->password} {$oJConfig->db} {$tables} | zip > {$file}";
			//uitvoeren als command
			system($command, $retval);
			if ($retval == 0 && file_exists($file)) {
				$sSize = round((filesize($file/1024)),2);
				$sLog = "$command"._N_;
				$sLog .= sprintf(.'%s: Backup succesvol, %sMb.'._N_,$date->format('d-m-Y H:i:s'),$sSize);
				self::log($sLog,'dbdumps');
			} else {
				$sLog = "$command"._N_;
				$sLog .= sprintf(.'%s: Backup mislukt!'._N_,$date->format('d-m-Y H:i:s'));
				self::log($sLog,'dbdumps');
			}
		} else {
			self::log("Configuratiebestand niet gevonden!"._N_,'dbdumps');
		}
	}
	
	public static function log($message,$prefix='log') {
		$date = new DateTime('NOW',new DateTimeZone('UTC'));
		$logPath = dirname(__FILE__).DS.self::$_LOGFOLDER;
		if (!file_exists($logPath)) {
			mkdir($logPath, 0755, true);
		}
		$logFile = $logPath.DS.$prefix.'_'.$date->format('Y-W').'.log';
		file_put_contents($logFile,$message,FILE_APPEND);
	}
}
