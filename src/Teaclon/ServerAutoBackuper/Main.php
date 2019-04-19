<?php

/*                             Copyright (c) 2017-2018 TeaTech All right Reserved.
 *
 *      ████████████  ██████████           ██         ████████  ██           ██████████    ██          ██
 *           ██       ██                 ██  ██       ██        ██          ██        ██   ████        ██
 *           ██       ██                ██    ██      ██        ██          ██        ██   ██  ██      ██
 *           ██       ██████████       ██      ██     ██        ██          ██        ██   ██    ██    ██
 *           ██       ██              ████████████    ██        ██          ██        ██   ██      ██  ██
 *           ██       ██             ██          ██   ██        ██          ██        ██   ██        ████
 *           ██       ██████████    ██            ██  ████████  ██████████   ██████████    ██          ██
**/

namespace Teaclon\ServerAutoBackuper;

// Basic;
use pocketmine\Player;
use pocketmine\Server;
use pocketmine\utils\Config;

use Teaclon\ServerAutoBackuper\command\MainCommand;

// 下次commit之前请先填写 -m "Fixed a BUG with Configuration" !
class Main extends \pocketmine\plugin\PluginBase implements \pocketmine\event\Listener
{
	const PLUGIN_VERSION       = "1.0.7_en";
	
	const STRING_PRE           = "ServerAutoBackuper";
	const UPDATE_PRE           = self::NORMAL_PRE."§a UPDATE §e>§f ";
	const WARNING_PRE          = "§e".self::STRING_PRE." §r§e>§f ";
	const ERROR_PRE            = "§c".self::STRING_PRE." §r§e>§f ";
	const NORMAL_PRE           = "§b".self::STRING_PRE." §r§e>§f ";
	
	const CONFIG_FTP_USER      = "UserName";
	const CONFIG_FTP_PASSWORD  = "Password";
	const CONFIG_FTP_ADDRESS   = "ip-address";
	const CONFIG_FTP_PORT      = "port";
	const CONFIG_RETRY_TIME    = "retry-time";
	const CONFIG_AUTO_BACKUP_MODE     = "auto-backup-mode";
	const CONFIG_AUTO_BACKUP_PERIODIC = "auto-backup-periodic";
	const CONFIG_AUTO_DELETE_OLD_DATA = "auto-delete-old-data";
	const CONFIG_AUTO_BACKUP_PATH     = "auto-backup-path";
	
	
	public static $instance    = null;
	private $server            = null;
	private $logger            = null;
	private $tsapi             = null;
	private $config            = null;
	
	
	public function onLoad()
	{
		foreach(["ftp_connect", "ftp_login", "ftp_close", "ftp_mkdir", "ftp_delete", "ftp_get", "ftp_put", "ftp_chdir", "ftp_mdtm"] as $function_name)
		{
			if(@!\function_exists($function_name))
			{
				self::stopThread($this->getName(), "Your Server do not support to use FTP Extendsion, Please remove this plugin", 6666);
			}
		}
		$this->ssm(self::NORMAL_PRE."§aFTP Exception checked.");
		
		if($this->getDescription()->getVersion() > self::PLUGIN_VERSION)
		{
			self::stopThread($this->getName(), "Invalid version.");
		}
	}
	
	
	public function onEnable()
	{
		$start = microtime(true);
		$this->server = $this->getServer();
		$this->logger = $this->getServer()->getLogger();
		$this->mypath = $this->getDataFolder(); if(!is_dir($this->mypath)) mkdir($this->mypath, 0777, true);
		$this->config = new Config($this->getDataFolder()."config.yml", Config::YAML, 
		[
			self::CONFIG_FTP_USER     => "teaclon",
			self::CONFIG_FTP_PASSWORD => "123456",
			self::CONFIG_FTP_ADDRESS  => "127.0.0.1",
			self::CONFIG_FTP_PORT     => 21,
			self::CONFIG_RETRY_TIME   => 3,
			self::CONFIG_AUTO_BACKUP_PERIODIC => 1,
			self::CONFIG_AUTO_BACKUP_MODE     => \false,
			self::CONFIG_AUTO_DELETE_OLD_DATA => \true,
			"notice-1" => "关于这项自定义路径备份的设置, 请输入在服务器根目录下你需要备份的文件夹名称即可. 例如我想备份worlds文件夹, 则直接添加文件夹名称'worlds'.",
			"notice-2" => "下面这个'all'是指备份服务器全部数据(整个根目录). 如果你不想备份完整的服务器数据, 移除这个选项即可.",
			"notice-3" => "如果你填写了'all'并且也填写了其他需要备份的服务器数据文件夹, 本系统将默认备份整个服务器数据(整个根目录).",
			"notice-4" => "For the setting of this custom path backup, please enter the name of the folder you need to back up in the server root directory. For example, if I want to back up the worlds folder, just add the folder name 'worlds'.",
			"notice-5" => "The following 'all' refers to all the data of the backup server (the entire root directory). If you do not want to back up the complete server data, remove this option.",
			"notice-6" => "If you fill in 'all' and also fill in other server data folders that need to be backed up, the system will back up the entire server data (the entire root directory) by default.",
		]);
		if(!$this->config->exists(self::CONFIG_AUTO_BACKUP_PATH))
		{
			$this->config->set(self::CONFIG_AUTO_BACKUP_PATH, ["all", "worlds"]);
			$this->config->save();
		}
		
		
		if(!$this->server->getPluginManager()->getPlugin("TSeriesAPI"))
		{
			$this->ssm(self::NORMAL_PRE."§c服务器无法找到所依赖的插件, 将无法智能判断核心环境!");
			$this->ssm(self::NORMAL_PRE."§c本插件已卸载.");
			$this->server->getPluginManager()->disablePlugin($this);
			return null;
		}
		else $this->tsapi = $this->server->getPluginManager()->getPlugin("TSeriesAPI")->setMeEnable($this);
		
		$this->ssm(self::NORMAL_PRE."§d-----------------------------------------------------", "info", "server");
		$this->ssm(self::NORMAL_PRE."§e".self::STRING_PRE." §aEnabled §f(Internal version §dv§a".self::PLUGIN_VERSION."§f)", "info", "server");
		$this->ssm(self::NORMAL_PRE."§eAuthor: §bTeaclon§f(§e锤子§f)", "info", "server");
		// $this->ssm(self::NORMAL_PRE."§f插件主指令: §d/§6".MainCommand::MY_COMMAND."", "info", "server");
		$this->ssm(self::NORMAL_PRE."§eUsed-Time: §6".round(microtime(true) - $start,3)."§as", "info", "server");
		$this->ssm(self::NORMAL_PRE."§d-----------------------------------------------------", "info", "server");
		
		// $this->server->getPluginManager()->registerEvents($this, $this);
		// $this->tsapi->getCommandManager()->registerCommand(new MainCommand($this));
		
		$periodic = (int) $this->config->get(self::CONFIG_AUTO_BACKUP_PERIODIC);
		$periodic = ($periodic <= 0) ? 1 : $periodic;
		$this->tsapi->getTaskManager()->registerTask("scheduleRepeatingTask", new task\AutoBackupTask($this, $this->config->getAll()), 20 * 60 * 60 * 24 * $periodic, \false);
	}
	
	public function onDisable()
	{
		$this->ssm(self::NORMAL_PRE."§cPlugin disabled.", "info", "server");
	}
	
	
	
	
#------[MAIN CODES AREA]-----------------------------------------------------------------------------------------#
	public function randomString(int $length = 10, string $type)
	{
		$type_arr = ["IntOnly", "StringSmallLettersOnly", "StringBigLettersOnly", "StringMixedLettersOnly", "Mixed"];
		if(!in_array($type, $type_arr)) return "Type incorrect! You must input type like ".implode(", ", $type_arr);
		$randomString = $characters = null;
		switch($type)
		{
			case "IntOnly":
				$characters = '0123456789';
			break;
			
			case "StringSmallLettersOnly":
				$characters = 'abcdefghijklmnopqrstuvwxyz';
			break;
			
			case "StringBigLettersOnly":
				$characters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
			break;
			
			case "StringMixedLettersOnly":
				$characters = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
			break;
			
			case "Mixed":
				$characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
			break;
		}
		$charactersLength = strlen($characters);
		for($i = 0; $i < $length; $i++)
		{
			$randomString .= $characters[rand(0, $charactersLength - 1)];
		}
		return $randomString;
	}
	
	
	
	
	#_______________________________________________________________________________#
	#_______________________________________________________________________________#
	#________________________________[FTP FUNCTIONS]________________________________#
	#_______________________________________________________________________________#
	#_______________________________________________________________________________#
	
	
	
	
	private static $ftp_server = [];
	/*********************************
	  * Get a FTP SERVER and save it;
	  * var (string) randomId -> a random Id for ftp server;
	*********************************/
	public function get_ftp_server(string $randomId) : array
	{
		return isset(self::$ftp_server[$randomId]) ? self::$ftp_server[$randomId] : [];
	}
	
	/*********************************
	  * Get a FTP SERVER's RandomId;
	  * var (string) randomId -> a random Id for ftp server;
	*********************************/
	public function getFTPServerRandomId(string $address, int $port = 21) : string
	{
		$randomId = "";
		foreach(self::$ftp_server as $i => $ftpData)
		{
			if(($ftpData["address"] === $address) && ($ftpData["port"] === $port))
			{
				$randomId = $i;
				break;
			}
		}
		return $randomId;
	}
	
	/*********************************
	  * Check FTP SERVER is passive or initiactive;
	  * var (string) randomId -> a random Id for ftp server;
	*********************************/
	public function changeFTPServerConnectMode(string $randomId, bool $pasv = \true)
	{
		return isset(self::$ftp_server[$randomId]["resource"]) ? ftp_pasv(self::$ftp_server[$randomId]["resource"], $pasv) : \false;
	}
	
	/*********************************
	  * Add a FTP SERVER and save it;
	  * var (string) address   -> ftp server address or ip;
	  * var (intval) port      -> ftp server port;
	  * var (intval) timeout   -> connect time with timeout and stop to connect;
	  * var (intval) retry     -> retry connect ftp server with custom times;
	  * var (string) error_msg -> error code or custom message;
	*********************************/
	private $t200 = [];
	public function add_ftp_server(string $address, int $port = 21, int $timeout = 90, int $retry = 3, string $error_msg = "0000")
	{
		$ftp = @\ftp_connect($address, $port, $timeout);
		if($ftp !== \false)
		{
			while(\true)
			{
				$randomId = $this->randomString(7, "StringMixedLettersOnly");
				if(!isset(self::$ftp_server[$randomId]))
				{
					self::$ftp_server[$randomId] = ["resource" => $ftp, "address" => $address, "port" => $port, "systype" => "", "isLoginStatus" => \false];
					$this->ssm(self::NORMAL_PRE."[01001] §aSuccessful connected to FTP SERVER §6{$address}§e:§6{$port} §a!", "info", "server");
					$this->ssm(self::NORMAL_PRE."§aThis FTPServer-RandomId is: §e".$randomId, "info", "server");
					break;
				}
			}
			return \true;
		}
		else
		{
			if(!isset($this->t200[$address])) $this->t200[$address] = 0;
			if(($retry > 0) && ($this->t200[$address] < $retry))
			{
				$this->t200[$address]++;
				$this->ssm(self::NORMAL_PRE."[01002] §eRetry to connecting FTP SERVER §6{$address}§f:§6{$port} §e... §f(§eTRY §c".$this->t200[$address]."§d/§b{$retry}§f)", "info", "server");
				return $this->add_ftp_server($address, $port, $timeout, $retry, $error_msg);
			}
			else
			{
				unset($this->t200[$address]);
				$this->ssm(self::NORMAL_PRE."[01003] §cTried to connect FTP SERVER §6{$address}§f:§6{$port} §cwith §e{$retry} §ctimes, but some reasons the ftp server had to refuse ours requests§f(§eErrMsg§2#§b{$error_msg}§f)§c.", "error", "server");
				return \false;
			}
		}
		
	}
	
	/*********************************
	  * Close a FTP SERVER;
	  * var (string)  randomId            -> a random Id for ftp server;
	  * var (boolean) removeFTPServerData -> delete/unset a ftp server from global var ftp_server or not;
	*********************************/
	public function close_ftp_server(string $randomId, bool $removeFTPServerData = \false) : bool
	{
		if(!isset(self::$ftp_server[$randomId]) || (isset(self::$ftp_server[$randomId]) && !isset($this->get_ftp_server($randomId)["resource"])))
		{
			$this->ssm(self::NORMAL_PRE."[02003] §cInvaild ftp address and port. Please try to connect it at first and then use this method function.", "error", "server");
			return \false;
		}
		if(@\ftp_close($this->get_ftp_server($randomId)["resource"]))
		{
			if($removeFTPServerData === \true) unset(self::$ftp_server[$randomId]);
			$this->ssm(self::NORMAL_PRE."[02001] §aSuccessful to close/disconnect FTP SERVER §6{$address}§e:§6{$port} §a!", "info", "server");
			return \true;
		}
		else
		{
			$this->ssm(self::NORMAL_PRE."[01002] §aFailed to close/disconnect FTP SERVER §6{$address}§e:§6{$port} §c!", "info", "server");
			return \false;
		}
	}
	
	/*********************************
	  * Check a FTP SERVER is Login or not;
	  * var (string) randomId -> a random Id for ftp server;
	  * var (mixed)  user     -> a user for ftp server;
	*********************************/
	public function isFTPServerUserLoginActived(string $randomId) : bool
	{
		return isset(self::$ftp_server[$randomId], self::$ftp_server[$randomId]["isLoginStatus"]) ? self::$ftp_server[$randomId]["isLoginStatus"] : \false;
	}
	
	/*********************************
	  * Active Login-Status from FTP SERVER;
	  * var (string) randomId -> a random Id for ftp server;
	*********************************/
	public function activeFTPServerLoginStatus(string $randomId) : bool
	{
		if($this->get_ftp_server($randomId) && !$this->isFTPServerUserLoginActived($randomId))
		{
			self::$ftp_server[$randomId] = $this->get_ftp_server($randomId);
			self::$ftp_server[$randomId]["isLoginStatus"] = \true;
			return \true;
		}
		else
		{
			$this->ssm(self::NORMAL_PRE."[03001] §cThis user cannot add in FTPServer-RandomId §e{$randomId}§c.", "error", "server");
			return \false;
		}
	}
	
	/*********************************
	  * Cancel activation Login-Status from FTP SERVER;
	  * var (string) randomId -> a random Id for ftp server;
	*********************************/
	public function unactivationFTPServerLoginStatus(string $randomId) : bool
	{
		if($this->get_ftp_server($randomId) && $this->isFTPServerUserLoginActived($randomId))
		{
			self::$ftp_server[$randomId] = $this->get_ftp_server($randomId);
			self::$ftp_server[$randomId]["isLoginStatus"] = \false;
			return \true;
		}
		else
		{
			$this->ssm(self::NORMAL_PRE."[03002] §cThis user cannot remove in FTPServer-RandomId §e{$randomId}§c.", "error", "server");
			return \false;
		}
	}
	
	/*********************************
	  * Login a FTP SERVER;
	  * var (string) randomId -> a random Id for ftp server;
	  * var (mixed)  user     -> a user for ftp server;
	  * var (mixed)  password -> user's password;
	*********************************/
	public function login_ftp_server(string $randomId, $user, $password) : bool
	{
		if(!isset(self::$ftp_server[$randomId]) || (isset(self::$ftp_server[$randomId]) && !isset($this->get_ftp_server($randomId)["resource"])))
		{
			$this->ssm(self::NORMAL_PRE."[04004] §cInvaild ftp address and port. Please try to connect it at first and then use this method function.", "error", "server");
			return \false;
		}
		if($this->isFTPServerUserLoginActived($randomId))
		{
			$this->ssm(self::NORMAL_PRE."[04003] §cThis user has been log in this FTP SERVER.", "info", "server");
			return \true;
		}
		if(@\ftp_login($this->get_ftp_server($randomId)["resource"], $user, $password))
		{
			$this->activeFTPServerLoginStatus($randomId);
			$this->ssm(self::NORMAL_PRE."[04001] §aSuccessful login to FTPServer-RandomId §e{$randomId} §a!", "info", "server");
			$this->ssm(self::NORMAL_PRE."§aFTP SERVER current file path: §f".ftp_pwd($this->get_ftp_server($randomId)["resource"]), "info", "server");
			$this->ssm(self::NORMAL_PRE."§aFTP SERVER system type:       §f".ftp_systype($this->get_ftp_server($randomId)["resource"]), "info", "server");
			return \true;
		}
		else
		{
			$this->ssm(self::NORMAL_PRE."[04002] §aFailed login to FTP SERVER §6".$this->get_ftp_server($randomId)["address"]."§e:§6".$this->get_ftp_server($randomId)["port"]." §c! Please check user name and password!", "info", "server");
			return \false;
		}
	}
	
	/*********************************
	  * Change FTP SERVER remote file path;
	  * var (string) randomId  -> a random Id for ftp server;
	  * var (string) file_path -> the file path from ftp server;
	*********************************/
	public function changeFTPServerRemoteFilePath(string $randomId, string $file_path)
	{
		if(!isset(self::$ftp_server[$randomId]) || (isset(self::$ftp_server[$randomId]) && !isset($this->get_ftp_server($randomId)["resource"])))
		{
			$this->ssm(self::NORMAL_PRE."[05004] §cInvaild ftp address and port. Please try to connect it at first and then use this method function.", "error", "server");
			return \false;
		}
		if(!$this->isFTPServerUserLoginActived($randomId))
		{
			$this->ssm(self::NORMAL_PRE."[05003] §cYou must Login this FTPServer at first.", "error", "server");
			return \false;
		}
		try
		{
			if(\ftp_chdir($this->get_ftp_server($randomId)["resource"], $file_path))
			{
				$this->ssm(self::NORMAL_PRE."[05001] §aSuccessful to change remote FTPServer file path.", "info", "server");
				$this->ssm(self::NORMAL_PRE."§aCurrently remote FTPServer file path: §f".ftp_pwd($this->get_ftp_server($randomId)["resource"]), "info", "server");
				return \true;
			}
		}
		catch(\Exception $e)
		{
			$this->ssm(self::NORMAL_PRE."[05002] §cCannot change remote FTPServer file path. Reason: §e".$e->getMessage(), "error", "server");
			return \false;
		}
	}
	
	/*********************************
	  * Upload file to FTP SERVER;
	  * var (string)  randomId       -> a random Id for ftp server;
	  * var (string)  localFilePath  -> the file path from local;
	  * var (string)  remoteFilePath -> the file path from remote ftp server;
	  * var (special) mode           -> upload mode, need to fill in the specified FTP parameter(FTP_ASCII/FTP_BINARY);
	  * var (special) resume         -> resume, need to fill in the specified FTP parameter;
	*********************************/
	public function uploadFileToFTPServer(string $randomId, string $localFilePath, string $remoteFilePath, $mode = FTP_BINARY, $resume = 0)
	{
		if(!isset(self::$ftp_server[$randomId]) || (isset(self::$ftp_server[$randomId]) && !isset($this->get_ftp_server($randomId)["resource"])))
		{
			$this->ssm(self::NORMAL_PRE."[06005] §cInvaild ftp address and port. Please try to connect it at first and then use this method function.", "error", "server");
			return \false;
		}
		if(!$this->isFTPServerUserLoginActived($randomId))
		{
			$this->ssm(self::NORMAL_PRE."[06004] §cYou must Login this FTPServer at first.", "error", "server");
			return \false;
		}
		try
		{
			if(!\file_exists($localFilePath))
			{
				$this->ssm(self::NORMAL_PRE."[06003] §cCannot find local file §f\"§e{$localFilePath}§f\"§c! Please check file exists.", "info", "server");
				return \true;
			}
			if(\ftp_put($this->get_ftp_server($randomId)["resource"], $remoteFilePath, $localFilePath, $mode, $resume))
			{
				$this->ssm(self::NORMAL_PRE."[06001] §aSuccessful to upload file to remote FTPServer path.", "info", "server");
				return \true;
			}
		}
		catch(\Exception $e)
		{
			$this->ssm(self::NORMAL_PRE."[06002] §cCannot upload file to remote FTPServer path. Reason: §e".$e->getMessage(), "error", "server");
			return \false;
		}
	}
	
	/*********************************
	  * Get/Download file from FTP SERVER;
	  * var (string)  randomId       -> a random Id for ftp server;
	  * var (string)  localFilePath  -> the file path from local;
	  * var (string)  remoteFilePath -> the file path from remote ftp server;
	  * var (special) mode           -> upload mode, need to fill in the specified FTP parameter(FTP_ASCII/FTP_BINARY);
	  * var (special) resume         -> resume, need to fill in the specified FTP parameter;
	*********************************/
	public function getFileFromFTPServer(string $randomId, string $localFilePath, string $remoteFilePath, $mode = FTP_BINARY, $resume = 0)
	{
		if(!isset(self::$ftp_server[$randomId]) || (isset(self::$ftp_server[$randomId]) && !isset($this->get_ftp_server($randomId)["resource"])))
		{
			$this->ssm(self::NORMAL_PRE."[07004] §cInvaild ftp address and port. Please try to connect it at first and then use this method function.", "error", "server");
			return \false;
		}
		if(!$this->isFTPServerUserLoginActived($randomId))
		{
			$this->ssm(self::NORMAL_PRE."[07003] §cYou must Login this FTPServer at first.", "error", "server");
			return \false;
		}
		try
		{
			if(\file_exists($localFilePath))
			{
				// file_put_contents($localFilePath, "");
				@\rename($localFilePath, \trim($localFilePath).".".$this->randomString(5, "IntOnly").".bak");
				// $this->ssm(self::NORMAL_PRE."§cCannot find local file(s) §f\"§e{$localFilePath}§f\"§c! Please check file exists.", "info", "server");
				// return \true;
			}
			if(\ftp_get($this->get_ftp_server($randomId)["resource"], $localFilePath, $remoteFilePath, $mode, $resume))
			{
				$this->ssm(self::NORMAL_PRE."[07001] §aSuccessful download file from remote FTPServer path.", "info", "server");
				return \true;
			}
		}
		catch(\Exception $e)
		{
			$this->ssm(self::NORMAL_PRE."[07002] §cCannot download file from remote FTPServer path. Reason: §e".$e->getMessage(), "error", "server");
			return \false;
		}
	}
	
	/*********************************
	  * Create file path in a FTP SERVER;
	  * var (string) randomId -> a random Id for ftp server;
	  * var (string) dirName  -> the file path from local;
	*********************************/
	public function mkdirInFTPServer(string $randomId, string $dirName)
	{
		if(!isset(self::$ftp_server[$randomId]) || (isset(self::$ftp_server[$randomId]) && !isset($this->get_ftp_server($randomId)["resource"])))
		{
			$this->ssm(self::NORMAL_PRE."[08004] §cInvaild ftp address and port. Please try to connect it at first and then use this method function.", "error", "server");
			return \false;
		}
		if(!$this->isFTPServerUserLoginActived($randomId))
		{
			$this->ssm(self::NORMAL_PRE."[08003] §cYou must Login this FTPServer at first.", "error", "server");
			return \false;
		}
		try
		{
			if(\ftp_mkdir($this->get_ftp_server($randomId)["resource"], $dirName))
			{
				$this->ssm(self::NORMAL_PRE."[08001] §aSuccessful to create a file path in this FTPServer.", "info", "server");
				return \true;
			}
		}
		catch(\Exception $e)
		{
			$this->ssm(self::NORMAL_PRE."[08002] §cCannot create a file path in this FTPServer. Reason: §e".$e->getMessage(), "error", "server");
			return \false;
		}
	}
	
	/*********************************
	  * Delete file path in a FTP SERVER;
	  * var (string) randomId -> a random Id for ftp server;
	  * var (string) dirName  -> the file path from local;
	*********************************/
	public function rmdirInFTPServer(string $randomId, string $dirName)
	{
		if(!isset(self::$ftp_server[$randomId]) || (isset(self::$ftp_server[$randomId]) && !isset($this->get_ftp_server($randomId)["resource"])))
		{
			$this->ssm(self::NORMAL_PRE."[09004] §cInvaild ftp address and port. Please try to connect it at first and then use this method function.", "error", "server");
			return \false;
		}
		if(!$this->isFTPServerUserLoginActived($randomId))
		{
			$this->ssm(self::NORMAL_PRE."[09003] §cYou must Login this FTPServer at first.", "error", "server");
			return \false;
		}
		try
		{
			if(\ftp_rmdir($this->get_ftp_server($randomId)["resource"], $dirName))
			{
				$this->ssm(self::NORMAL_PRE."[09001] §aSuccessful to delete a file path in this FTPServer.", "info", "server");
				return \true;
			}
		}
		catch(\Exception $e)
		{
			$this->ssm(self::NORMAL_PRE."[09002] §cCannot delete a file path in this FTPServer. Reason: §e".$e->getMessage(), "error", "server");
			return \false;
		}
	}
	
	/*********************************
	  * Delete file path in a FTP SERVER;
	  * var (string) randomId -> a random Id for ftp server;
	  * var (string) file     -> the file name which need to delete in remote ftp server;
	*********************************/
	public function deleteFileInFTPServer(string $randomId, string $file)
	{
		if(!isset(self::$ftp_server[$randomId]) || (isset(self::$ftp_server[$randomId]) && !isset($this->get_ftp_server($randomId)["resource"])))
		{
			$this->ssm(self::NORMAL_PRE."[10004] §cInvaild ftp address and port. Please try to connect it at first and then use this method function.", "error", "server");
			return \false;
		}
		if(!$this->isFTPServerUserLoginActived($randomId))
		{
			$this->ssm(self::NORMAL_PRE."[10003] §cYou must Login this FTPServer at first.", "error", "server");
			return \false;
		}
		try
		{
			if(\ftp_delete($this->get_ftp_server($randomId)["resource"], $file))
			{
				$this->ssm(self::NORMAL_PRE."[10001] §aSuccessful to delete a file in this FTPServer.", "info", "server");
				return \true;
			}
		}
		catch(\Exception $e)
		{
			$this->ssm(self::NORMAL_PRE."[10002] §cCannot delete a file in this FTPServer. Reason: §e".$e->getMessage(), "error", "server");
			return \false;
		}
	}
	
	/*********************************
	  * Check file/dirName's least modify date in a FTP SERVER;
	  * var (string) randomId     -> a random Id for ftp server;
	  * var (string) file/dirName -> which you need to check;
	*********************************/
	public function checkResourceLeastModifyDateInFTPServer(string $randomId, string $resource)
	{
		if(!isset(self::$ftp_server[$randomId]) || (isset(self::$ftp_server[$randomId]) && !isset($this->get_ftp_server($randomId)["resource"])))
		{
			$this->ssm(self::NORMAL_PRE."[11004] §cInvaild ftp address and port. Please try to connect it at first and then use this method function.", "error", "server");
			return \false;
		}
		if(!$this->isFTPServerUserLoginActived($randomId))
		{
			$this->ssm(self::NORMAL_PRE."[11003] §cYou must Login this FTPServer at first.", "error", "server");
			return \false;
		}
		try
		{
			$S235_N = ftp_mdtm($this->get_ftp_server($randomId)["resource"], $resource);
			$this->ssm(self::NORMAL_PRE. "[11001] Info: ".date(DATE_RFC822, $S235_N), "error", "server");
			return \true;
		}
		catch(\Exception $e)
		{
			$this->ssm(self::NORMAL_PRE."[11002] §cCannot delete a file in this FTPServer. Reason: §e".$e->getMessage(), "error", "server");
			return \false;
		}
	}
	
	
	
	
	
	
	
	
	public function getFileSize(string $file_path, string $format_s = "mb", bool $format = \false)
	{
		if(is_file($file_path)) $size = filesize($file_path);
		$size /= pow(1024, (($format_s === "kb") ? 1 : (($format_s === "mb") ? 2 : (($format_s === "gb") ? 3 : 0))));
		return ($format) ? number_format($size, 3) : $size;
	}
	
	
#------[MAIN CODES AREA END]-------------------------------------------------------------------------------------#
	
	
	
	
	/**
		用法: self::ssm(信息, 日志记录等级, 发送形式)
	**/
	public final function ssm($msg, $level = "info", $type = "logger")
	{
		if(($msg === "") || ($level === "") || ($type === ""))
		{
			Server::getInstance()->getLogger()->error(self::NORMAL_PRE."[LOGGER] Error Usage(0010)");
		}
		elseif(!\in_array($level, ["info", "warning", "error", "notice", "debug", "alert", "critical", "emergency"]))
		{
			Server::getInstance()->getLogger()->error(self::NORMAL_PRE."[LOGGER] Error Usage(0015)");
		}
		elseif(!\in_array($type, ["server", "logger"]))
		{
			Server::getInstance()->getLogger()->error(self::NORMAL_PRE."[LOGGER] Error Usage(0020)");
		}
		else
		{
			$color = ($level === "notice") ? "§r§b" : null;
			if($type === "server") Server::getInstance()->getLogger()->$level($color.$msg);
			elseif($type === "logger") $this->getLogger()->$level($color.$msg);
		}
	}
	
	public static final function stopThread($plugin_name, $msg, $error_code = "")
	{
		Server::getInstance()->getLogger()->error("§c§l服务器已崩溃, 正在关闭服务器.");
		Server::getInstance()->getLogger()->error("§c§l服务器已崩溃, 正在关闭服务器.");
		Server::getInstance()->forceshutdown();
		if($error_code === "") $error_code = "NULL";
		exit("ERROR: >> Plugin: {$plugin_name}; Cause: {$msg}; Code: {$error_code}".PHP_EOL);
	}
	
	public static final function getInstance()
	{
		return self::$instance;
	}
	
	public final function getTSApi() : \Teaclon\TSeriesAPI\Main
	{
		return $this->tsapi;
	}
	
	public final function config() : Config
	{
		return $this->config;
	}
	
	
	
	
	
	
	
}
?>