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

namespace Teaclon\ServerAutoBackuper\command;

use Teaclon\ServerAutoBackuper\Main;

use pocketmine\Player;
use pocketmine\utils\Config;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;

use Teaclon\TSeriesAPI\command\subcommand\BaseCommand;
use Teaclon\TSeriesAPI\command\CommandManager;


class MainCommand extends BaseCommand
{
	const MY_COMMAND             = "backupsys";
	const MY_COMMAND_PEREMISSION = [self::PERMISSION_CONSOLE];
	public $myprefix = Main::NORMAL_PRE;
	private $tsapi = null;
	
	
	
	
	
	public function __construct(Main $plugin)
	{
		$this->tsapi = $plugin->getTSApi();
		// CommandName, Description, usage, aliases, overloads;
		$this->init($plugin, self::MY_COMMAND, Main::STRING_PRE."的主指令", null, [], []);
	}
	
	
	
	public function execute(CommandSender $sender, $commandLabel, array $args)
	{
		$senderName = strtolower($sender->getName());
		if(!isset($args[0]))
		{
			$this->sendMessage($sender, "§e--------------§b".Main::STRING_PRE."指令助手§e--------------");
			foreach(self::getHelpMessage() as $cmd => $message)
			{
				if($this->hasSenderPermission($sender, $cmd))
					$this->sendMessage($sender, str_replace("{cmd}", self::MY_COMMAND, $message));
				else continue;
			}
			$this->sendMessage($sender, "§e---------------------------------------------------");
			return true;
		}
		
		switch($args[0])
		{
			default:
			case "help":
			case "帮助":
				$this->execute($sender, $commandLabel, []);
				return true;
			break;
			
			
			case "reload":
				if(!$this->hasSenderPermission($sender, $args[0]))
				{
					$this->sendMessage($sender, "§c你没有权限使用这个指令.");
					return true;
				}
				$this->plugin->config()->reload();
				$this->sendMessage($sender, "§a已重载配置文件.");
				return true;
			break;
			
			
		}
	}
	
	
	public static function getCommandPermission(string $cmd)
	{
		$cmds = 
		[
			// "ftp-ulist"       => [self::PERMISSION_CONSOLE],
			// "ftp-olist"       => [self::PERMISSION_CONSOLE],
			// "ftp-delete"      => [self::PERMISSION_CONSOLE],
			// "ftp-connect"     => [self::PERMISSION_CONSOLE],
			// "ftp-login"       => [self::PERMISSION_CONSOLE],
			// "ftp-disconnect"  => [self::PERMISSION_CONSOLE],
			// "ftp-systype"     => [self::PERMISSION_CONSOLE],
			"reload" => [self::PERMISSION_CONSOLE],
		];
		
		$cmd = strtolower($cmd);
		return isset($cmds[$cmd]) ? $cmds[$cmd] : "admin";
	}
	
	public static function getHelpMessage() : array
	{
		return 
		[
			// "ftp-ulist"       => "用法: §d/§6{cmd} ftp-ulist                    §f查找所有被加载的FTP服务器",
			// "ftp-olist"       => "用法: §d/§6{cmd} ftp-olist                    §f查找所有未被加载的FTP服务器",
			// "ftp-delete"      => "用法: §d/§6{cmd} ftp-delete <randomId>        §f从列表中删除一个已/未加载的FTP服务器(如果服务器为登录状态, 将将所有已登录用户强制登出)",
			// "ftp-connect"     => "用法: §d/§6{cmd} ftp-connect <randomId>       §f连接一个FTP服务器",
			// "ftp-login"       => "用法: §d/§6{cmd} ftp-login <randomId>         §f登录至一个FTP服务器",
			// "ftp-disconnect"  => "用法: §d/§6{cmd} ftp-disconnect <randomId>    §f关闭一个FTP服务器的连接",
			"reload" => "用法: §d/§6{cmd} reload       §f重载配置文件",
		];
	}
	
	
}
?>