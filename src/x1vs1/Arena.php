<?php
namespace x1vs1;
use pocketmine\Player;
use pocketmine\level\Position;
use pocketmine\item\Item;
use pocketmine\utils\TextFormat;
use pocketmine\math\Vector3;
use \DateTime;
use x1vs1\x1vs1;
class Arena{
	public $active = FALSE;
	
	public $startTime;
	
	public $players = [];
	
	/** @var Position */
	public $position;
	
	/** @var ArenaManager */
	private $plugin;

	
	// Roound duration (3min)
	const ROUND_DURATION = 180;
	
	const PLAYER_1_OFFSET_X = 5;
	const PLAYER_2_OFFSET_X = -5;
	
	// Variable for stop the round's timer
	private $taskHandler;
	private $countdownTaskHandler;
	/**
	 * Build a new Arena
	 * @param Position position Base position of the Arena
	 */
	public function __construct($position, x1vs1 $plugin){
		$this->position = $position;
		$this->plugin = $plugin;
		$this->active = FALSE;
	}
	
	/** 
	 * Demarre un match.
	 * @param Player[] $players
	 */
	public function startRound(array $players){
		
		// Set active to prevent new players
		$this->active = TRUE;
		
		// Set players
		$this->players = $players;
		$player1 = $players[0];
		$player2 = $players[1];

		$pos_player1 = Position::fromObject($this->position, $this->position->getLevel());
		$pos_player1->x += self::PLAYER_1_OFFSET_X;
		
		$pos_player2 = Position::fromObject($this->position, $this->position->getLevel());
		$pos_player2->x += self::PLAYER_2_OFFSET_X;
		$player1->teleport($pos_player1, 90, 0);
		$player2->teleport($pos_player2, -90, 0);
		if(!$player1->isImmobile()){
			$player1->setImmobile(true);
		}
		if(!$player2->isImmobile()){
			$player2->setImmobile(true);
		}
		
		$player1->sendMessage('สู้กับ '. $player2->getName());
		$player2->sendMessage('สู้กับ '. $player1->getName());
		// Create a new countdowntask
		$task = new CountDownToDuelTask($this->plugin, $this);
		$this->countdownTaskHandler = $this->plugin->getServer()->getScheduler()->scheduleDelayedRepeatingTask($task, 20, 20);
	}
	
	/**
	 * Really starts the duel after countdown
	 */
	public function startDuel(){
		
		$this->plugin->getServer()->getScheduler()->cancelTask($this->countdownTaskHandler->getTaskId());
		
		$player1 = $this->players[0];
		$player2 = $this->players[1];
		if($player1->isImmobile()){
			$player1->setImmobile(false);
		}
		if($player2->isImmobile()){
			$player2->setImmobile(false);
		}

		// Fix start time
		$this->startTime = new DateTime('now');
		
		$player1->sendTip('เริ่มได้');
		$player1->sendMessage('เริ่มได้');
		
		$player2->sendTip('เริ่มได้');
		$player2->sendMessage('เริ่มได้');

		unset($this->plugin->wait[$player1->getName()]);
		unset($this->plugin->wait[$player2->getName()]);
		
		// Launch the end round task
		$task = new RoundCheckTask($this->plugin);
		$task->arena = $this;
		$this->taskHandler = $this->plugin->getServer()->getScheduler()->scheduleDelayedTask($task, self::ROUND_DURATION * 20);
	}
	
	/**
	 * Abort duel during countdown if one of the players has quit
	 */
	public function abortDuel(){
		$this->plugin->getServer()->getScheduler()->cancelTask($this->countdownTaskHandler->getTaskId());
	}
   
   /**
    * When a player was killed
    * @param Player $loser
    */
   public function onPlayerDeath(Player $loser){
   	
		// Finish the duel and teleport the winner at spawn
   		if($loser == $this->players[0]){
   			$winner = $this->players[1];
   		}else{
   			$winner = $this->players[0];
   		}  		
   		$loser->sendMessage('คุณแพ้ '. $winner->getName());
   		$loser->removeAllEffects();
   		
   		$winner->sendMessage('คุณชนะ '. $loser->getName());
   		$winner->removeAllEffects();
   		
   		// Teleport the winner at spawn
   		$winner->teleport($winner->getSpawn());
   		// Set his life to 20
   		$winner->setHealth(20);
   		$winner->getInventory()->addItem(Item::get(369));
   		$this->plugin->getServer()->broadcastMessage($winner->getName() .' ชนะ '. $loser->getName() ." !");
   		
   		// Reset arena
   		$this->reset();
   }
   /**
    * Reset the Arena to current state
    */
   private function reset(){
   		// Put active a rena after the duel
   		$this->active = FALSE;
   		foreach ($this->players as $player){
   			$player->getInventory()->setItemInHand(new Item(Item::AIR,0,0));
   			$player->getInventory()->clearAll();
   			$player->getInventory()->sendArmorContents($player);
   			$player->getInventory()->sendContents($player);
   			$player->getInventory()->sendHeldItem($player);
   		}
   		$this->players = array();
   		$this->startTime = NULL;
   		if($this->taskHandler != NULL){
   			$this->plugin->getServer()->getScheduler()->cancelTask($this->taskHandler->getTaskId());
   			$this->plugin->notifyEndOfRound($this);
   		}
   }
   
   /**
    * When a player quit the game
    * @param Player $loser
    */
   public function onPlayerQuit(Player $loser){
   		// Finish the duel when a player quit
   		// With onPlayerDeath() function
   		$this->onPlayerDeath($loser);
   }
   
   /**
    * When maximum round time is reached
    */
   public function onRoundEnd(){
   		foreach ($this->players as $player){
   			$player->teleport($player->getSpawn());
   			$player->sendMessage(TextFormat::BOLD . "++++++++=++++++++");
   			$player->sendMessage('หมดเวลา');
   			$player->sendMessage(TextFormat::BOLD . "++++++++=++++++++");
   			$player->removeAllEffects();
   		}
   		
   		// Reset arena
   		$this->reset();   		
	 }
	 
	 public function isPlayerInArena(Player $player){
	 	return in_array($player, $this->players);
	 }
}