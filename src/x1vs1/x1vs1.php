<?php

namespace x1vs1;

use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\utils\Config;
use pocketmine\Player;
use pocketmine\Server;
use pocketmine\event\Listener;
use pocketmine\plugin\PluginBase;
use pocketmine\event\entity\{EntityDamageEvent, EntityDamageByEntityEvent, EntityTeleportEvent};
use pocketmine\level\Position;
use pocketmine\level\Location;
use pocketmine\event\player\{PlayerQuitEvent, PlayerDeathEvent, PlayerJoinEvent, PlayerChatEvent};
use x1vs1\Arena;
use pocketmine\item\Item;

class x1vs1 extends PluginBase implements Listener{

	public $config = [];
	public $game = [];
	public $wait = [];
	public $queue = [];
	public $arenas = [];
	public $status = false;

	public function onEnable(){
		@mkdir($this->getDataFolder());
		$this->saveDefaultConfig();
		$this->getServer()->getPluginManager()->registerEvents($this, $this);
		$this->config = new Config($this->getDataFolder()."config.yml", Config::YAML, array());
		$this->orcore = $this->getServer()->getPluginManager()->getPlugin("xORCore");
        if($this->orcore == null){
            $this->getLogger()->error("§cPlease install §bxORCore");
            $this->setEnabled(false);
            return false;
        }

		$this->getLogger()->info("§bEverything loaded!");
	}

	public function getORCore(){
		return $this->orcore;
	}

	public function onDisable(){
		unset($this->game);
		unset($this->config);
		unset($this->wait);

	}
	// API

	public function loadLevel(){
		$arenaPositions = $this->config->get('arenas');
		foreach ($arenaPositions as $n => $arenaPosition){
			$this->getServer()->loadLevel($arenaPosition[3]);
			$level = $this->getServer()->getLevelByName($arenaPosition[3]);
			if($level === null){
				$this->getServer()->getLogger()->error("[1vs1] - " . $arenaPosition[3] . " is not loaded. Arena " . $n . " is disabled.");
			}else{
				$newArenaPosition = new Position($arenaPosition[0], $arenaPosition[1], $arenaPosition[2], $level);
                $newArena = new Arena($newArenaPosition, $this);
				array_push($this->arenas, $newArena);
				$this->getServer()->getLogger()->debug("[1vs1] - Arena " . $n . " loaded at position " . $newArenaPosition->__toString());
			}
		}
	}

	public function addWait(Player $wait, Player $accept){
		$this->wait[$wait->getName()] = [$wait->getName(), $accept->getName()];
	}

	public function hasWait(Player $accept, Player $wait){
		if(isset($this->wait[$wait->getName()])){
			$check = array_diff($this->wait[$wait->getName()], [$wait->getName(), $accept->getName()]);
			if($check == null){
				$ans = true;
			}
			return $ans;
		}
		return false;
	}

	public function checkPlayerInQueue(Player $player){
		$name = $player->getName();
		$found = false;
		foreach($this->queue as $key => $value){
			if(($value[0]->getName() == $name) || ($value[1]->getName() == $name)){
				$found = true;
				break;
			}
		}
		return $found;
	}

	public function addPlayerToQueue(array $player){
		array_push($this->queue, $player);
		$player[0]->sendMessage('§aพวกคุณกำลังเข้าคิว');
		$player[1]->sendMessage('§aพวกคุณกำลังเข้าคิว');
		$this->launchNewRounds();
	}

	public function launchNewRounds(){
		if(count($this->queue) < 1){
			return;
		}
		$freeArena = NULL;
		foreach($this->arenas as $arena){
			if(!$arena->active){
				$freeArena = $arena;
				break;
			}
		}
		
		if($freeArena == NULL){
			$this->getServer()->getLogger()->debug("[1vs1] - No free arena found");
			return;
		}
		$roundPlayers = [];
		$num = array_shift($this->queue);
		array_push($roundPlayers, $num[0], $num[1]);
		$freeArena->startRound($roundPlayers);
	}

	public function referenceNewArena(Location $location){
		// Create a new arena
		$newArena = new Arena($location, $this);	
		
		// Add it to the array
		array_push($this->arenas, $newArena);
		
		// Save it to config
		$arenas = $this->config->arenas;
		array_push($arenas, [$newArena->position->getX(), $newArena->position->getY(), $newArena->position->getZ(), $newArena->position->getLevel()->getName()]);
		$this->config->set("arenas", $arenas);
		$this->config->save();		
	}

	public function removePlayerFromQueueOrArena(Player $player){
		$currentArena = $this->getPlayerArena($player);
		if($currentArena != null){
			$currentArena->onPlayerDeath($player);
			return;
		}

		$index = array_search($player, $this->queue);
		if($index != -1){
			unset($this->queue[$index]);
		}
	}

	public function getNumberOfArenas(){
		return count($this->arenas);
	}

	public function getNumberOfFreeArenas(){
		$numberOfFreeArenas = count($this->arenas);
		foreach ($this->arenas as $arena){
			if($arena->active){
				$numberOfFreeArenas--;
			}
		}
		return $numberOfFreeArenas;
	}

	public function getNumberOfPlayersInQueue(){
		return count($this->queue);
	}

	public function getPlayerArena(Player $player){
		foreach ($this->arenas as $arena) {
			if($arena->isPlayerInArena($player)){
				return $arena;
			}
		}	
		return NULL;	
	}

	public function notifyEndOfRound(Arena $arena){
		$this->launchNewRounds();
	}

    /******************
     ******************
     ******************
     ****
     ****
     ****
     *********
     *********
     *********
     ****
     ****
     ******************
     ******************
     ****************** event
    */

    public function onEntityDamage(EntityDamageEvent $event){
    	if($event instanceof EntityDamageByEntityEvent){
            $entity = $event->getEntity();
            $damage = $event->getDamager();
    		if($entity instanceof Player && $damage instanceof Player){
    			if($entity->getLevel()->getFolderName() == 'Hub'){
    				if($damage->getInventory()->getItemInHand()->getId() == 369){
        				if($this->status == false){
        					$this->status = true;
    						$this->loadLevel();
	    				}
	    				if($this->checkPlayerInQueue($damage)){
	    					$damage->sendMessage('§aคู่ของคุณกำลังรอคิวอยู่');
	    					$event->setCancelled(true);
	    					return;
	    				}
	    				if(isset($this->wait[$damage->getName()]) && isset($this->wait[$entity->getName()])){
	    					if(($this->wait[$damage->getName()][1] == $entity->getName()) && $this->wait[$entity->getName()][1] == $damage->getName()){
	    						$event->setCancelled(true);
	    						$damage->sendMessage('§aคู่ของคุณกำลังรอคิวอยู่');
	    						return;
	    					}
	    				}

	    				if($this->hasWait($damage, $entity)){
	    					$damage->sendMessage('§aคุณตอบรับคำท้าแล้ว');
	    					$entity->sendMessage('§aฝ่ายตรงข้ามตอบรับคำท้า');
	    					$this->addWait($damage, $entity);
	    					$this->addPlayerToQueue([$damage, $entity]);
	    					$event->setCancelled(true);
	    					return;
	    				}else{
	    					$this->addWait($damage, $entity);
	    					// $this->addWait($entity, $damage);
	    					$damage->sendMessage('§eรอฝ่ายตรงข้ามยอมรับ');
	    					$entity->sendMessage('§b'.$damage->getName(). ' §aได้ท้าคุณ');
	    					$event->setCancelled(true);
	    					return;
	    				}
	    				$event->setCancelled(true);
    				}
    			}
    		}
    	}
    }

	public function onCommand(CommandSender $sender, Command $command, $label, array $args){
		switch($command->getName()){
			case "arenaset":
				if($sender instanceof Player){
					if($sender->hasPermission("1vs1.arenaset")){
						$playerLocation = $sender->getLocation();
						$this->referenceNewArena($playerLocation);
						$sender->sendMessage('มี arena '. $this->getNumberOfArenas() .' arenas.');
						return true;
					}else{
						$sender->sendMessage('ไม่อนุญาตให้ใช้คำสั่งนี้');
						return false;
					}
				}else{
					$sender->sendMessage('ใช้คำสั่งในเกม');
					return false;
				}
				break;
		}
		return false;
	}

	public function onPlayerDeath(PlayerDeathEvent $event){
		$deadPlayer = $event->getEntity();
		$arena = $this->getPlayerArena($deadPlayer);
		if($arena != NULL){
			$event->setDrops([]);
			$event->setKeepInventory(false);
			$arena->onPlayerDeath($deadPlayer);
		}
	}

	public function onPlayerQuit(PlayerQuitEvent $event){
		$player = $event->getPlayer();
		$this->removePlayerFromQueueOrArena($player);
	}

	public function onJoin(PlayerJoinEvent $event){
		$player = $event->getPlayer();
		$player->getInventory()->addItem(Item::get(369));
	}

	public function onChat(PlayerChatEvent $event){
		$player = $event->getPlayer();
		$arena = $this->getPlayerArena($player);
		$event->setCancelled(true);
		if($arena !== null){
			$players = $arena->players;
			foreach($players as $pl){
				$pl->sendMessage($this->getORCore()->setChat($event->getPlayer(), $event->getMessage()));
			}
		}
	}

	// public function onTeleport(EntityTeleportEvent $event){
	// 	$player = $event->getEntity();
	// 	if($event->getTo()->getLevel()->getFolderName() == 'Hub'){
	// 		$player->getInventory()->addItem(Item::get(369));
	// 	}
	// }
}
