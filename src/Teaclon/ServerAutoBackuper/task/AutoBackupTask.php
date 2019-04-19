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

namespace Teaclon\ServerAutoBackuper\task;

use pocketmine\Player;
use pocketmine\Server;
use Teaclon\TSeriesAPI\task\PluginTask;

use Teaclon\ServerAutoBackuper\Main;

class AutoBackupTask extends PluginTask
{
	private $plugin;
	private $config;
	private $backup_path;
	
	public function __construct(\Teaclon\ServerAutoBackuper\Main $plugin, array $config)
	{
		$this->plugin = $plugin;
		$this->config = $config;
		$this->backup_path = $plugin->getServer()->getDataPath()."ServerAutoBackuper".DIRECTORY_SEPARATOR;
		parent::__construct($plugin);
	}
	
	
	
	public function me($tick)
	{
		if(!method_exists($this->plugin, "getTSApi"))
		{
			$this->plugin->ssm(Main::NORMAL_PRE."§cThis server didn't install TSeriesAPI, cannot work without TSAPI!", "info", "server");
			$this->plugin->getServer()->getPluginManager()->disablePlugin($this);
			return null;
		}
		if($this->config[Main::CONFIG_AUTO_BACKUP_MODE] !== \true)
		{
			// $this->plugin->ssm(Main::NORMAL_PRE."§c未开启自动备份功能, 已跳过后续步骤.", "info", "server");
			$this->plugin->ssm(Main::NORMAL_PRE."§cAuto-Backup is disabled. Skip the next steps.", "info", "server");
			return \false;
		}
		
		if(!is_dir($this->backup_path)) mkdir($this->backup_path, 0600, \true);
		$backup_name = date("Y_m_d").".zip";
		if(!@extension_loaded("zip"))
		{
			// $this->plugin->ssm(Main::NORMAL_PRE."§c请联系本服务器最高管理员, 本服务器的PHP环境未安装zip拓展, 无法备份服务器数据.", "info", "server");
			$this->plugin->ssm(Main::NORMAL_PRE."§cPHP binary without extension \"zip\", cannot backup server data.", "info", "server");
			return \null;
		}
		else
		{
			if(!file_exists($this->backup_path.$backup_name))
			{
				// $this->plugin->ssm(Main::NORMAL_PRE."[0001] §e即将开始备份今日的服务器数据...", "info", "server");
				$this->plugin->ssm(Main::NORMAL_PRE."[0001] §eStarting to backup data from today...", "info", "server");
				$zip = new \ZipArchive();
				if($zip->open($this->backup_path.$backup_name, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) === \true)
				{
					$server_root_path = $this->backup_path."..";
					if(in_array("all", $this->config[Main::CONFIG_AUTO_BACKUP_PATH])) $this->addFileToZip($server_root_path, $zip);
					else
					{
						foreach($this->config[Main::CONFIG_AUTO_BACKUP_PATH] as $need_backup_path)
						{
							$t = $server_root_path. DIRECTORY_SEPARATOR .$need_backup_path. DIRECTORY_SEPARATOR;
							if(is_dir($t))
							{
								$this->addFileToZip($t, $zip);
								// $this->plugin->ssm(Main::NORMAL_PRE."[0003-1] §e已添加自定义备份路径 §f\"§e{$t}§f\" 至备份进程中.", "info", "server");
								$this->plugin->ssm(Main::NORMAL_PRE."[0003-1] §eAdded custom backup path §f\"§e{$t}§f\" to current-thread.", "info", "server");
							}
						}
					}
					$zip->close();
					// $this->plugin->ssm(Main::NORMAL_PRE."[0002] §a今日的服务器数据已经备份至路径 §f\"§e".$this->backup_path.$backup_name."§f\" §a下!", "info", "server");
					$this->plugin->ssm(Main::NORMAL_PRE."[0002] §aFinished backup from today §f\"§e".$this->backup_path.$backup_name."§f\" §a!", "info", "server");
					if($this->plugin->getFileSize($this->backup_path.$backup_name, "mb") >= 2048)
					{
						// $this->plugin->ssm(Main::NORMAL_PRE."[0003] §c服务器备份数据已经超过§e2048MB§c, 已停止自动上传!", "info", "server");
						$this->plugin->ssm(Main::NORMAL_PRE."[0003] §cBackup data is bigger than §e2048MB§c, stopped auto upload to ftp-server!", "info", "server");
						return true;
					}
					// $this->plugin->ssm(Main::NORMAL_PRE."[0003] §e准备上传至目标FTP服务器...", "info", "server");
					$this->plugin->ssm(Main::NORMAL_PRE."[0003] §eStarting upload to ftp-server...", "info", "server");
					if($this->plugin->add_ftp_server($this->config[Main::CONFIG_FTP_ADDRESS], $this->config[Main::CONFIG_FTP_PORT], 30, $this->config[Main::CONFIG_RETRY_TIME], "0003"))
					{
						$FTP_SERVER_RANDOMID = $this->plugin->getFTPServerRandomId($this->config[Main::CONFIG_FTP_ADDRESS], $this->config[Main::CONFIG_FTP_PORT]);
						if($this->plugin->login_ftp_server($FTP_SERVER_RANDOMID, $this->config[Main::CONFIG_FTP_USER], $this->config[Main::CONFIG_FTP_PASSWORD]))
						{
							$this->plugin->mkdirInFTPServer($FTP_SERVER_RANDOMID, "ServerAutoBackuper/");
							
							if($this->plugin->changeFTPServerRemoteFilePath($FTP_SERVER_RANDOMID, "ServerAutoBackuper/") && $this->plugin->mkdirInFTPServer($FTP_SERVER_RANDOMID, date("Y_m_d")) && $this->plugin->changeFTPServerRemoteFilePath($FTP_SERVER_RANDOMID, date("Y_m_d")))
							{
								if($this->plugin->uploadFileToFTPServer($FTP_SERVER_RANDOMID, $this->backup_path.$backup_name, $backup_name))
								{
									// $this->plugin->ssm(Main::NORMAL_PRE."[0004] §a已将本地备份数据上传至远端FTP服务器!", "info", "server");
									$this->plugin->ssm(Main::NORMAL_PRE."[0004] §aSuccessful upload local-backup-data to ftp-server!", "info", "server");
								}
							}
						}
					}
					return true;
				}
				else
				{
					// $this->plugin->ssm(Main::NORMAL_PRE."§c出现未知错误.", "info", "server");
					$this->plugin->ssm(Main::NORMAL_PRE."§cInterval error.", "info", "server");
					return true;
				}
			}
		}
		
	}
	
	
	private function addFileToZip($path, \ZipArchive $zip)
	{
		$handler = opendir($path);
		while($filename = readdir($handler))
		{
			if(($filename === ".") || ($filename === "..")) continue;
			if(is_dir($path. DIRECTORY_SEPARATOR .$filename))
			{
				$this->addFileToZip($path. DIRECTORY_SEPARATOR .$filename, $zip);
			}
			else
			{
				$d = dirname(dirname(dirname(dirname(dirname(dirname(__DIR__)))))). DIRECTORY_SEPARATOR ."ServerAutoBackuper". DIRECTORY_SEPARATOR ."..". DIRECTORY_SEPARATOR;
				$d = str_replace($d, "", $path. DIRECTORY_SEPARATOR .basename($filename));
				$zip->addFile($path. DIRECTORY_SEPARATOR .basename($filename), $d);
			}
		}
		@closedir($handler);
		return \true;
	}
	
	public function updateConfig(array $config) : void
	{
		if((count($config) > 0) && (($config !== $this->config) || (count($this->config) === 0))) $this->config = $config;
	}
}
?>