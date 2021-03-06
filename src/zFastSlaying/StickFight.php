<?php

namespace zFastSlaying;

//Base
use pocketmine\plugin\PluginBase;
use pocketmine\scheduler\Task;
//Utils
use pocketmine\utils\TextFormat as Color;
use pocketmine\utils\Config;
//EventListener
use pocketmine\event\Listener;
//PlayerEvents
use pocketmine\Player;
use pocketmine\event\player\PlayerHungerChangeEvent;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\event\player\PlayerLoginEvent;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\event\player\PlayerDeathEvent;
use pocketmine\event\player\PlayerRespawnEvent;
use pocketmine\event\player\PlayerMoveEvent;
//ItemUndBlock
use pocketmine\block\Block;
use pocketmine\item\Item;
use pocketmine\item\enchantment\Enchantment;
//BlockEvents
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\block\BlockPlaceEvent;
//EntityEvents
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\entity\Effect;
//Level
use pocketmine\level\Level;
use pocketmine\level\Position;
use pocketmine\math\Vector3;
//Sounds
use pocketmine\level\sound\AnvilFallSound;
use pocketmine\level\sound\BlazeShootSound;
use pocketmine\level\sound\GhastSound;
//Commands
use pocketmine\command\CommandSender;
use pocketmine\command\Command;
//Tile
use pocketmine\tile\Sign;
use pocketmine\tile\Chest;
use pocketmine\tile\Tile;
//Nbt
use pocketmine\nbt\NBT;
use pocketmine\nbt\tag\ByteTag;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\nbt\tag\DoubleTag;
use pocketmine\nbt\tag\FloatTag;
use pocketmine\nbt\tag\IntTag;
use pocketmine\nbt\tag\ListTag;
use pocketmine\nbt\tag\ShortTag;
use pocketmine\nbt\tag\StringTag;
//Inventar
use pocketmine\inventory\ChestInventory;
use pocketmine\inventory\Inventory;
use pocketmine\event\inventory\InventoryCloseEvent;
use pocketmine\event\inventory\InventoryPickupItemEvent;
use pocketmine\event\inventory\InventoryTransactionEvent;
use pocketmine\level\particle\HeartParticle;

class StickFight extends PluginBase implements Listener {
	
	public $prefix = Color::WHITE . "[" . Color::GOLD  . "StickFight" . Color::WHITE . "] ";
	public $arenaname = "";
	public $mode = 0;
	public $players = 0;
	
	public $pc1 = 0;
	public $pc2 = 0;
	
	public $ps1 = false;
	public $ps2 = false;
	
	public function onEnable() {
    	
	    if (is_dir($this->getDataFolder()) !== true) {
        	
            mkdir($this->getDataFolder());
            
        }
        
        if (is_dir("/home/Test/plugins/StickFight") !== true) {
			
             mkdir("/home/Test/plugins/StickFight");
            
        }
        
        if (is_dir("/home/Test/plugins/StickFight/players") !== true) {
			
             mkdir("/home/Test/plugins/StickFight/players");
            
        }
    	
        if(is_dir($this->getDataFolder() . "/maps") !== true) {
        
            mkdir($this->getDataFolder() . "/maps");
            
        }
        
        $this->saveDefaultConfig();
        $this->reloadConfig();

        $config = $this->getConfig();
        
        $this->getServer()->getPluginManager()->registerEvents($this, $this);
        $this->getScheduler()->scheduleRepeatingTask(new GameSender($this), 20);
        $this->getScheduler()->scheduleRepeatingTask(new PlayerSender($this), 10);
        $this->getScheduler()->scheduleRepeatingTask(new ResetMap($this), 5);
        $this->getLogger()->info($this->prefix . Color::GREEN . "wurde aktiviert!");
        $this->getLogger()->info($this->prefix . Color::AQUA . "Made By" . Color::GREEN . " zFastSlaying");
        
    }
    
    public function copymap($src, $dst) {
    
        $dir = opendir($src);
        @mkdir($dst);
        while (false !== ($file = readdir($dir))) {
        	
            if (($file != '.') && ($file != '..')) {
            	
                if (is_dir($src . '/' . $file)) {
                	
                    $this->copymap($src . '/' . $file, $dst . '/' . $file);
                    
                } else {
                	
                    copy($src . '/' . $file, $dst . '/' . $file);
                    
                }
                
            }
            
        }
        
        closedir($dir);
        
    }

    public function deleteDirectory($dirPath) {
    
        if (is_dir($dirPath)) {
        	
            $objects = scandir($dirPath);
            foreach ($objects as $object) {
            	
                if ($object != "." && $object != "..") {
                	
                    if (filetype($dirPath . DIRECTORY_SEPARATOR . $object) == "dir") {
                    	
                        $this->deleteDirectory($dirPath . DIRECTORY_SEPARATOR . $object);
                        
                    } else {
                    	
                        unlink($dirPath . DIRECTORY_SEPARATOR . $object);
                        
                    }
                    
                }
                
            }
            
            reset($objects);
            rmdir($dirPath);
            
        }
        
    }
    
    public function onLogin(PlayerLoginEvent $event) {
    
        $player = $event->getPlayer();
        if (!is_file("/home/Test/plugins/StickFight/players/" . $player->getName() . ".yml")) {
        
            $playerfile = new Config("/home/Test/plugins/StickFight/players/" . $player->getName() . ".yml", Config::YAML);
            $playerfile->set("Kills", 0);
            $playerfile->set("Deaths", 0);
            $playerfile->set("KD", 0);
            $playerfile->save();
            
        }
        
    }
    
    public function onJoin(PlayerJoinEvent $event)
    {

        $player = $event->getPlayer();
        $config = $this->getConfig();
        $spawn = $this->getServer()->getDefaultLevel()->getSafeSpawn();
        $this->getServer()->getDefaultLevel()->loadChunk($spawn->getX(), $spawn->getZ());
        $player->teleport($spawn, 0, 0);
        $player->setGamemode(0);
        $player->setHealth(20);
        $player->setFood(20);
        $player->getInventory()->clearAll();
        $set = Item::get(395, 0, 1);
        $set->setCustomName(Color::DARK_PURPLE . "Maps");
        $player->getInventory()->setItem(4, $set);
        $player->removeAllEffects();
        $player->setAllowFlight(false);
        $all = $this->getServer()->getOnlinePlayers();
        if ($config->get("ingame") === true) {
        	
        	$event->setJoinMessage("");
        	$player->getInventory()->clearAll();
            $player->setGamemode(3);
            $level = $this->getServer()->getLevelByName($config->get("Arena"));
            $af = new Config($this->getDataFolder() . "/" . $config->get("Arena") . ".yml", Config::YAML);
            $player->teleport(new Position($af->get("s1x"), $af->get("s1y")+1, $af->get("s1z"), $level));
        
        } else {
        	
        	$event->setJoinMessage(Color::GRAY . "> " . Color::DARK_GRAY . "> " . $player->getName() . Color::GRAY . " ist der Runde Beigetreten!");
        	
        if ($this->players === 0) {
        	
        	$this->players++;
            $this->ps1 = false;
            $config->set("player1", $player->getName());
            $player->setGamemode(0);
            $config->save();
            
        } else if ($this->players === 1) {
        	
        	$this->players++;
            $this->ps2 = false;
            $config->set("player2", $player->getName());
            $player->setGamemode(0);
            $config->save();
              
        } else if ($this->players === 2) {
        	
        	$player->transfer("refloxmc.tk ", 19132);
        	
        }
        
        }
        
    }
    
    public function onQuit(PlayerQuitEvent $event) {
    	
    	$player = $event->getPlayer();
        $event->setQuitMessage(Color::GRAY . "< " . Color::DARK_GRAY . "< " . $player->getDisplayName() . Color::GRAY . " hat die Runde verlassen!");
        $config = $this->getConfig();
        if ($config->get("ingame") === false) {
        	
        	if ($player->getName() === $config->get("player1")) {
        	
        	    $this->players--;
        	    $p2 = $config->get("player2");
                
                $config->set("player1", $p2);
                $config->set("player2", "");
                $config->save();
                
            } else if ($player->getName() === $config->get("player2")) {
            	
            	$this->players--;
            	$p2 = $config->get("player2");
                
                $config->set("player2", "");
                $config->save();
            	
            }
        	
        } else {
        	
        	if ($this->players < 1) {
        	
        	    $this->players = 0;
        
            } else {
        	
        	if ($player->getName() === $config->get("player1")) {
        	
        	    $this->players--;
        
            } else if ($player->getName() === $config->get("player2")) {
        	
        	    $this->players--;
        
            }
            
            }
        	
        }
        
    }
    
    public function onMove(PlayerMoveEvent $event) {
    	
        $player = $event->getPlayer();
        $player->setFood(20);
        $player->setHealth(20);
        
    }
    
    public function onCommand(CommandSender $sender, Command $command, string $label, array $args) : bool {
    	
    	switch ($command->getName()) {
    	
    	    case "StickFight":
            if (isset($args[0])) {
            	
            	if (strtolower($args[0]) === "lobby") {
            	
            	    if ($sender->isOp()) {
            	
            	        if (isset($args[1])) {
            	
            	            $config = $this->getConfig();
                            $config->set("Server", $args[1]);
                            $config->save();
                            $sender->sendMessage($this->prefix . "Der " . Color::GOLD . "Server Name " . Color::WHITE . "wurde gesetzt!");
                            
                        }
            	
                    }
                    
                } else if (strtolower($args[0]) === "make") {
                	
                	if ($sender->isOp()) {
                	
                        if (isset($args[1])) {
                        	
                        	if (file_exists($this->getServer()->getDataPath() . "/worlds/" . $args[1])) {
                        	
                        	   if (!$this->getServer()->getLevelByName($args[1]) instanceof Level) {
                                    	
                                        $this->getServer()->loadLevel($args[1]);
                                        
                                    }
                                    
                                    $spawn = $this->getServer()->getLevelByName($args[1])->getSafeSpawn();
                                    $this->getServer()->getLevelByName($args[1])->loadChunk($spawn->getX(), $spawn->getZ());
                                    $sender->teleport($spawn, 0, 0);
                                    $config = $this->getConfig();
                                    $config->set("Arena", $args[1]);
                                    $sender->sendMessage($this->prefix . "Du hast die Arena " . Color::RED . $args[1] . Color::WHITE . " ausgewaehlt. Jetzt musst du auf den Spawn fuer den Blauen Spieler tippen");
                                    $this->mode++;
                                    return true;
                                    
                            }
                            
                        }
                        
                    }
                    
                }
                
            }
            
        }
        
        return true;
        
    }
    
    public function onInteract(PlayerInteractEvent $event) {
    	
    	$player = $event->getPlayer();
        $player->setFood(20);
        $player->setHealth(20);
        $block = $event->getBlock();
        $tile = $player->getLevel()->getTile($block);
        $config = $this->getConfig();
        $item = $player->getInventory()->getItemInHand();
        $af = new Config($this->getDataFolder() . "/" . $config->get("Arena") . ".yml", Config::YAML);
        if ($item->getCustomName() === Color::DARK_PURPLE . "Maps") {
        	
        	$player->getInventory()->clearAll();
            $ol = Item::get(395, 0, 1);
            $os = Item::get(395, 0, 1);
            $ts = Item::get(395, 0, 1);
            $ol->setCustomName(Color::GREEN . "OneLine");
            $os->setCustomName(Color::GREEN . "OneStack");
            $ts->setCustomName(Color::GREEN . "ThreeStack");
            $player->getInventory()->setItem(0, $ol);
            $player->getInventory()->setItem(4, $os);
            $player->getInventory()->setItem(8, $ts);
        	
        } else if ($item->getCustomName() === Color::GREEN . "OneLine") {
        	
        	$config->set("Arena", "OneLine-1");
            $config->save();
            $player->sendMessage(Color::WHITE . "Die Map " . Color::GREEN . "OneLine" . Color::WHITE . " wurde erfolgreich gewählt!");
            $player->getInventory()->clearAll();
            $set = Item::get(395, 0, 1);
            $set->setCustomName(Color::DARK_PURPLE . "Maps");
            $player->getInventory()->setItem(4, $set);
        	
        } else if ($item->getCustomName() === Color::GREEN . "OneStack") {
        	
        	$config->set("Arena", "OneStack-1");
            $config->save();
            $player->sendMessage(Color::WHITE . "Die Map " . Color::GREEN . "OneStack" . Color::WHITE . " wurde erfolgreich gewählt!");
            $player->getInventory()->clearAll();
            $set = Item::get(395, 0, 1);
            $set->setCustomName(Color::DARK_PURPLE . "Maps");
            $player->getInventory()->setItem(4, $set);
        	
        } else if ($item->getCustomName() === Color::GREEN . "ThreeStack") {
        	
        	$config->set("Arena", "ThreeStack-1");
            $config->save();
            $player->sendMessage(Color::WHITE . "Die Map " . Color::GREEN . "ThreeStack" . Color::WHITE . " wurde erfolgreich gewählt!");
            $player->getInventory()->clearAll();
            $set = Item::get(395, 0, 1);
            $set->setCustomName(Color::DARK_PURPLE . "Maps");
            $player->getInventory()->setItem(4, $set);
        	
        }
        
        if ($this->mode === 1 && $player->isOp()) {
        	
        	$af->set("s1x", $block->getX() + 0.5);
            $af->set("s1y", $block->getY() + 1);
            $af->set("s1z", $block->getZ() + 0.5);
            $af->save();
            
            $player->sendMessage($this->prefix . "Jetzt den Roten Spawn");
            $this->mode++;
            
        } else if ($this->mode === 2 && $player->isOp()) {
        	
        	$af->set("s2x", $block->getX() + 0.5);
            $af->set("s2y", $block->getY() + 1);
            $af->set("s2z", $block->getZ() + 0.5);
            $af->save();
            
            $player->sendMessage($this->prefix . "Jetzt den MittelPunkt Spawn");
            $this->mode++;
            
        } else if ($this->mode === 3 && $player->isOp()) {
        	
            $af->set("Y", $block->getY() + 1);
            $af->save();
            
            $player->sendMessage($this->prefix . "Die Arena ist nun Spielbereit");
            $this->mode = 0;
            
            $this->copymap($this->getServer()->getDataPath() . "/worlds/" . $player->getLevel()->getFolderName(), $this->getDataFolder() . "/maps/" . $player->getLevel()->getFolderName());
            $spawn = $this->getServer()->getDefaultLevel()->getSafeSpawn();
            $this->getServer()->getDefaultLevel()->loadChunk($spawn->getX(), $spawn->getZ());
            $player->teleport($spawn, 0, 0);
            
        }
        
    }
    
    public function onDamage(EntityDamageEvent $event) {
    	
    	$player = $event->getEntity();
        $config = $this->getConfig();
        if ($config->get("ingame") === false) {
        	
        	$event->setCancelled(true);
        
        } else {
        	
            $player->setHealth(20);
        	
        }
        
    }
    
    public function onDeath(PlayerDeathEvent $event) {
    	
    	$player = $event->getEntity();
    	$event->setDeathMessage("");
    
    }
    
    public function onRespawn(PlayerRespawnEvent $event) {
    	
    	$player = $event->getPlayer();
        $player->getInventory()->clearAll();
        $this->giveKit($player);
        
    }
    
    public function onPlace(BlockPlaceEvent $event) {
    
        $player = $event->getPlayer();
        $config = $this->getConfig();
        if ($config->get("ingame") === false) {
        	
        	$event->setCancelled();
        
        }
        
    }
    
    public function onBreak(BlockBreakEvent $event) {
    	
        $event->setCancelled(true);
        
    }
    
    public function giveKit(Player $player) {   	
        	
        $player->getInventory()->clearAll();
        $stick = Item::get(Item::STICK);
        $player->getInventory()->setItem(0, $stick);
        
    }
    
    public function delPlayer(Player $player) {
    	
    	$config = $this->getConfig();
        if ($player->getName() === $config->get("player1")) {
        	
        	$config->set("player1", "");
            $config->save();
        	
        } else if ($player->getName() === $config->get("player2")) {
        	
        	$config->set("player2", "");
            $config->save();
        	
        } else if ($player->getName() === $config->get("player3")) {
        	
        	$config->set("player3", "");
            $config->save();
        	
        } else if ($player->getName() === $config->get("player4")) {
        	
        	$config->set("player4", "");
            $config->save();
        	
        } else if ($player->getName() === $config->get("player5")) {
        	
        	$config->set("player5", "");
            $config->save();
        	
        } else if ($player->getName() === $config->get("player6")) {
        	
        	$config->set("player6", "");
            $config->save();
        	
        } else if ($player->getName() === $config->get("player7")) {
        	
        	$config->set("player7", "");
            $config->save();
        	
        } else if ($player->getName() === $config->get("player8")) {
        	
        	$config->set("player8", "");
            $config->save();
        	
        } else if ($player->getName() === $config->get("player9")) {
        	
        	$config->set("player9", "");
            $config->save();
        	
        } else if ($player->getName() === $config->get("player10")) {
        	
        	$config->set("player10", "");
            $config->save();
        	
        } else if ($player->getName() === $config->get("player11")) {
        	
        	$config->set("player11", "");
            $config->save();
        	
        } else if ($player->getName() === $config->get("player12")) {
        	
        	$config->set("player12", "");
            $config->save();
        	
        } else if ($player->getName() === $config->get("player13")) {
        	
        	$config->set("player13", "");
            $config->save();
        	
        } else if ($player->getName() === $config->get("player14")) {
        	
        	$config->set("player14", "");
            $config->save();
        	
        } else if ($player->getName() === $config->get("player15")) {
        	
        	$config->set("player15", "");
            $config->save();
        	
        } else if ($player->getName() === $config->get("player16")) {
        	
        	$config->set("player16", "");
            $config->save();
        	
        }
    	
    }
    
    public function spawn(Player $player) {
    	
    	$pos = $player->getPosition();
        $player->setSpawn($pos);
        
    }
    
    public function teleportIngame(Player $player) {
    	
    	$config = $this->getConfig();
        $level = $this->getServer()->getLevelByName($config->get("Arena"));
        if (!$this->getServer()->getLevelByName($config->get("Arena")) instanceof Level) {
        	
            $this->getServer()->loadLevel($config->get("Arena"));
            
        }
        
        $af = new Config($this->getDataFolder() . "/" . $config->get("Arena") . ".yml", Config::YAML);
        if ($player->getName() === $config->get("player1")) {
        	
        	$player->teleport(new Position($af->get("s1x"), $af->get("s1y")+1, $af->get("s1z"), $level));
        
        } else if ($player->getName() === $config->get("player2")) {
            
        	$player->teleport(new Position($af->get("s2x"), $af->get("s2y")+1, $af->get("s2z"), $level));
        
        } else {
        	
        	$player->teleport(new Position($af->get("s1x"), $af->get("s1y")+1, $af->get("s1z"), $level));
        	
        }
        
    }
    
    public function teleportIngame2(Player $player) {
    	
    	$config = $this->getConfig();
        $level = $this->getServer()->getLevelByName($config->get("Arena"));
        if (!$this->getServer()->getLevelByName($config->get("Arena")) instanceof Level) {
        	
            $this->getServer()->loadLevel($config->get("Arena"));
            
        }
        
        $af = new Config($this->getDataFolder() . "/" . $config->get("Arena") . ".yml", Config::YAML);
        if ($player->getName() === $config->get("player2")) {
        	
        	$player->teleport(new Position($af->get("s1x"), $af->get("s1y")+1, $af->get("s1z"), $level));
        
        } else if ($player->getName() === $config->get("player1")) {
            
        	$player->teleport(new Position($af->get("s2x"), $af->get("s2y")+1, $af->get("s2z"), $level));
        
        }
        
    }
	
}

class ResetMap extends Task {
	
	public function __construct($plugin)
    {

        $this->plugin = $plugin;

    }

    public function onRun($tick)
    {
    	
    	$level = $this->plugin->getServer()->getDefaultLevel();
        $config = $this->plugin->getConfig();
        $all = $this->plugin->getServer()->getOnlinePlayers();
        if ($config->get("ingame") === true) {
        	
        	foreach ($all as $player) {
        	
        	    if ($player->getName() === $config->get("player1")) {
        	
        	        $y = $player->getY();
                    if ($y <= 0) {
                    	
                    	$p2 = $config->get("player2");
                    	$p = $this->plugin->getServer()->getPlayerExact($p2);
                        $af = new Config($this->plugin->getDataFolder() . "/" . $config->get("Arena") . ".yml", Config::YAML);
                        if ($config->get("Arena") === "OneLine-1") {
                        	
                        	if ($p->getX() > 0) {
                        	
                        	    $player->setHealth(20);
                                $player->setFood(20);
                                $this->plugin->teleportIngame2($player);
                                $this->plugin->spawn($player);
                                $this->plugin->giveKit($player);
                                $this->plugin->pc2++;
                        
                                $pf = new Config("/home/Test/plugins/StickFight/players/" . $player->getName() . ".yml", Config::YAML);
                                $pf->set("Deaths", $pf->get("Deaths")+1);
                                $pf->save();
                        
                                $pf2 = new Config("/home/Test/plugins/StickFight/players/" . $p2 . ".yml", Config::YAML);
                                $pf2->set("Kills", $pf2->get("Kills")+1);
                                $pf2->save();
                                
                            } else if ($p->getX() < 0) {
                        	
                        	    $player->setHealth(20);
                                $player->setFood(20);
                                $this->plugin->teleportIngame($player);
                                $this->plugin->spawn($player);
                                $this->plugin->giveKit($player);
                                $this->plugin->pc2++;
                        
                                $pf = new Config("/home/Test/plugins/StickFight/players/" . $player->getName() . ".yml", Config::YAML);
                                $pf->set("Deaths", $pf->get("Deaths")+1);
                                $pf->save();
                        
                                $pf2 = new Config("/home/Test/plugins/StickFight/players/" . $p2 . ".yml", Config::YAML);
                                $pf2->set("Kills", $pf2->get("Kills")+1);
                                $pf2->save();
                                
                            }
                        	
                        } else {
                        	
                        if ($p->getY() === $af->get("Y")) {
                        	
                        	$player->setHealth(20);
                            $player->setFood(20);
                            $this->plugin->teleportIngame($player);
                            $this->plugin->spawn($player);
                            $this->plugin->giveKit($player);
                            $this->plugin->pc2++;
                        
                            $pf = new Config("/home/Test/plugins/StickFight/players/" . $player->getName() . ".yml", Config::YAML);
                            $pf->set("Deaths", $pf->get("Deaths")+1);
                            $pf->save();
                        
                             $pf2 = new Config("/home/Test/plugins/StickFight/players/" . $p2 . ".yml", Config::YAML);
                             $pf2->set("Kills", $pf2->get("Kills")+1);
                             $pf2->save();
                        	
                        } else if ($p->getY() < $af->get("Y")) {
                        	
                        	$player->setHealth(20);
                            $player->setFood(20);
                            $this->plugin->teleportIngame2($player);
                            $this->plugin->spawn($player);
                            $this->plugin->giveKit($player);
                            $this->plugin->pc2++;
                        
                            $pf = new Config("/home/Test/plugins/StickFight/players/" . $player->getName() . ".yml", Config::YAML);
                            $pf->set("Deaths", $pf->get("Deaths")+1);
                            $pf->save();
                        
                             $pf2 = new Config("/home/Test/plugins/StickFight/players/" . $p2 . ".yml", Config::YAML);
                             $pf2->set("Kills", $pf2->get("Kills")+1);
                             $pf2->save();
                        	
                        } else if ($p->getY() > $af->get("Y")) {
                        	
                        	$player->setHealth(20);
                            $player->setFood(20);
                            $this->plugin->teleportIngame($player);
                            $this->plugin->spawn($player);
                            $this->plugin->giveKit($player);
                            $this->plugin->pc2++;
                        
                            $pf = new Config("/home/Test/plugins/StickFight/players/" . $player->getName() . ".yml", Config::YAML);
                            $pf->set("Deaths", $pf->get("Deaths")+1);
                            $pf->save();
                        
                             $pf2 = new Config("/home/Test/plugins/StickFight/players/" . $p2 . ".yml", Config::YAML);
                             $pf2->set("Kills", $pf2->get("Kills")+1);
                             $pf2->save();
                        	
                        }
                        
                        }
                    	
                    }
                    
                }
                
                if ($player->getName() === $config->get("player2")) {
        	
        	        $y = $player->getY();
                    if ($y <= 0) {
                    	
                    	$p2 = $config->get("player1");
                    	$p = $this->plugin->getServer()->getPlayerExact($p2);
                        $af = new Config($this->plugin->getDataFolder() . "/" . $config->get("Arena") . ".yml", Config::YAML);
                        if ($config->get("Arena") === "OneLine-1") {
                        	
                        	if ($p->getX() > 0) {
                        	
                        	    $player->setHealth(20);
                                $player->setFood(20);
                                $this->plugin->teleportIngame($player);
                                $this->plugin->spawn($player);
                                $this->plugin->giveKit($player);
                                $this->plugin->pc1++;
                        
                                $pf = new Config("/home/Test/plugins/StickFight/players/" . $player->getName() . ".yml", Config::YAML);
                                $pf->set("Deaths", $pf->get("Deaths")+1);
                                $pf->save();
                        
                                $pf2 = new Config("/home/Test/plugins/StickFight/players/" . $p2 . ".yml", Config::YAML);
                                $pf2->set("Kills", $pf2->get("Kills")+1);
                                $pf2->save();
                                
                            } else if ($p->getX() < 0) {
                        	
                        	    $player->setHealth(20);
                                $player->setFood(20);
                                $this->plugin->teleportIngame2($player);
                                $this->plugin->spawn($player);
                                $this->plugin->giveKit($player);
                                $this->plugin->pc1++;
                        
                                $pf = new Config("/home/Test/plugins/StickFight/players/" . $player->getName() . ".yml", Config::YAML);
                                $pf->set("Deaths", $pf->get("Deaths")+1);
                                $pf->save();
                        
                                $pf2 = new Config("/home/Test/plugins/StickFight/players/" . $p2 . ".yml", Config::YAML);
                                $pf2->set("Kills", $pf2->get("Kills")+1);
                                $pf2->save();
                                
                            }
                        	
                        } else {
                        	
                        if ($p->getY() === $af->get("Y")) {
                        	
                        	$player->setHealth(20);
                            $player->setFood(20);
                            $this->plugin->teleportIngame($player);
                            $this->plugin->spawn($player);
                            $this->plugin->giveKit($player);
                            $this->plugin->pc1++;
                        
                            $pf = new Config("/home/Test/plugins/StickFight/players/" . $player->getName() . ".yml", Config::YAML);
                            $pf->set("Deaths", $pf->get("Deaths")+1);
                            $pf->save();
                        
                             $pf2 = new Config("/home/Test/plugins/StickFight/players/" . $p2 . ".yml", Config::YAML);
                             $pf2->set("Kills", $pf2->get("Kills")+1);
                             $pf2->save();
                        	
                        } else if ($p->getY() < $af->get("Y")) {
                        	
                        	$player->setHealth(20);
                            $player->setFood(20);
                            $this->plugin->teleportIngame($player);
                            $this->plugin->spawn($player);
                            $this->plugin->giveKit($player);
                            $this->plugin->pc1++;
                        
                            $pf = new Config("/home/Test/plugins/StickFight/players/" . $player->getName() . ".yml", Config::YAML);
                            $pf->set("Deaths", $pf->get("Deaths")+1);
                            $pf->save();
                        
                             $pf2 = new Config("/home/Test/plugins/StickFight/players/" . $p2 . ".yml", Config::YAML);
                             $pf2->set("Kills", $pf2->get("Kills")+1);
                             $pf2->save();
                        	
                        } else if ($p->getY() > $af->get("Y")) {
                        	
                        	$player->setHealth(20);
                            $player->setFood(20);
                            $this->plugin->teleportIngame2($player);
                            $this->plugin->spawn($player);
                            $this->plugin->giveKit($player);
                            $this->plugin->pc1++;
                        
                            $pf = new Config("/home/Test/plugins/StickFight/players/" . $player->getName() . ".yml", Config::YAML);
                            $pf->set("Deaths", $pf->get("Deaths")+1);
                            $pf->save();
                        
                             $pf2 = new Config("/home/Test/plugins/StickFight/players/" . $p2 . ".yml", Config::YAML);
                             $pf2->set("Kills", $pf2->get("Kills")+1);
                             $pf2->save();
                        	
                        }
                        
                        }
                    	
                    }
                    
                }
                
            }
            
        }
    	
    }
	
}

class PlayerSender extends Task
{
	
	public function __construct($plugin)
    {

        $this->plugin = $plugin;

    }

    public function onRun($tick)
    {
    	
    	$config = $this->plugin->getConfig();
        $all = $this->plugin->getServer()->getOnlinePlayers();
        $config->set("players", $this->plugin->players);
        $config->save();
        if (count($all) === 0) {

            if ($config->get("state") === true) {

                $config->set("ingame", false);
                $config->set("state", false);
                $config->set("reset", false);
                $config->set("rtime", 10);
                $config->set("time", 20);
                $config->set("playtime", 3600);
                $config->save();

            }

        }
    	
    }
	
}

class GameSender extends Task
{

    public function __construct($plugin)
    {

        $this->plugin = $plugin;

    }

    public function onRun($tick)
    {

        $level = $this->plugin->getServer()->getDefaultLevel();
        $config = $this->plugin->getConfig();
        $all = $this->plugin->getServer()->getOnlinePlayers();
        if ($config->get("ingame") === false) {

            if ($this->plugin->players < 2) {

                foreach ($all as $player) {

                    $player->sendPopup(Color::GRAY . ">> Warten auf weitere Spieler <<");

                }

            }

            if ($this->plugin->players >= 2) {

                $config->set("time", $config->get("time") - 1);
                $config->save();
                $time = $config->get("time") + 1;
                foreach ($all as $player) {
                	
                	$t = str_repeat(" ", 87);
                    $pf = new Config("/home/Test/plugins/StickFight/players/" . $player->getName() . ".yml", Config::YAML);
                    if ($pf->get("Kills") === 0) {
                    	
                    	$player->sendTip($t . Color::GRAY . ">> " . Color::GOLD . "StickFight" . Color::GRAY . " <<\n" .
                                                        $t . "\n" .
                                                        $t . Color::GREEN . "Map: " . Color::GRAY . $config->get("Arena") . "\n" .
                                                        $t . "\n" .
                                                        $t . Color::GREEN . "Kills: " . Color::GRAY . $pf->get("Kills") . "\n" .
                                                        $t . Color::GREEN . "Deaths: " . Color::GRAY . $pf->get("Deaths") . "\n" .
                                                        $t . Color::GREEN . "K/D: " . Color::GRAY . $pf->get("Deaths") . "\n" .
                                                        $t . "\n" .
                                                        $t . Color::GRAY . ">> " . Color::GOLD . "StickFight" . Color::GRAY . " <<" . str_repeat("\n", 20));
                    	
                    } else if ($pf->get("Deaths") === 0) {
                    	
                    	$player->sendTip($t . Color::GRAY . ">> " . Color::GOLD . "StickFight" . Color::GRAY . " <<\n" .
                                                        $t . "\n" .
                                                        $t . Color::GREEN . "Map: " . Color::GRAY . $config->get("Arena") . "\n" .
                                                        $t . "\n" .
                                                        $t . Color::GREEN . "Kills: " . Color::GRAY . $pf->get("Kills") . "\n" .
                                                        $t . Color::GREEN . "Deaths: " . Color::GRAY . $pf->get("Deaths") . "\n" .
                                                        $t . Color::GREEN . "K/D: " . Color::GRAY . $pf->get("Kills") . "\n" .
                                                        $t . "\n" .
                                                        $t . Color::GRAY . ">> " . Color::GOLD . "StickFight" . Color::GRAY . " <<" . str_repeat("\n", 20));
                    	
                    } else {
                    	
                    	$player->sendTip($t . Color::GRAY . ">> " . Color::GOLD . "StickFight" . Color::GRAY . " <<\n" .
                                                        $t . "\n" .
                                                        $t . Color::GREEN . "Map: " . Color::GRAY . $config->get("Arena") . "\n" .
                                                        $t . "\n" .
                                                        $t . Color::GREEN . "Kills: " . Color::GRAY . $pf->get("Kills") . "\n" .
                                                        $t . Color::GREEN . "Deaths: " . Color::GRAY . $pf->get("Deaths") . "\n" .
                                                        $t . Color::GREEN . "K/D: " . Color::GRAY . $pf->get("Kills") / $pf->get("Deaths") . "\n" .
                                                        $t . "\n" .
                                                        $t . Color::GRAY . ">> " . Color::GOLD . "StickFight" . Color::GRAY . " <<" . str_repeat("\n", 20));
                    	
                    }
                    
                }
                
                if ($time % 5 === 0 && $time > 0) {

                    foreach ($all as $player) {

                        $player->sendMessage(Color::DARK_PURPLE . ">> " . Color::WHITE . "Die Runde startet in " . Color::DARK_PURPLE . $time . Color::WHITE . " Sekunden!");

                    }

                } else if ($time === 15) {
                
                	$config->set("state", true);
                    $config->save();
                	foreach ($all as $player) {

                        $player->sendMessage(Color::DARK_PURPLE . ">> " . Color::WHITE . "Die Runde startet in " . Color::DARK_PURPLE . $time . Color::WHITE . " Sekunden!");

                    }
                	
                } else if ($time === 4 || $time === 3 || $time === 2 || $time === 1) {

                    foreach ($all as $player) {

                        $player->sendMessage(Color::DARK_PURPLE . ">> " . Color::WHITE . "Die Runde startet in " . Color::DARK_PURPLE . $time . Color::WHITE . " Sekunden!");

                    }

                } else if ($time === 1) {
                	
                	foreach ($all as $player) {
                	
                	    $player->sendMessage($this->plugin->prefix . "Die Map: " . Color::GREEN . $config->get("Arena").  Color::WHITE . " wurde gevotet!");
                
                    }
                	
                } else if ($time === 0) {

                    $config->set("ingame", true);
                    $config->set("state", true);
                    foreach ($all as $player) {

                        $player->setHealth(20);
                        $player->setFood(20);
                        $this->plugin->teleportIngame($player);
                        $this->plugin->spawn($player);
                        $this->plugin->giveKit($player);

                    }

                    $config->save();

                }

            }

        } else if ($config->get("ingame") === true) {

            $all = $this->plugin->getServer()->getOnlinePlayers();
            if ($this->plugin->players <= 1) {

                foreach ($all as $player) {

                    $player->getInventory()->clearAll();
                    $player->setHealth(20);
                    $player->setFood(20);
                    $player->removeAllEffects();
                    $spawn = $this->plugin->getServer()->getDefaultLevel()->getSafeSpawn();
                    $this->plugin->getServer()->getDefaultLevel()->loadChunk($spawn->getX(), $spawn->getZ());
                    $player->teleport($spawn, 0, 0);
                    $player->sendMessage(Color::WHITE . "[" . Color::DARK_PURPLE . "+" . Color::WHITE . "] 100 Coins");
                    $pc = new Config("/home/Test/plugins/Coins/" . $player->getName() . ".yml", Config::YAML);
                    $pc->set("coins", $pc->get("coins")+100);
                    $pc->save();
                    $pf = new Config("/home/Test/plugins/StickFight/players/" . $player->getName() . ".yml", Config::YAML);
                    $pf->set("wins", $pf->get("wins") + 1);
                    $pf->save();
                    $config->set("ingame", false);
                    $config->set("reset", true);
                    $config->set("rtime", 10);
                    $config->set("time", 20);
                    $config->set("playtime", 3600);
                    $config->set("player1", "");
                    $config->set("player2", "");
                    $this->plugin->pc1 = 0;
                    $this->plugin->pc2 = 0;
                    $config->save();
                    $this->plugin->players = 0;
                    
                }

            } elseif ($this->plugin->players >= 2) {

                $config->set("playtime", $config->get("playtime") - 1);
                $config->save();
                $time = $config->get("playtime") + 1;
                foreach ($all as $player) {
                	
                	if ($this->plugin->pc1 === $this->plugin->pc2) {
                	
                	    $player->sendPopup(Color::YELLOW . $config->get("player1") . Color::GRAY . " [ " . Color::YELLOW . $this->plugin->pc1 . Color::GRAY . " ] / " . Color::YELLOW . $config->get("player2") . Color::GRAY . " [ " . Color::YELLOW . $this->plugin->pc2 . Color::GRAY . " ]");
                
                    }
                    
                    if ($this->plugin->pc1 > $this->plugin->pc2) {
                	
                	    $player->sendPopup(Color::GREEN . $config->get("player1") . Color::GRAY . " [ " . Color::GREEN . $this->plugin->pc1 . Color::GRAY . " ] / " . Color::RED . $config->get("player2") . Color::GRAY . " [ " . Color::RED . $this->plugin->pc2 . Color::GRAY . " ]");
                
                    }
                   
                    if ($this->plugin->pc1 < $this->plugin->pc2) {
                	
                	    $player->sendPopup(Color::RED . $config->get("player1") . Color::GRAY . " [ " . Color::RED . $this->plugin->pc1 . Color::GRAY . " ] / " . Color::GREEN . $config->get("player2") . Color::GRAY . " [ " . Color::GREEN . $this->plugin->pc2 . Color::GRAY . " ]");
                
                    }
                    
                	if ($time === 0) {

                        $player->getInventory()->clearAll();
                        $player->setHealth(20);
                        $player->setFood(20);
                        $player->removeAllEffects();
                        $player->sendMessage($this->plugin->prefix . Color::GREEN . "Du hast die Runde gewonnen!");
                        $this->plugin->getServer()->broadcastMessage($this->plugin->prefix . $player->getName() . Color::GREEN . " hat das Runde in " . Color::WHITE . $config->get("Arena") . Color::GREEN . " Gewonnen!");
                        $spawn = $this->plugin->getServer()->getDefaultLevel()->getSafeSpawn();
                        $this->plugin->getServer()->getDefaultLevel()->loadChunk($spawn->getX(), $spawn->getZ());
                        $player->teleport($spawn, 0, 0);
                        $config->set("ingame", false);
                        $config->set("reset", true);
                        $config->set("rtime", 10);
                        $config->set("time", 20);
                        $config->set("playtime", 3600);
                    $config->set("player1", "");
                    $config->set("player2", "");
                    $this->plugin->pc1 = 0;
                    $this->plugin->pc2 = 0;
                        $config->save();
                        $this->plugin->players = 0;
                        
                    }

                }

            }

        } 
        
        if ($config->get("reset") === true) {

            $config->set("rtime", $config->get("rtime") - 1);
            $config->save();
            $time = $config->get("rtime") + 1;
            if ($time === 10) {
            	
            	$clouddata = new Config("/home/Test/Daten.yml", Config::YAML);
                $clouddata->set("ServerMessage", "Der Server: " . $config->get("Server") . " wird heruntergefahren!");
                $clouddata->set("ServerMessageStatus", true);
                $clouddata->set($config->get("Server"), false);
                $clouddata->save();
            	$this->plugin->getServer()->broadcastMessage(Color::DARK_PURPLE . ">> " . Color::WHITE . "Die Runde restartet in  " . Color::DARK_PURPLE . $time . Color::WHITE . " Sekunden!");
            	
            } else if ($time === 5) {
            	
            	$this->plugin->getServer()->broadcastMessage(Color::DARK_PURPLE . ">> " . Color::WHITE . "Die Runde restartet in " . Color::DARK_PURPLE . $time . Color::WHITE . " Sekunden!");
            	
            } else if ($time === 0) {
            	
            	$clouddata = new Config("/home/Test/Daten.yml", Config::YAML);
                $clouddata->set("ServerMessage", "Der Server: " . $config->get("Server") . " wird hochgefahren!");
                $clouddata->set("ServerMessageStatus", true);
                $clouddata->set($config->get("Server"), true);
                $clouddata->save();
                foreach ($all as $player) {
                	
                	$player->transfer("refloxmc.tk", 19132);
                	
                }
                
                $config->set("reset", false);
                $config->set("rtime", 10);
                $config->set("state", false);
                $config->save();
                $this->plugin->players = 0;
            	
            }

        }

    }

}
