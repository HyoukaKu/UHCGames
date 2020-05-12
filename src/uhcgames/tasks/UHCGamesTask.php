<?php
declare(strict_types=1);

namespace uhcgames\tasks;

use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\item\ItemFactory;
use pocketmine\item\ItemIds;
use pocketmine\math\Vector3;
use pocketmine\player\GameMode;
use pocketmine\player\Player;
use pocketmine\scheduler\Task;
use pocketmine\Server;
use pocketmine\utils\TextFormat;
use uhcgames\Loader;

class UHCGamesTask extends Task{
	/** @var int */
	public const WAITING = 0;
	/** @var int */
	public const COUNTDOWN = 1;
	/** @var int */
	public const MATCH = 2;
	/** @var int */
	public const MEETUP = 3;
	/** @var int */
	private const BORDER_READY = 4;

	/** @var int */
	private $countdown = 60;
	/** @var int */
	private $meetupTimer = 30;
	/** @var int */
	private $shutdownTimer = 5;

	/** @var int */
	private $border = 0; //TODO: Config

	/** @var Loader */
	private $plugin;
	/** @var Server */
	private $server;

	public function __construct(Loader $plugin){
		$this->plugin = $plugin;
		$this->server = $plugin->getServer();
		$this->border = $plugin->getConfig()->get($plugin->map->getFolderName())["border"];
	}

	public function onRun(int $currentTick){
		switch($this->plugin->gameStatus){
			case self::WAITING:
				if(count($this->plugin->gamePlayers) >= 2){
					$this->plugin->gameStatus = self::COUNTDOWN;
				}
				break;
			case self::COUNTDOWN:
				$this->handleCountdown();
				break;
			case self::MATCH:
				if(count($this->plugin->gamePlayers) <= 4){
					$this->plugin->gameStatus = self::MEETUP;
				}
				break;
			case self::MEETUP:
				$this->handleMeetup();
				break;
		}

		if($this->plugin->gameStatus >= self::MATCH){
			foreach($this->plugin->gamePlayers as $player){
				$player->setImmobile(false);
			}
			$this->onWin();
		}

		foreach($this->plugin->gamePlayers as $player){
			$this->handleBorder($player);
			$player->setScoreTag(floor($player->getHealth() / 2) . TextFormat::RED . "❤");
		}
	}

	private function handleCountdown(){
		if(count($this->plugin->gamePlayers) <= 1){
			$this->plugin->gameStatus = self::WAITING;
			$this->countdown = 61;
		}

		switch($this->countdown){
			case 60:
				$this->server->broadcastMessage(Loader::PREFIX . "Game is starting in 1 minute.");
				break;
			case 30:
				$this->server->broadcastMessage(Loader::PREFIX . "Game is starting in $this->countdown seconds.");
				break;
			case 10:
				$this->server->broadcastMessage(Loader::PREFIX . "Game is starting in $this->countdown seconds.");
				break;
			case 5:
			case 4:
			case 3:
			case 2:
			case 1:
				$this->server->broadcastMessage(Loader::PREFIX . "Game is starting in $this->countdown second(s).");
				break;
			case 0:
				$this->plugin->gameStatus = self::MATCH;

				foreach($this->plugin->gamePlayers as $player){
					$player->setHealth($player->getMaxHealth());
					$player->getHungerManager()->setFood($player->getHungerManager()->getMaxFood());

					$armorInventory = $player->getArmorInventory();
					$inventory = $player->getInventory();
					$inventory->addItem(ItemFactory::get(ItemIds::STONE_SWORD));
					$armorInventory->setHelmet(ItemFactory::get(ItemIds::IRON_HELMET));
					$armorInventory->setChestplate(ItemFactory::get(ItemIds::IRON_CHESTPLATE));
					$armorInventory->setLeggings(ItemFactory::get(ItemIds::IRON_LEGGINGS));
					$armorInventory->setBoots(ItemFactory::get(ItemIds::IRON_BOOTS));

					$player->setImmobile(false);
				}
				break;
		}
		$this->countdown--;
	}

	private function handleMeetup(){
		switch($this->meetupTimer){
			case 30:
				$this->server->broadcastMessage(Loader::PREFIX . "Meetup is starting in $this->meetupTimer seconds.");
				break;
			case 10:
				$this->server->broadcastMessage(Loader::PREFIX . "Meetup is starting in $this->meetupTimer seconds.");
				break;
			case 5:
			case 4:
			case 3:
			case 2:
			case 1:
				$this->server->broadcastMessage(Loader::PREFIX . "Meetup is starting in $this->meetupTimer second(s).");
				break;
			case 0:
				$spawns = $this->plugin->getConfig()->get($this->plugin->map->getFolderName())["meetup-spawns"];
				shuffle($spawns);
				foreach($this->plugin->gamePlayers as $player){
					$locations = array_shift($spawns);
					if(isset($locations)){
						$player->teleport(new Vector3($locations[0], $locations[1], $locations[2]));
					}
				}

				$this->server->broadcastMessage(Loader::PREFIX . "Border shrunk to $this->border! Walking into it will cause you to take damage!");
				$this->plugin->gameStatus = self::BORDER_READY;
				break;
		}
		$this->meetupTimer--;
	}

	private function onWin(){
		if(count($this->plugin->gamePlayers) > 1) return;

		if($this->shutdownTimer === 0){
			foreach($this->server->getOnlinePlayers() as $p){
				$config = $this->plugin->getConfig();
				$p->transfer((string) $config->get("server-ip"), (int) $config->get("server-port"));
			}
			$this->plugin->getServer()->shutdown();
		}elseif($this->shutdownTimer === 5){
			if(count($this->plugin->gamePlayers) === 1){
				foreach($this->plugin->gamePlayers as $player){
					$player->setGamemode(GameMode::CREATIVE());
					$this->server->broadcastMessage(Loader::PREFIX . $player->getName() . " won the game!");
				}
			}else{
				$this->server->broadcastMessage(Loader::PREFIX . "No one won the game.");
			}
		}
		$this->shutdownTimer--;
	}

	private function handleBorder(Player $p){
		$spawn = $this->plugin->map->getSpawnLocation();
		if($this->plugin->gameStatus === self::BORDER_READY){
			if((
				 $p->getPosition()->getX() > $spawn->getX() + $this->border
				 || $p->getPosition()->getX() < $spawn->getX() - $this->border ||
				$p->getPosition()->getZ() > $spawn->getZ() + $this->border 
				|| $p->getPosition()->getZ() < $spawn->getZ() - $this->border
			)){
				$p->attack(new EntityDamageEvent($p, EntityDamageEvent::CAUSE_CUSTOM, 2));
				$p->sendTip("You are outside border, get back inside!");
			}
		}
	}
}
