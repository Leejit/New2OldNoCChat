<?php

namespace FactionsPro;

use onebone\economyapi\EconomyAPI;
use pocketmine\plugin\PluginBase;
use pocketmine\command\CommandSender;
use pocketmine\command\Command;
use pocketmine\event\Listener;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\player\PlayerChatEvent;
use pocketmine\Player;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\utils\TextFormat;
use pocketmine\scheduler\PluginTask;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\utils\Config;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\math\Vector3;
use pocketmine\level\level;
use pocketmine\level\Position;

class FactionCommands {

	public $plugin;

	public function __construct(FactionMain $pg) {
		$this->plugin = $pg;
	}

	public function onCommand(CommandSender $sender, Command $command, $label, array $args) {
		if($sender instanceof Player) {
			$player = $sender->getPlayer()->getName();
			$create = $this->plugin->prefs->get("CreateCost");
			$claim = $this->plugin->prefs->get("ClaimCost");
			$oclaim = $this->plugin->prefs->get("OverClaimCost");
			$allyr = $this->plugin->prefs->get("AllyCost");
			$allya = $this->plugin->prefs->get("AllyPrice");
			$home = $this->plugin->prefs->get("SetHomeCost");
			if(strtolower($command->getName('c'))) {
				if(empty($args)) {
					$sender->sendMessage($this->plugin->formatMessage("§fuse §e/c help §ffor a list of commands"));
					return true;
				}
				if(count($args == 2)) {

                    ///////////////////////////////// WAR /////////////////////////////////

                    if ($args[0] == "war") {
                        if (!isset($args[1])) {
                            $sender->sendMessage($this->plugin->formatMessage("Usage: /c war <clan name:tp>"));
                            return true;
                        }
                        if (strtolower($args[1]) == "tp") {
                            foreach ($this->plugin->wars as $r => $f) {
                                $fac = $this->plugin->getPlayerFaction($player);
                                if ($r == $fac) {
                                    $x = mt_rand(0, $this->plugin->getNumberOfPlayers($fac) - 1);
                                    $tper = $this->plugin->war_players[$f][$x];
                                    $sender->teleport($this->plugin->getServer()->getPlayerByName($tper));
                                    return;
                                }
                                if ($f == $fac) {
                                    $x = mt_rand(0, $this->plugin->getNumberOfPlayers($fac) - 1);
                                    $tper = $this->plugin->war_players[$r][$x];
                                    $sender->teleport($this->plugin->getServer()->getPlayer($tper));
                                    return;
                                }
                            }
                            $sender->sendMessage("You must be in a war to do that");
                            return true;
                        }
                        if (!(ctype_alnum($args[1]))) {
                            $sender->sendMessage($this->plugin->formatMessage("You may only use letters and numbers"));
                            return true;
                        }
                        if (!$this->plugin->factionExists($args[1])) {
                            $sender->sendMessage($this->plugin->formatMessage("Clan does not exist"));
                            return true;
                        }
                        if (!$this->plugin->isInFaction($sender->getName())) {
                            $sender->sendMessage($this->plugin->formatMessage("You must be in a clan to do this"));
                            return true;
                        }
                        if (!$this->plugin->isLeader($player)) {
                            $sender->sendMessage($this->plugin->formatMessage("Only your clan leader may start wars"));
                            return true;
                        }
                        if (!$this->plugin->areEnemies($this->plugin->getPlayerFaction($player), $args[1])) {
                            $sender->sendMessage($this->plugin->formatMessage("Your clan is not an enemy of $args[1]"));
                            return true;
                        } else {
                            $factionName = $args[1];
                            $sFaction = $this->plugin->getPlayerFaction($player);
                            foreach ($this->plugin->war_req as $r => $f) {
                                if ($r == $args[1] && $f == $sFaction) {
                                    foreach ($this->plugin->getServer()->getOnlinePlayers() as $p) {
                                        $task = new FactionWar($this->plugin, $r);
                                        $handler = $this->plugin->getServer()->getScheduler()->scheduleDelayedTask($task, 20 * 60 * 2);
                                        $task->setHandler($handler);
                                        $p->sendMessage("The war against $factionName and $sFaction has started!");
                                        if ($this->plugin->getPlayerFaction($p->getName()) == $sFaction) {
                                            $this->plugin->war_players[$sFaction][] = $p->getName();
                                        }
                                        if ($this->plugin->getPlayerFaction($p->getName()) == $factionName) {
                                            $this->plugin->war_players[$factionName][] = $p->getName();
                                        }
                                    }
                                    $this->plugin->wars[$factionName] = $sFaction;
                                    unset($this->plugin->war_req[strtolower($args[1])]);
                                    return true;
                                }
                            }
                            $this->plugin->war_req[$sFaction] = $factionName;
                            foreach ($this->plugin->getServer()->getOnlinePlayers() as $p) {
                                if ($this->plugin->getPlayerFaction($p->getName()) == $factionName) {
                                    if ($this->plugin->getLeader($factionName) == $p->getName()) {
                                        $p->sendMessage("$sFaction wants to start a war, '/c war $sFaction' to start!");
                                        $sender->sendMessage("Clan war requested");
                                        return true;
                                    }
                                }
                            }
                            $sender->sendMessage("§cClan leader is not online.");
                            return true;
                        }
                    }

					/////////////////////////////// CREATE ///////////////////////////////

					if($args[0] == "create") {
						if(!isset($args[1])) {
							$sender->sendMessage($this->plugin->formatMessage("§bUsage: §e/c create <clan name>"));
							return true;
						}
						if(!(ctype_alnum($args[1]))) {
							$sender->sendMessage($this->plugin->formatMessage("§cNames can only include letters or numbers"));
							return true;
						}
						if($this->plugin->isNameBanned($args[1])) {
							$sender->sendMessage($this->plugin->formatMessage("§cThis name is restricted"));
							return true;
						}
						if($this->plugin->factionExists($args[1]) == true ) {
							$sender->sendMessage($this->plugin->formatMessage("§cThis name is already in use"));
							return true;
						}
						if(strlen($args[1]) > $this->plugin->prefs->get("MaxFactionNameLength")) {
							$sender->sendMessage($this->plugin->formatMessage("§cThis name is too long"));
							return true;
						}
						if($this->plugin->isInFaction($sender->getName())) {
							$sender->sendMessage($this->plugin->formatMessage("§cYou are already in a clan"));
							return true;
						} elseif($r = EconomyAPI::getInstance()->reduceMoney($player, $create)) {
							$factionName = $args[1];
							$player = strtolower($player);
							$rank = "Leader";
							$stmt = $this->plugin->db->prepare("INSERT OR REPLACE INTO master (player, faction, rank) VALUES (:player, :faction, :rank);");
							$stmt->bindValue(":player", $player);
							$stmt->bindValue(":faction", $factionName);
							$stmt->bindValue(":rank", $rank);
							$result = $stmt->execute();

                            $this->plugin->setFactionPower($factionName, $this->plugin->prefs->get("TheDefaultPowerEveryFactionStartsWith"));
							$sender->sendMessage($this->plugin->formatMessage("§aClan successfully created for §6$$create", true));
							return true;
						}
						else {

						switch($r){
							case EconomyAPI::RET_INVALID:

								$sender->sendMessage($this->plugin->formatMessage("§bYou do not have enough Money to create a Clan! Need §6$$create."));
								break;
							case EconomyAPI::RET_CANCELLED:

								$sender->sendMessage($this->plugin->formatMessage("§6-ERROR!"));
								break;
							case EconomyAPI::RET_NO_ACCOUNT:
								$sender->sendMessage($this->plugin->formatMessage("§6-ERROR!"));
								break;
						}
					}
					}

					/////////////////////////////// INVITE ///////////////////////////////

					if ($args[0] == "invite") {
						if (!isset($args[1])) {
							$sender->sendMessage($this->plugin->formatMessage("§cUsage: §e/c invite <player>"));
							return true;
						}
						if ($this->plugin->isFactionFull($this->plugin->getPlayerFaction($player)) ) {
							$sender->sendMessage($this->plugin->formatMessage("§cClan is full."));
							return true;
						}
						$invited = $this->plugin->getServer()->getPlayerExact($args[1]);
                        if(!$invited instanceof Player) {
							$sender->sendMessage($this->plugin->formatMessage("§cPlayer is offline"));
							return true;
						}
						if ($this->plugin->isInFaction($args[1]) == true) {
							$sender->sendMessage($this->plugin->formatMessage("§cPlayer is already in a clan"));
							return true;
						}
						if ($this->plugin->prefs->get("OnlyLeadersAndOfficersCanInvite") == true) {
                if (!($this->plugin->isOfficer($player) || $this->plugin->isLeader($player))){
							      $sender->sendMessage($this->plugin->formatMessage("§cOnly leader and officers can invite"));
							      return true;
                }
						}
            if ($invited->getName() == $player) {

                $sender->sendMessage($this->plugin->formatMessage("You can't invite yourself to your own clan"));
                return true;
            }
						if($invited->isOnline() == true) {
							$factionName = $this->plugin->getPlayerFaction($player);
							$invitedName = $invited->getName();
							$rank = "Member";

							$stmt = $this->plugin->db->prepare("INSERT OR REPLACE INTO confirm (player, faction, invitedby, timestamp) VALUES (:player, :faction, :invitedby, :timestamp);");
							$stmt->bindValue(":player", strtolower($invitedName));
							$stmt->bindValue(":faction", $factionName);
							$stmt->bindValue(":invitedby", $sender->getName());
							$stmt->bindValue(":timestamp", time());
							$result = $stmt->execute();
							$sender->sendMessage($this->plugin->formatMessage("§b$invitedName §ahas been invited", true));
							$invited->sendMessage($this->plugin->formatMessage("§bYou have been invited to §a$factionName.§b Use §e'/c accept' §bor §e'/c deny'", true));
						} else {
							$sender->sendMessage($this->plugin->formatMessage("§cPlayer is offline"));
						}
					}

					/////////////////////////////// LEADER ///////////////////////////////

					if($args[0] == "leader") {
						if(!isset($args[1])) {
							$sender->sendMessage($this->plugin->formatMessage("§bUsage: §e/c leader <player>"));
							return true;
						}
						if(!$this->plugin->isInFaction($sender->getName())) {
							$sender->sendMessage($this->plugin->formatMessage("§cYou must be in a clan to use this"));
              return true;
						}
						if(!$this->plugin->isLeader($player)) {
							$sender->sendMessage($this->plugin->formatMessage("§cYou must be leader to use this"));
              return true;
						}
						if($this->plugin->getPlayerFaction($player) != $this->plugin->getPlayerFaction($args[1])) {
							$sender->sendMessage($this->plugin->formatMessage("§cThat player is not in this clan"));
              return true;
						}
						if(!($this->plugin->getServer()->getPlayer($args[1]) instanceof Player)) {
							$sender->sendMessage($this->plugin->formatMessage("§cPlayer is not online to accept"));
              return true;
						}
            if ($args[1] == $sender->getName()) {

               $sender->sendMessage($this->plugin->formatMessage("You can't transfer the leadership to yourself"));
                  return true;
                }
				        $factionName = $this->plugin->getPlayerFaction($player);
				        $stmt = $this->plugin->db->prepare("INSERT OR REPLACE INTO master (player, faction, rank) VALUES (:player, :faction, :rank);");
				        $stmt->bindValue(":player", $player);
				        $stmt->bindValue(":faction", $factionName);
				        $stmt->bindValue(":rank", "Member");
				        $result = $stmt->execute();

				        $stmt = $this->plugin->db->prepare("INSERT OR REPLACE INTO master (player, faction, rank) VALUES (:player, :faction, :rank);");
				        $stmt->bindValue(":player", strtolower($args[1]));
				        $stmt->bindValue(":faction", $factionName);
				        $stmt->bindValue(":rank", "Leader");
				        $result = $stmt->execute();


				        $sender->sendMessage($this->plugin->formatMessage("§aYou are no longer leader", true));
				        $this->plugin->getServer()->getPlayer($args[1])->sendMessage($this->plugin->formatMessage("§bYou are now leader of §a$factionName §b!",  true));
                $this->plugin->updateTag($sender->getName());
								$this->plugin->updateTag($this->plugin->getServer()->getPlayerExact($args[1])->getName());
				}

					/////////////////////////////// PROMOTE ///////////////////////////////

					if($args[0] == "promote") {
						if(!isset($args[1])) {
							$sender->sendMessage($this->plugin->formatMessage("§bUsage: §e/c promote <player>"));
							return true;
						}
						if(!$this->plugin->isInFaction($sender->getName())) {
							$sender->sendMessage($this->plugin->formatMessage("§cYou must be in a clan to use this"));
							return true;
						}
						if(!$this->plugin->isLeader($player)) {
							$sender->sendMessage($this->plugin->formatMessage("§cYou must be leader to use this"));
							return true;
						}
						if($this->plugin->getPlayerFaction($player) != $this->plugin->getPlayerFaction($args[1])) {
							$sender->sendMessage($this->plugin->formatMessage("§cThat player is not in this clan"));
							return true;
						}
            if ($args[1] == $sender->getName()) {
              $sender->sendMessage($this->plugin->formatMessage("§cYou can't promote yourself"));
              return true;
            }
						if($this->plugin->isOfficer($args[1])) {
							$sender->sendMessage($this->plugin->formatMessage("§cAlready an Officer"));
							return true;
						}
            if(!($this->plugin->getServer()->getPlayer($args[1]) instanceof Player)) {
							$sender->sendMessage($this->plugin->formatMessage("§cPlayer is offline!"));
              return true;
						}
						$factionName = $this->plugin->getPlayerFaction($player);
						$stmt = $this->plugin->db->prepare("INSERT OR REPLACE INTO master (player, faction, rank) VALUES (:player, :faction, :rank);");
						$stmt->bindValue(":player", strtolower($args[1]));
						$stmt->bindValue(":faction", $factionName);
						$stmt->bindValue(":rank", "Officer");
						$result = $stmt->execute();
						$player = $this->plugin->getServer()->getPlayer($args[1]);
						$sender->sendMessage($this->plugin->formatMessage("§b" . $player->getName() . " §ahas been promoted to Officer!", true));
						$this->plugin->getServer()->getPlayer($args[1])->sendMessage($this->plugin->formatMessage("§bYou were promoted to officer of§a $factionName!", true));
						}

					/////////////////////////////// DEMOTE ///////////////////////////////

					if($args[0] == "demote") {
						if(!isset($args[1])) {
							$sender->sendMessage($this->plugin->formatMessage("§bUsage: §e/c demote <player>"));
							return true;
						}
						if($this->plugin->isInFaction($sender->getName()) == false) {
							$sender->sendMessage($this->plugin->formatMessage("§cYou must be in a clan to use this"));
							return true;
						}
						if($this->plugin->isLeader($player) == false) {
							$sender->sendMessage($this->plugin->formatMessage("§cYou must be leader to use this"));
							return true;
						}
						if($this->plugin->getPlayerFaction($player) != $this->plugin->getPlayerFaction($args[1])) {
							$sender->sendMessage($this->plugin->formatMessage("§cThat player is not in this clan"));
							return true;
						}
  				  if ($args[1] == $sender->getName()) {
            	$sender->sendMessage($this->plugin->formatMessage("§cYou can't demote yourself"));
              return true;
            }
						if(!$this->plugin->isOfficer($args[1])) {
							$sender->sendMessage($this->plugin->formatMessage("§cPlayer is already Member"));
							return true;
						}
            if(!($this->plugin->getServer()->getPlayer($args[1]) instanceof Player)) {
							$sender->sendMessage($this->plugin->formatMessage("§cPlayer is offline"));
              return true;
						}
						$factionName = $this->plugin->getPlayerFaction($player);
						$stmt = $this->plugin->db->prepare("INSERT OR REPLACE INTO master (player, faction, rank) VALUES (:player, :faction, :rank);");
						$stmt->bindValue(":player", strtolower($args[1]));
						$stmt->bindValue(":faction", $factionName);
						$stmt->bindValue(":rank", "Member");
						$result = $stmt->execute();
						$player = $this->plugin->getServer()->getPlayer($args[1]);
						$sender->sendMessage($this->plugin->formatMessage("§b" . $player->getName() . " §ahas been demoted to Member.", true));
						$this->plugin->getServer()->getPlayer($args[1])->sendMessage($this->plugin->formatMessage("§bYou were demoted to member of§a $factionName", true));
					}

					/////////////////////////////// KICK ///////////////////////////////

					if($args[0] == "kick") {
						if(!isset($args[1])) {
							$sender->sendMessage($this->plugin->formatMessage("§bUsage: §e/c kick <player>"));
							return true;
						}
						if($this->plugin->isInFaction($sender->getName()) == false) {
							$sender->sendMessage($this->plugin->formatMessage("§cYou must be in a clan to use this"));
							return true;
						}
						if($this->plugin->isLeader($player) == false) {
							$sender->sendMessage($this->plugin->formatMessage("§cYou must be leader to use this"));
							return true;
						}
						if($this->plugin->getPlayerFaction($player) != $this->plugin->getPlayerFaction($args[1])) {
							$sender->sendMessage($this->plugin->formatMessage("§cPlayer is not in this clan"));
							return true;
						}
            if ($args[1] == $sender->getName()) {
               $sender->sendMessage($this->plugin->formatMessage("§cYou can't kick yourself"));
               return true;
            }
						$kicked = $this->plugin->getServer()->getPlayer($args[1]);
						$factionName = $this->plugin->getPlayerFaction($player);
						$this->plugin->db->query("DELETE FROM master WHERE player='$args[1]';");
						$sender->sendMessage($this->plugin->formatMessage("§aYou have kicked §b$args[1]a2!", true));
                        $this->plugin->subtractFactionPower($factionName,$this->plugin->prefs->get("PowerGainedPerPlayerInFaction"));
						$players[] = $this->plugin->getServer()->getOnlinePlayers();
						if(in_array($args[1], $players) == true) {
							$this->plugin->getServer()->getPlayer($args[1])->sendMessage($this->plugin->formatMessage("§3You have been kicked from§a $factionName, true"));
							return true;
						}
					}

					/////////////////////////////// INFO ///////////////////////////////
					if(strtolower($args[0]) == 'info') {
						if(isset($args[1])) {
							if( !(ctype_alnum($args[1])) | !($this->plugin->factionExists($args[1]))) {
								$sender->sendMessage($this->plugin->formatMessage("§cClan does not exist"));
								return true;
							}
							$faction = $args[1];
              $power = $this->plugin->getFactionPower($faction);
							$message = $array["message"];
							$leader = $this->plugin->getLeader($faction);
							$numPlayers = $this->plugin->getNumberOfPlayers($faction);
							$sender->sendMessage(TextFormat::BOLD . TextFormat::GRAY . "------*+=+*------");
							$sender->sendMessage(TextFormat::BOLD . TextFormat::GOLD . "»§b $faction §6«");
							$sender->sendMessage("§6Leader:§3 $leader");
							$sender->sendMessage("§6Players:§f $numPlayers");
							$sender->sendMessage("§6Power:§f $power");
							$sender->sendMessage("§6MOTD:§e $message");
							$sender->sendMessage(TextFormat::BOLD . TextFormat::GRAY . "------*+=+*------");
						} else {
							$faction = $this->plugin->getPlayerFaction(strtolower($sender->getName()));
							$result = $this->plugin->db->query("SELECT * FROM motd WHERE faction='$faction';");
							$array = $result->fetchArray(SQLITE3_ASSOC);
              $power = $this->plugin->getFactionPower($faction);
							$message = $array["message"];
							$leader = $this->plugin->getLeader($faction);
							$numPlayers = $this->plugin->getNumberOfPlayers($faction);
							$sender->sendMessage(TextFormat::BOLD . TextFormat::GRAY . "------*+=+*------");
							$sender->sendMessage(TextFormat::BOLD . TextFormat::GOLD . "»§b $faction §6«");
							$sender->sendMessage("§6Leader:§3 $leader");
							$sender->sendMessage("§6Players:§f $numPlayers");
							$sender->sendMessage("§6Power:§f $power");
							$sender->sendMessage("§6MOTD:§e $message");
							$sender->sendMessage(TextFormat::BOLD . TextFormat::GRAY . "------*+=+*------");
						}
					}
					if(strtolower($args[0]) == "help") {//forceunclaim
						if(!isset($args[1]) || $args[1] == 1) {
							$sender->sendMessage("§f====§bClans Help (1/5)§f====" . TextFormat::WHITE . "\n§e/c create <name> §f- Create a Clan for §6$$create
																																													 \n§e/c invite <player> §f- Invite a player to your Clan
																																													 \n§e/c kick <player> §f- Kick a player from your Clan
																																													 \n§e/c accept §f- Accept an invite
																																													 \n§e/c deny §f- Deny an invite");
							return true;
						}
						if($args[1] == 2) {
							$sender->sendMessage("§f====§bClans Help (2/5)§f====" . TextFormat::WHITE . "\n§e/c home §f- Teleport to your Clans home
																																													 \n§e/c help <page> §f- Bring up current menu
																																													 \n§e/c info <clan> §f- View Clan info
																																													 \n§e/c leader <player> §f- Promote a player to leader of your Clan
																																													 \n§e/c leave §f- Leave your Clan
																																													 \n§e/c del §f- Delete your Clan");
							return true;
						}
                        if($args[1] == 3) {
							$sender->sendMessage("§f====§bClans Help (3/5)§f====" . TextFormat::WHITE . "\n§e/c msg §f- Set message of your Clan
																																													 \n§e/c promote <player> §f- Promote a player to Officer
									 																																				 \n§e/c demote <player> §f- Demote a player to member
																																													 \n§e/c sethome §f- Set home for your Clan for §6$$home
									 																																				 \n§e/c claim §f- Claim land for §6$$claim
									 																																				 \n§e/c overclaim §f- Overclaim land of an opposing Clan for §6$$oclaim
																																													 \n§e/c unclaim §f- Unclaim land claimed by your Clan
																																													 \n§e/c unsethome §f- Remove the current home of your Clan");
							return true;
						}
                        if($args[1] == 4) {
              $sender->sendMessage("§f====§bClans Help (4/5)§f====" . TextFormat::WHITE . "\n§e/c power §f- View your Clan's power
																																													 \n§e/c top §f- Who's the top Clan?
																																													 \n§e/c seepower <clan> §f- View Clan power
																																													 \n§e/c ally <clan> §f- Request for an ally for §6$$allyr
																																													 \n§e/c aaccept/aok §f- Accept an ally request and earn §6$$allya
																																													 \n§e/c adeny/ano §f- Deny an ally request
																																													 \n§e/c unally <clan> §f- Unally a Clan
																																													 \n§e/c pos §f- Get Clan info of your current position");
							return true;
						}
                        if($args[1] == 5) {
              $sender->sendMessage("§f====§bClans Help (5/5)§f====" . TextFormat::WHITE . "\n§e/c power §f- View your Clan's power");

                        } else {
                            $sender->sendMessage("§f====§bClans Staff Help Page§f====" . TextFormat::WHITE . "\n§e/c cunclaim <clan> §f- Unclaim all land of target Clan
																																																																 \n§e/c cdel <clan> §f- Delete a Clan
																																																																 \n§e/c caddpower <clan> <number> §f- Add power to a Clan");
							return true;
                        }
					}
				}
				if(count($args == 1)) {

					/////////////////////////////// CLAIM ///////////////////////////////

					if(strtolower($args[0]) == 'claim') {
						if(!$this->plugin->isInFaction($player)) {
							$sender->sendMessage($this->plugin->formatMessage("§cYou must be in a clan to claim"));
							return true;
						}
                        if($this->plugin->prefs->get("OfficersCanClaim")){
                            if(!$this->plugin->isLeader($player) || !$this->plugin->isOfficer($player)) {
							    $sender->sendMessage($this->plugin->formatMessage("§cOnly Leaders and Officers can claim"));
							    return true;
						    }
                        } else {
                            if(!$this->plugin->isLeader($player)) {
							    $sender->sendMessage($this->plugin->formatMessage("§cYou must be leader to use this"));
							    return true;
						    }
                        }
						if(!$this->plugin->isLeader($player)) {
							$sender->sendMessage($this->plugin->formatMessage("§cYou must be leader to use this"));
							return true;
						}

						if($this->plugin->inOwnPlot($sender)) {
							$sender->sendMessage($this->plugin->formatMessage("§aYour clan has already claimed this area."));
							return true;
						}
						$faction = $this->plugin->getPlayerFaction($sender->getPlayer()->getName());
                        if($this->plugin->getNumberOfPlayers($faction) < $this->plugin->prefs->get("PlayersNeededInFactionToClaimAPlot")){

                           $needed_players =  $this->plugin->prefs->get("PlayersNeededInFactionToClaimAPlot") -
                                               $this->plugin->getNumberOfPlayers($faction);
                           $sender->sendMessage($this->plugin->formatMessage("§bYou need §e$needed_players §bmore players to claim"));
				           return true;
                        }
                        if($this->plugin->getFactionPower($faction) < $this->plugin->prefs->get("PowerNeededToClaimAPlot")){
                            $needed_power = $this->plugin->prefs->get("PowerNeededToClaimAPlot");
                            $faction_power = $this->plugin->getFactionPower($faction);
							$sender->sendMessage($this->plugin->formatMessage("§3Your clan doesn't have enough power to claim"));
							$sender->sendMessage($this->plugin->formatMessage("§e"."$needed_power" . " §3power is required. Your clan only has §a$faction_power §3power."));
                            return true;
                        }
						elseif($r = EconomyAPI::getInstance()->reduceMoney($player, $claim)){
						$x = floor($sender->getX());
						$y = floor($sender->getY());
						$z = floor($sender->getZ());
						if($this->plugin->drawPlot($sender, $faction, $x, $y, $z, $sender->getPlayer()->getLevel(), $this->plugin->prefs->get("PlotSize")) == false) {

							return true;
						}

						$sender->sendMessage($this->plugin->formatMessage("§bGetting your coordinates...", true));
                        $plot_size = $this->plugin->prefs->get("PlotSize");
                        $faction_power = $this->plugin->getFactionPower($faction);
						$sender->sendMessage($this->plugin->formatMessage("§aLand successfully claimed for §6$$claim§a.", true));
					}
					else {
						// $r is an error code
						switch($r){
							case EconomyAPI::RET_INVALID:
								# Invalid $amount
								$sender->sendMessage($this->plugin->formatMessage("§3You do not have enough Money to Claim! Need §6$$claim"));
								break;
							case EconomyAPI::RET_CANCELLED:
								# Transaction was cancelled for some reason :/
								$sender->sendMessage($this->plugin->formatMessage("§6-ERROR!"));
								break;
							case EconomyAPI::RET_NO_ACCOUNT:
								$sender->sendMessage($this->plugin->formatMessage("§6-ERROR!"));
								break;
						}
					}
					}
                    if(strtolower($args[0]) == 'pos'){
                        $x = floor($sender->getX());
						$y = floor($sender->getY());
						$z = floor($sender->getZ());
                        $fac = $this->plugin->factionFromPoint($x,$z);
                        $power = $this->plugin->getFactionPower($fac);
                        if(!$this->plugin->isInPlot($sender)){
                            $sender->sendMessage($this->plugin->formatMessage("§bThis area is unclaimed. Use §e/c claim §bto claim", true));
							return true;
                        }
                        $sender->sendMessage($this->plugin->formatMessage("§3This plot is claimed by §a$fac §3with §e$power §3power"));
                    }

                    if(strtolower($args[0]) == 'cdel') {
                        if(!isset($args[1])){
                            $sender->sendMessage($this->plugin->formatMessage("§bUsage: §e/c cdel <clan>"));
                            return true;
                        }
                        if(!$this->plugin->factionExists($args[1])) {
							$sender->sendMessage($this->plugin->formatMessage("§cThe requested faction does not exist"));
                            return true;
						}
                        if(!($sender->isOp())) {
							$sender->sendMessage($this->plugin->formatMessage("§cInsufficient permissions"));
                            return true;
						}
						$this->plugin->db->query("DELETE FROM master WHERE faction='$args[1]';");
						$this->plugin->db->query("DELETE FROM plots WHERE faction='$args[1]';");
            $this->plugin->db->query("DELETE FROM allies WHERE faction1='$args[1]';");
            $this->plugin->db->query("DELETE FROM allies WHERE faction2='$args[1]';");
            $this->plugin->db->query("DELETE FROM strength WHERE faction='$args[1]';");
            $this->plugin->db->query("DELETE FROM motd WHERE faction='$args[1]';");
            $this->plugin->db->query("DELETE FROM home WHERE faction='$args[1]';");
				    $sender->sendMessage($this->plugin->formatMessage("§aClan was successfully deleted. All claimed land is now unclaimed.", true));
                    }
                    if(strtolower($args[0]) == 'caddpower') {
                        if(!isset($args[1]) or !isset($args[2])){
                            $sender->sendMessage($this->plugin->formatMessage("§bUsage: §e/c caddpower <clan> <number>"));
                            return true;
                        }
                        if(!$this->plugin->factionExists($args[1])) {
							$sender->sendMessage($this->plugin->formatMessage("§cThe requested clan does not exist"));
                            return true;
						}
                        if(!($sender->isOp())) {
							$sender->sendMessage($this->plugin->formatMessage("§cInsufficient permissions"));
                            return true;
						}
                        $this->plugin->addFactionPower($args[1],$args[2]);
				        $sender->sendMessage($this->plugin->formatMessage("§aSuccessfully added §e$args[2] power to §b$args[1]", true));
                    }
                    if (strtolower($args[0]) == 'pc') {
                        if (!isset($args[1])) {
                            $sender->sendMessage($this->plugin->formatMessage("Usage: /c pc <player>"));
                            return true;
                        }
                        if (!$this->plugin->isInFaction($args[1])) {
                            $sender->sendMessage($this->plugin->formatMessage("The selected player is not in a clan or doesn't exist."));
                            $sender->sendMessage($this->plugin->formatMessage("Make sure the name of the selected player is ABSOLUTELY EXACT."));
                            return true;
                        }
                        $faction = $this->plugin->getPlayerFaction($args[1]);
                        $sender->sendMessage($this->plugin->formatMessage("-$args[1] is in $faction-", true));
                    }
                    if(strtolower($args[0]) == 'overclaim') {
						if(!$this->plugin->isInFaction($player)) {
							$sender->sendMessage($this->plugin->formatMessage("§cYou must be in a clan to use this"));
							return true;
						}
						if(!$this->plugin->isLeader($player)) {
							$sender->sendMessage($this->plugin->formatMessage("§cYou must be leader to use this"));
							return true;
						}
                        $faction = $this->plugin->getPlayerFaction($player);
						if($this->plugin->getNumberOfPlayers($faction) < $this->plugin->prefs->get("PlayersNeededInFactionToClaimAPlot")){

                           $needed_players =  $this->plugin->prefs->get("PlayersNeededInFactionToClaimAPlot") -
                                               $this->plugin->getNumberOfPlayers($faction);
                           $sender->sendMessage($this->plugin->formatMessage("§3You need §e$needed_players §3more players to overclaim"));
				           return true;
                        }
                        if($this->plugin->getFactionPower($faction) < $this->plugin->prefs->get("PowerNeededToClaimAPlot")){
                            $needed_power = $this->plugin->prefs->get("PowerNeededToClaimAPlot");
                            $faction_power = $this->plugin->getFactionPower($faction);
							$sender->sendMessage($this->plugin->formatMessage("§3Your clan does not have enough power to claim! Get power by killing players!"));
							$sender->sendMessage($this->plugin->formatMessage("§e$needed_power" . "§3 power is required but your clan only has §e$faction_power §3power"));
                            return true;
                        }
						$sender->sendMessage($this->plugin->formatMessage("§bGetting your coordinates...", true));
						$x = floor($sender->getX());
						$y = floor($sender->getY());
						$z = floor($sender->getZ());
                        if($this->plugin->prefs->get("EnableOverClaim")){
                            if($this->plugin->isInPlot($sender)){
                                $faction_victim = $this->plugin->factionFromPoint($x,$z);
                                $faction_victim_power = $this->plugin->getFactionPower($faction_victim);
                                $faction_ours = $this->plugin->getPlayerFaction($player);
                                $faction_ours_power = $this->plugin->getFactionPower($faction_ours);
                                if($this->plugin->inOwnPlot($sender)){
                                    $sender->sendMessage($this->plugin->formatMessage("§aYour clan has already claimed this land"));
                                    return true;
                                } else {
                                    if($faction_ours_power < $faction_victim_power){
                                        $sender->sendMessage($this->plugin->formatMessage("§3Your power level is too low to over claim §b$faction_victim"));
                                        return true;
                                    } elseif($r = EconomyAPI::getInstance()->reduceMoney($player, $oclaim))
									   {
                                        $this->plugin->db->query("DELETE FROM plots WHERE faction='$faction_ours';");
                                        $this->plugin->db->query("DELETE FROM plots WHERE faction='$faction_victim';");
                                        $arm = (($this->plugin->prefs->get("PlotSize")) - 1) / 2;
                                        $this->plugin->newPlot($faction_ours,$x+$arm,$z+$arm,$x-$arm,$z-$arm);
					$sender->sendMessage($this->plugin->formatMessage("§aYour clan has successfully overclaimed the land of §b$faction_victim §afor §6$$oclaim", true));
                                        return true;
                                    }
									else {
						// $r is an error code
						    switch($r){
							case EconomyAPI::RET_INVALID:
								# Invalid $amount
								$sender->sendMessage($this->plugin->formatMessage("§3You do not have enough Money to Overclaim! Need §6$oclaim"));
								break;
							case EconomyAPI::RET_CANCELLED:
								# Transaction was cancelled for some reason :/
								$sender->sendMessage($this->plugin->formatMessage("§6-ERROR!"));
								break;
							case EconomyAPI::RET_NO_ACCOUNT:
								$sender->sendMessage($this->plugin->formatMessage("§6-ERROR!"));
								break;
						}
					}

                                }
                            } else {
                                $sender->sendMessage($this->plugin->formatMessage("§cYou are not in claimed land"));
                                return true;
                            }
                        } else {
                            $sender->sendMessage($this->plugin->formatMessage("§cInsufficient permissions"));
                            return true;
                        }

					}


					/////////////////////////////// UNCLAIM ///////////////////////////////

					if(strtolower($args[0]) == "unclaim") {
                        if(!$this->plugin->isInFaction($sender->getName())) {
							$sender->sendMessage($this->plugin->formatMessage("§cYou must be in a clan to use this"));
							return true;
						}
						if(!$this->plugin->isLeader($sender->getName())) {
							$sender->sendMessage($this->plugin->formatMessage("§cYou must be leader to use this"));
							return true;
						}
						$faction = $this->plugin->getPlayerFaction($sender->getName());
						$this->plugin->db->query("DELETE FROM plots WHERE faction='$faction';");
						$sender->sendMessage($this->plugin->formatMessage("§aLand successfully unclaimed", true));
					}

					/////////////////////////////// MSG ///////////////////////////////

					if(strtolower($args[0]) == "msg") {
						if($this->plugin->isInFaction($sender->getName()) == false) {
							$sender->sendMessage($this->plugin->formatMessage("§cYou must be in a clan to use this"));
							return true;
						}
						if($this->plugin->isLeader($player) == false) {
							$sender->sendMessage($this->plugin->formatMessage("§cYou must be leader to use this"));
							return true;
						}
						$sender->sendMessage($this->plugin->formatMessage("§bType your desired message in chat", true));
						$stmt = $this->plugin->db->prepare("INSERT OR REPLACE INTO motdrcv (player, timestamp) VALUES (:player, :timestamp);");
						$stmt->bindValue(":player", strtolower($sender->getName()));
						$stmt->bindValue(":timestamp", time());
						$result = $stmt->execute();
					}

					/////////////////////////////// ACCEPT ///////////////////////////////

					if(strtolower($args[0]) == "accept") {
						$player = $sender->getName();
						$lowercaseName = strtolower($player);
						$result = $this->plugin->db->query("SELECT * FROM confirm WHERE player='$lowercaseName';");
						$array = $result->fetchArray(SQLITE3_ASSOC);
						if(empty($array) == true) {
							$sender->sendMessage($this->plugin->formatMessage("§cYou have not been invited to any clan"));
							return true;
						}
						$invitedTime = $array["timestamp"];
						$currentTime = time();
						if(($currentTime - $invitedTime) <= 60) { //This should be configurable
							$faction = $array["faction"];
							$stmt = $this->plugin->db->prepare("INSERT OR REPLACE INTO master (player, faction, rank) VALUES (:player, :faction, :rank);");
							$stmt->bindValue(":player", strtolower($player));
							$stmt->bindValue(":faction", $faction);
							$stmt->bindValue(":rank", "Member");
							$result = $stmt->execute();
							$this->plugin->db->query("DELETE FROM confirm WHERE player='$lowercaseName';");
							$sender->sendMessage($this->plugin->formatMessage("§bYou have joined §a§$faction!", true));
                            $this->plugin->addFactionPower($faction,$this->plugin->prefs->get("PowerGainedPerPlayerInFaction"));
							$this->plugin->getServer()->getPlayerExact($array["invitedby"])->sendMessage($this->plugin->formatMessage("§a$player §bjoined the clan!", true));
						} else {
							$sender->sendMessage($this->plugin->formatMessage("§3Invite has timed out"));
							$this->plugin->db->query("DELETE * FROM confirm WHERE player='$player';");
						}
					}

					/////////////////////////////// DENY ///////////////////////////////

					if(strtolower($args[0]) == "deny") {
						$player = $sender->getName();
						$lowercaseName = strtolower($player);
						$result = $this->plugin->db->query("SELECT * FROM confirm WHERE player='$lowercaseName';");
						$array = $result->fetchArray(SQLITE3_ASSOC);
						if(empty($array) == true) {
							$sender->sendMessage($this->plugin->formatMessage("§cYou have not been invited to any clan"));
							return true;
						}
						$invitedTime = $array["timestamp"];
						$currentTime = time();
						if( ($currentTime - $invitedTime) <= 60 ) { //This should be configurable
							$this->plugin->db->query("DELETE * FROM confirm WHERE player='$lowercaseName';");
							$sender->sendMessage($this->plugin->formatMessage("§3Invite declined!", true));
							$this->plugin->getServer()->getPlayerExact($array["invitedby"])->sendMessage($this->plugin->formatMessage("§b$player §3declined the invite!"));
						} else {
							$sender->sendMessage($this->plugin->formatMessage("§3Invite has timed out!"));
							$this->plugin->db->query("DELETE * FROM confirm WHERE player='$lowercaseName';");
						}
					}

					/////////////////////////////// DELETE ///////////////////////////////

					if(strtolower($args[0]) == "del") {
						if($this->plugin->isInFaction($player) == true) {
							if($this->plugin->isLeader($player)) {
								$faction = $this->plugin->getPlayerFaction($player);
                $this->plugin->db->query("DELETE FROM plots WHERE faction='$faction';");
								$this->plugin->db->query("DELETE FROM master WHERE faction='$faction';");
                $this->plugin->db->query("DELETE FROM allies WHERE faction1='$faction';");
            		$this->plugin->db->query("DELETE FROM allies WHERE faction2='$faction';");
              	$this->plugin->db->query("DELETE FROM strength WHERE faction='$faction';");
                $this->plugin->db->query("DELETE FROM motd WHERE faction='$faction';");
                $this->plugin->db->query("DELETE FROM home WHERE faction='$faction';");
								$sender->sendMessage($this->plugin->formatMessage("§aClan has been disbanded and all claimed land is now unclaimed", true));
							} else {
								$sender->sendMessage($this->plugin->formatMessage("§cYou are not leader"));
							}
						} else {
							$sender->sendMessage($this->plugin->formatMessage("§cYou are not in a clan"));
						}
					}

					/////////////////////////////// LEAVE ///////////////////////////////

					if(strtolower($args[0] == "leave")) {
						if($this->plugin->isLeader($player) == false) {
							$remove = $sender->getPlayer()->getNameTag();
							$faction = $this->plugin->getPlayerFaction($player);
							$name = $sender->getName();
							$this->plugin->db->query("DELETE FROM master WHERE player='$name';");
							$sender->sendMessage($this->plugin->formatMessage("§aYou successfully left §b$faction", true));

                            $this->plugin->subtractFactionPower($faction,$this->plugin->prefs->get("PowerGainedPerPlayerInFaction"));
						} else {
							$sender->sendMessage($this->plugin->formatMessage("§cYou must delete your clan or give leadership to someone else first"));
						}
					}

					/////////////////////////////// SETHOME ///////////////////////////////

					if(strtolower($args[0] == "sethome")) {
						if(!$this->plugin->isInFaction($player)) {
							$sender->sendMessage($this->plugin->formatMessage("§cYou must be in a clan to do this"));
							return true;
						}
						if(!$this->plugin->isLeader($player)) {
							$sender->sendMessage($this->plugin->formatMessage("§cYou must be leader to set home"));
							return true;
						}

                        $faction_power = $this->plugin->getFactionPower($this->plugin->getPlayerFaction($player));
                        $needed_power = $this->plugin->prefs->get("PowerNeededToSetOrUpdateAHome");
                        if($faction_power < $needed_power){
                            $sender->sendMessage($this->plugin->formatMessage("§3Your clan doesn't have enough power set a home. Get power by killing players!"));
                            $sender->sendMessage($this->plugin->formatMessage("§e $needed_power §3power is required to set a home. Your clan has §e$faction_power §3power."));
							return true;
                        }
						elseif($r = EconomyAPI::getInstance()->reduceMoney($player, $home)){
						$factionName = $this->plugin->getPlayerFaction($sender->getName());
						$stmt = $this->plugin->db->prepare("INSERT OR REPLACE INTO home (faction, x, y, z) VALUES (:faction, :x, :y, :z);");
						$stmt->bindValue(":faction", $factionName);
						$stmt->bindValue(":x", $sender->getX());
						$stmt->bindValue(":y", $sender->getY());
						$stmt->bindValue(":z", $sender->getZ());
						$result = $stmt->execute();
						$sender->sendMessage($this->plugin->formatMessage("§bClan home set for §6$$home", true));}
						else {
						// $r is an error code
						    switch($r){
							case EconomyAPI::RET_INVALID:
								# Invalid $amount
								$sender->sendMessage($this->plugin->formatMessage("§3You do not have enough Money to set Clan Home! Need§6 $$home"));
								break;
							case EconomyAPI::RET_CANCELLED:
								# Transaction was cancelled for some reason :/
								$sender->sendMessage($this->plugin->formatMessage("§6-ERROR!"));
								break;
							case EconomyAPI::RET_NO_ACCOUNT:
								$sender->sendMessage($this->plugin->formatMessage("§6-ERROR!"));
								break;
						}
					}
					}

					/////////////////////////////// UNSETHOME ///////////////////////////////

					if(strtolower($args[0] == "unsethome")) {
						if(!$this->plugin->isInFaction($player)) {
							$sender->sendMessage($this->plugin->formatMessage("§cYou must be in a clan to do this"));
							return true;
						}
						if(!$this->plugin->isLeader($player)) {
							$sender->sendMessage($this->plugin->formatMessage("§cYou must be leader to unset home"));
							return true;
						}
						$faction = $this->plugin->getPlayerFaction($sender->getName());
						$this->plugin->db->query("DELETE FROM home WHERE faction = '$faction';");
						$sender->sendMessage($this->plugin->formatMessage("§aHome unset succeed", true));
					}

					/////////////////////////////// HOME ///////////////////////////////

					if(strtolower($args[0] == "home")) {
						if(!$this->plugin->isInFaction($player)) {
							$sender->sendMessage($this->plugin->formatMessage("§cYou must be in a clan to do this."));
                            return true;
						}
						$faction = $this->plugin->getPlayerFaction($sender->getName());
						$result = $this->plugin->db->query("SELECT * FROM home WHERE faction = '$faction';");
						$array = $result->fetchArray(SQLITE3_ASSOC);
						if(!empty($array)) {
							$sender->getPlayer()->teleport(new Vector3($array['x'], $array['y'], $array['z']));
							$sender->sendMessage($this->plugin->formatMessage("§bTeleported to home.", true));
							return true;
						} else {
							$sender->sendMessage($this->plugin->formatMessage("§3Clan home has not been set"));
				        }
				    }
                    /////////////////////////////// MEMBERS/OFFICERS/LEADER AND THEIR STATUSES ///////////////////////////////
                    if (strtolower($args[0] == "ourmembers")) {
                        if (!$this->plugin->isInFaction($player)) {
                            $sender->sendMessage($this->plugin->formatMessage("§cYou must be in a clan to do this"));
                            return true;
                        }
                        $this->plugin->getPlayersInFactionByRank($sender, $this->plugin->getPlayerFaction($player), "Member");
                    }
                    if (strtolower($args[0] == "membersof")) {
                        if (!isset($args[1])) {
                            $sender->sendMessage($this->plugin->formatMessage("§bUsage:§e /c membersof <clan>"));
                            return true;
                        }
                        if (!$this->plugin->factionExists($args[1])) {
                            $sender->sendMessage($this->plugin->formatMessage("§cThe requested clan doesn't exist"));
                            return true;
                        }
                        $this->plugin->getPlayersInFactionByRank($sender, $args[1], "Member");
                    }
                    if (strtolower($args[0] == "ourofficers")) {
                        if (!$this->plugin->isInFaction($player)) {
                            $sender->sendMessage($this->plugin->formatMessage("§cYou must be in a clan to do this"));
                            return true;
                        }
                        $this->plugin->getPlayersInFactionByRank($sender, $this->plugin->getPlayerFaction($player), "Officer");
                    }
                    if (strtolower($args[0] == "officersof")) {
                        if (!isset($args[1])) {
                            $sender->sendMessage($this->plugin->formatMessage("§bUsage:§e /c officersof <clan>"));
                            return true;
                        }
                        if (!$this->plugin->factionExists($args[1])) {
													$sender->sendMessage($this->plugin->formatMessage("§cThe requested clan doesn't exist"));
                            return true;
                        }
                        $this->plugin->getPlayersInFactionByRank($sender, $args[1], "Officer");
                    }
                    if (strtolower($args[0] == "ourleader")) {
                        if (!$this->plugin->isInFaction($player)) {
                            $sender->sendMessage($this->plugin->formatMessage("§cYou must be in a clan to do this"));
                            return true;
                        }
                        $this->plugin->getPlayersInFactionByRank($sender, $this->plugin->getPlayerFaction($player), "Leader");
                    }
                    if (strtolower($args[0] == "leaderof")) {
                        if (!isset($args[1])) {
                            $sender->sendMessage($this->plugin->formatMessage("§bUsage:§e /c leaderof <clan>"));
                            return true;
                        }
                        if (!$this->plugin->factionExists($args[1])) {
                            $sender->sendMessage($this->plugin->formatMessage("§cThe requested clan doesn't exist"));
                            return true;
                        }
                        $this->plugin->getPlayersInFactionByRank($sender, $args[1], "Leader");
                    }
                    if (strtolower($args[0] == "say")) {
                        if (!($this->plugin->isInFaction($player))) {

                            $sender->sendMessage($this->plugin->formatMessage("§cYou must be in a clan to send clan messages"));
                            return true;
                        }
                        $r = count($args);
                        $row = array();
                        $rank = "";
                        $f = $this->plugin->getPlayerFaction($player);

                        if ($this->plugin->isOfficer($player)) {
                            $rank = "*";
                        } else if ($this->plugin->isLeader($player)) {
                            $rank = "**";
                        }
                        $message = "-> ";
                        for ($i = 0; $i < $r - 1; $i = $i + 1) {
                            $message = $message . $args[$i + 1] . " ";
                        }
                        $result = $this->plugin->db->query("SELECT * FROM master WHERE faction='$f';");
                        for ($i = 0; $resultArr = $result->fetchArray(SQLITE3_ASSOC); $i = $i + 1) {
                            $row[$i]['player'] = $resultArr['player'];
                            $p = $this->plugin->getServer()->getPlayerExact($row[$i]['player']);
                            if ($p instanceof Player) {
                                $p->sendMessage(TextFormat::ITALIC . TextFormat::RED . "<FM>" . TextFormat::AQUA . " <$rank$f> " . TextFormat::GREEN . "<$player> " . ": " . TextFormat::RESET);
                                $p->sendMessage(TextFormat::ITALIC . TextFormat::DARK_AQUA . $message . TextFormat::RESET);
                            }
                        }
                    }


                    ////////////////////////////// ALLY SYSTEM ////////////////////////////////
			if (strtolower($args[0] == "enemy")) {
                        if (!isset($args[1])) {
                            $sender->sendMessage($this->plugin->formatMessage("§bUsage:§e /c enemy <clan>"));
                            return true;
                        }
                        if (!$this->plugin->isInFaction($player)) {
                            $sender->sendMessage($this->plugin->formatMessage("§cYou must be in a clan to do this"));
                            return true;
                        }
                        if (!$this->plugin->isLeader($player)) {
                            $sender->sendMessage($this->plugin->formatMessage("§cYou must be the leader to do this"));
                            return true;
                        }
                        if (!$this->plugin->factionExists($args[1])) {
                            $sender->sendMessage($this->plugin->formatMessage("§cThe requested clan doesn't exist"));
                            return true;
                        }
                        if ($this->plugin->getPlayerFaction($player) == $args[1]) {
                            $sender->sendMessage($this->plugin->formatMessage("§cYour clan can not enemy with itself"));
                            return true;
                        }
                        if ($this->plugin->areAllies($this->plugin->getPlayerFaction($player), $args[1])) {
                            $sender->sendMessage($this->plugin->formatMessage("§cYour faction is already enemied with $args[1]"));
                            return true;
                        }
                        $fac = $this->plugin->getPlayerFaction($player);
                        $leader = $this->plugin->getServer()->getPlayerExact($this->plugin->getLeader($args[1]));

                        if (!($leader instanceof Player)) {
                            $sender->sendMessage($this->plugin->formatMessage("§cThe leader of the requested clan is offline"));
                            return true;
                        }
                        $this->plugin->setEnemies($fac, $args[1]);
                        $sender->sendMessage($this->plugin->formatMessage("§bYou are now §cenemies §bwith §a$args[1]!", true));
                        $leader->sendMessage($this->plugin->formatMessage("§eThe leader of §a$fac has declared your clan as an enemy", true));
                    }
                    if(strtolower($args[0] == "ally")){
                        if(!isset($args[1])){
                            $sender->sendMessage($this->plugin->formatMessage("§bUsage:§e /c ally <clan>"));
                            return true;
                        }
                        if(!$this->plugin->isInFaction($player)) {
													  $sender->sendMessage($this->plugin->formatMessage("§cYou must be in a clan to do this"));
                            return true;
						}
                        if(!$this->plugin->isLeader($player)) {
													  $sender->sendMessage($this->plugin->formatMessage("§cYou must be the leader to do this"));
                            return true;
						}
                        if(!$this->plugin->factionExists($args[1])) {
													  $sender->sendMessage($this->plugin->formatMessage("§cThe requested clan does not exist"));
                            return true;
						}
                        if($this->plugin->getPlayerFaction($player) == $args[1]){
                            $sender->sendMessage($this->plugin->formatMessage("§cYour clan can not ally itself"));
                            return true;
                        }
                        if($this->plugin->areAllies($this->plugin->getPlayerFaction($player),$args[1])){
                            $sender->sendMessage($this->plugin->formatMessage("§3Your clan is already allied with §b$args[1]!"));
                            return true;
                        }
                        $fac = $this->plugin->getPlayerFaction($player);
						$leader = $this->plugin->getServer()->getPlayerExact($this->plugin->getLeader($args[1]));
                        if(!($leader instanceof Player)){
                            $sender->sendMessage($this->plugin->formatMessage("§3The leader of the target clan is offline"));
                            return true;
                        }
                        if ($this->plugin->getAlliesCount($args[1]) >= $this->plugin->getAlliesLimit()) {
                            $sender->sendMessage($this->plugin->formatMessage("The requested clan has the maximum amount of allies", false));
                            return true;
                        }
                        if ($this->plugin->getAlliesCount($fac) >= $this->plugin->getAlliesLimit()) {
                            $sender->sendMessage($this->plugin->formatMessage("Your clan has the maximum amount of allies", false));
                            return true;
                        }
                        elseif($r = EconomyAPI::getInstance()->reduceMoney($player, $allyr)){
                        $stmt = $this->plugin->db->prepare("INSERT OR REPLACE INTO alliance (player, faction, requestedby, timestamp) VALUES (:player, :faction, :requestedby, :timestamp);");
				        $stmt->bindValue(":player", $leader->getName());
				        $stmt->bindValue(":faction", $args[1]);
				        $stmt->bindValue(":requestedby", $sender->getName());
				        $stmt->bindValue(":timestamp", time());
				        $result = $stmt->execute();
                        $sender->sendMessage($this->plugin->formatMessage("§bYour clan has requested to ally with §a$args[1]",true));
                        $leader->sendMessage($this->plugin->formatMessage("§a $fac §bhas requested to ally. Type §e/c aaccept §bto accept or §e/c adeny §bto deny.",true));
                        }
							else {
						// $r is an error code
						    switch($r){
							case EconomyAPI::RET_INVALID:
								# Invalid $amount
								$sender->sendMessage($this->plugin->formatMessage("§3You do not have enough Money to send a Ally Request! Need §6$$allyr"));
								break;
							case EconomyAPI::RET_CANCELLED:
								# Transaction was cancelled for some reason :/
								$sender->sendMessage($this->plugin->formatMessage("§6-ERROR!"));
								break;
							case EconomyAPI::RET_NO_ACCOUNT:
								$sender->sendMessage($this->plugin->formatMessage("§6-ERROR!"));
								break;
						}
					}

                    }
                    if(strtolower($args[0] == "unally") or strtolower($args[0]) == "allydel"){
                        if(!isset($args[1])){
                            $sender->sendMessage($this->plugin->formatMessage("§bUsage: §e/c unally/allydel <clan>"));
                            return true;
                        }
                        if(!$this->plugin->isInFaction($player)) {
							$sender->sendMessage($this->plugin->formatMessage("§cYou must be in a clan to do this"));
                            return true;
						}
                        if(!$this->plugin->isLeader($player)) {
							$sender->sendMessage($this->plugin->formatMessage("§cYou must be the leader to do this"));
                            return true;
						}
                        if(!$this->plugin->factionExists($args[1])) {
							$sender->sendMessage($this->plugin->formatMessage("§cThe requested clan does not exist"));
                            return true;
						}
                        if($this->plugin->getPlayerFaction($player) == $args[1]){
                            $sender->sendMessage($this->plugin->formatMessage("§cYour clan cannot unally itself."));
                            return true;
                        }
                        if(!$this->plugin->areAllies($this->plugin->getPlayerFaction($player),$args[1])){
                            $sender->sendMessage($this->plugin->formatMessage("§cYour clan is not allied with §b$args[1]"));
                            return true;
                        }
                        $fac = $this->plugin->getPlayerFaction($player);
						$leader= $this->plugin->getServer()->getPlayerExact($this->plugin->getLeader($args[1]));
                        $this->plugin->deleteAllies($fac,$args[1]);
                        $this->plugin->deleteAllies($args[1],$fac);
                        $this->plugin->subtractFactionPower($fac,$this->plugin->prefs->get("PowerGainedPerAlly"));
                        $this->plugin->subtractFactionPower($args[1],$this->plugin->prefs->get("PowerGainedPerAlly"));
                        $this->plugin->updateAllies($fac);
                        $this->plugin->updateAllies($args[1]);
                        $sender->sendMessage($this->plugin->formatMessage("§bYour clan §a$fac §bis no longer allied with §a$args[1]§b!",true));
                        if($leader instanceof Player){
                            $leader->sendMessage($this->plugin->formatMessage("§a $fac §bhas unallied with your clan §a$args[1]",false));
                        }


                    }
                    if(strtolower($args[0] == "cunclaim") or strtolower($args[0]) == "cdel"){
                        if(!isset($args[1])){
                            $sender->sendMessage($this->plugin->formatMessage("§bUsage: §e/c cunclaim/cdel <clan>"));
                            return true;
                        }
                        if(!$this->plugin->factionExists($args[1])) {
							$sender->sendMessage($this->plugin->formatMessage("§cThe requested clan does not exist"));
                            return true;
						}
                        if(!($sender->isOp())) {
							$sender->sendMessage($this->plugin->formatMessage("§cInsufficient permissions"));
                            return true;
						}
				        $sender->sendMessage($this->plugin->formatMessage("§bLand of §a$args[1]§b unclaimed"));
                        $this->plugin->db->query("DELETE FROM plots WHERE faction='$args[1]';");

                    }

                    if (strtolower($args[0] == "allies")) {
                        if (!isset($args[1])) {
                            if (!$this->plugin->isInFaction($player)) {
                                $sender->sendMessage($this->plugin->formatMessage("§cYou must be in a clan to do this"));
                                return true;
                            }

                            $this->plugin->updateAllies($this->plugin->getPlayerFaction($player));
                            $this->plugin->getAllAllies($sender, $this->plugin->getPlayerFaction($player));
                        } else {
                            if (!$this->plugin->factionExists($args[1])) {
                                $sender->sendMessage($this->plugin->formatMessage("§cThe requested clan doesn't exist"));
                                return true;
                            }
                            $this->plugin->updateAllies($args[1]);
                            $this->plugin->getAllAllies($sender, $args[1]);
                        }
                    }
                    if(strtolower($args[0] == "aaccept") or strtolower($args[0]) == "ayes"){
                        if(!$this->plugin->isInFaction($player)) {
							$sender->sendMessage($this->plugin->formatMessage("§cYou must be in a clan to do this"));
                            return true;
						}
                        if(!$this->plugin->isLeader($player)) {
							$sender->sendMessage($this->plugin->formatMessage("§cYou must be a leader to do this"));
                            return true;
						}
						$lowercaseName = strtolower($player);
						$result = $this->plugin->db->query("SELECT * FROM alliance WHERE player='$lowercaseName';");
						$array = $result->fetchArray(SQLITE3_ASSOC);
						if(empty($array) == true) {
							$sender->sendMessage($this->plugin->formatMessage("§3Your clan has not been received any ally requests"));
							return true;
						}
						$allyTime = $array["timestamp"];
						$currentTime = time();
						if(($currentTime - $allyTime) <= 60) {
                            if($r = EconomyAPI::getInstance()->addMoney($player, $allya)){
                            $requested_fac = $this->plugin->getPlayerFaction($array["requestedby"]);
                            $sender_fac = $this->plugin->getPlayerFaction($player);
							$this->plugin->setAllies($requested_fac,$sender_fac);
							$this->plugin->setAllies($sender_fac,$requested_fac);
                            $this->plugin->addFactionPower($sender_fac,$this->plugin->prefs->get("PowerGainedPerAlly"));
                            $this->plugin->addFactionPower($requested_fac,$this->plugin->prefs->get("PowerGainedPerAlly"));
							$this->plugin->db->query("DELETE FROM alliance WHERE player='$lowercaseName';");
							$sender->sendMessage($this->plugin->formatMessage("§bYour clan is now allied with§a $requested_fac", true));
							$this->plugin->getServer()->getPlayerExact($array["requestedby"])->sendMessage($this->plugin->formatMessage("§a$player §bfrom §a$sender_fac §bhas accepted the alliance!", true));
                            }
						} else {
							$sender->sendMessage($this->plugin->formatMessage("§3Request has timed out"));
							$this->plugin->db->query("DELETE * FROM alliance WHERE player='$lowercaseName';");
						}

                    }
                    if(strtolower($args[0]) == "adeny") {
                        if(!$this->plugin->isInFaction($player)) {
							$sender->sendMessage($this->plugin->formatMessage("§cYou must be in a clan to do this"));
                            return true;
						}
                        if(!$this->plugin->isLeader($player)) {
							$sender->sendMessage($this->plugin->formatMessage("§cYou must be a leader to do this"));
                            return true;
						}
						$lowercaseName = strtolower($player);
						$result = $this->plugin->db->query("SELECT * FROM alliance WHERE player='$lowercaseName';");
						$array = $result->fetchArray(SQLITE3_ASSOC);
						if(empty($array) == true) {
							$sender->sendMessage($this->plugin->formatMessage("§3Your clan has not received any ally requests"));
							return true;
						}
						$allyTime = $array["timestamp"];
						$currentTime = time();
						if( ($currentTime - $allyTime) <= 60 ) { //This should be configurable
                            $requested_fac = $this->plugin->getPlayerFaction($array["requestedby"]);
                            $sender_fac = $this->plugin->getPlayerFaction($player);
							$this->plugin->db->query("DELETE FROM alliance WHERE player='$lowercaseName';");
							$sender->sendMessage($this->plugin->formatMessage("§bYour clan has declined the ally request.", true));
							$this->plugin->getServer()->getPlayerExact($array["requestedby"])->sendMessage($this->plugin->formatMessage("§a$player §3from§a $sender_fac §3has declined the alliance!"));

						} else {
							$sender->sendMessage($this->plugin->formatMessage("§3Request has timed out"));
							$this->plugin->db->query("DELETE * FROM alliance WHERE player='$lowercaseName';");
						}
					}


                    /////////////////////////////// POWER ///////////////////////////////
                    if(strtolower($args[0] == "power")) {
                        if(!$this->plugin->isInFaction($player)) {
							$sender->sendMessage($this->plugin->formatMessage("§cYou must be in a clan to do this"));
                            return true;
						}
                        $faction_power = $this->plugin->getFactionPower($this->plugin->getPlayerFaction($sender->getName()));

                        $sender->sendMessage($this->plugin->formatMessage("§bYour clan has§e $faction_power §bpower",true));
                    }
                    if(strtolower($args[0] == "seepower")) {
                        if(!isset($args[1])){
                            $sender->sendMessage($this->plugin->formatMessage("§bUsage: §e/c seepower <clan>"));
                            return true;
                        }
                        if(!$this->plugin->factionExists($args[1])) {
							$sender->sendMessage($this->plugin->formatMessage("§cClan does not exist"));
                            return true;
						}
                        $faction_power = $this->plugin->getFactionPower($args[1]);
                        $sender->sendMessage($this->plugin->formatMessage("§a$args[1] §bhas §e$faction_power §bpower.",true));
                    }


					/////////////////////////////// ABOUT ///////////////////////////////

					if(strtolower($args[0] == 'about')) {
						$sender->sendMessage(TextFormat::GREEN . "Custom Clan plug made for " . TextFormat::AQUA . "PureCraftPE");
					}
					 /////////////////////////////// TOPFACTIONS ///////////////////////////////

					if(strtolower($args[0]) == 'top') {
						$result = $this->plugin->db->query("SELECT * FROM strength ORDER BY power DESC LIMIT 8;");
						 $i = 1;

						while($row = $result->fetchArray(SQLITE3_ASSOC)){
							if($this->plugin->factionExists($row['faction'])) {
						    $fac = $row['faction'];
							$res = $this->plugin->db->query("SELECT * FROM motd WHERE faction='$fac';");
							$arr = $res->fetchArray(SQLITE3_ASSOC);
							$motd = $arr["message"];
							$lead = $this->plugin->getLeader($row['faction']);
							$num = $this->plugin->getNumberOfPlayers($row['faction']);
							$pow = $this->plugin->getFactionPower($row['faction']);
							$sender->sendMessage(TextFormat::BOLD . $i . ". §6$fac\n§4Leader:§f $lead §ePlayers:§f $num §bPower:§f $pow §5MOTD: §o§f$motd\n");
						    $i++;
						}
						}
					}
                    ////////////////////////////// CHAT ////////////////////////////////
                    if (strtolower($args[0]) == "chat" or strtolower($args[0]) == "c") {

                        if ($this->plugin->isInFaction($player)) {
                            if (isset($this->plugin->factionChatActive[$player])) {
                                unset($this->plugin->factionChatActive[$player]);
                                $sender->sendMessage($this->plugin->formatMessage("§cNo members online", false));
                                return true;
                            } else {
                                $this->plugin->factionChatActive[$player] = 1;
                                $sender->sendMessage($this->plugin->formatMessage("§aClan chat enabled", false));
                                return true;
                            }
                        } else {
                            $sender->sendMessage($this->plugin->formatMessage("§cYou are not in a clan"));
                            return true;
                        }
                    }
                    if (strtolower($args[0]) == "allychat" or strtolower($args[0]) == "ac") {

                        if ($this->plugin->isInFaction($player)) {
                            if (isset($this->plugin->allyChatActive[$player])) {
                                unset($this->plugin->allyChatActive[$player]);
                                $sender->sendMessage($this->plugin->formatMessage("§cAlly chat disabled", false));
                                return true;
                            } else {
                                $this->plugin->allyChatActive[$player] = 1;
                                $sender->sendMessage($this->plugin->formatMessage("§aAlly chat enabled", false));
                                return true;
                            }
					 /////////////////////////////// MEMBERS ///////////////////////////////

				if(strtolower($args[0]) == 'members') {
						if(!$this->plugin->isInFaction($player)) {
							$sender->sendMessage($this->plugin->formatMessage("§cYou must be in a clan to do this."));
                            return true;
						} else{
						$fact1 = $this->plugin->getPlayerFaction(strtolower($sender->getName()));
						$lead = $this->plugin->getLeader($fact1);
						$num = $this->plugin->getNumberOfPlayers($fact1);
						$pow = $this->plugin->getFactionPower($fact1);
						$sender->sendMessage(TextFormat::BOLD . "§aClan: §6§o$fact1 §4Leader:§f $lead §ePlayers:§f $num §bPower:§f $pow\n");
						$result = $this->plugin->db->query("SELECT * FROM master WHERE faction='$fact1' ORDER BY rank DESC;");
						$i = 1;
						while($row = $result->fetchArray(SQLITE3_ASSOC)){
						$rank1 = $row['rank'];
						$play = $row['player'];
						$sender->sendMessage(TextFormat::WHITE . $i . "§b$play -> §a$rank1\n");
						 $i++;
						}
					}
				}
			}
		}
					else {
			        $this->plugin->getServer()->getLogger()->info($this->plugin->formatMessage("Command must be run ingame"));}
		}
    }
}
}
}

