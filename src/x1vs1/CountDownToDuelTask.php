<?php
namespace x1vs1;
use pocketmine\scheduler\PluginTask;
use pocketmine\utils\TextFormat;
use pocketmine\plugin\Plugin;
use x1vs1\Arena;
class CountDownToDuelTask extends PluginTask{
	
	const COUNTDOWN_DURATION = 5;
	
	private $arena;
	private $countdownValue;
	
	public function __construct(Plugin $owner, Arena $arena){
		parent::__construct($owner);
		$this->arena = $arena;
		$this->countdownValue = CountDownToDuelTask::COUNTDOWN_DURATION;
	}
	
	public function onRun($currentTick){
		if(count($this->arena->players) < 2){
			$this->arena->abortDuel();
		}else{
			$player1 = $this->arena->players[0];
			$player2 = $this->arena->players[1];
			
			if(!$player1->isOnline() || !$player2->isOnline()){
				$this->arena->abortDuel();
			}else{
				$player1->sendTip('เริ่มใน '. $this->countdownValue . ' วินาที');
				$player2->sendTip('เริ่มใน '. $this->countdownValue . ' วินาที');
				$this->countdownValue--;
				
				if($this->countdownValue == 0){
					$this->arena->startDuel();
				}
			}
		}
	}
}