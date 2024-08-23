<?php

namespace takesi;

use pocketmine\player\GameMode;
use pocketmine\entity\effect\VanillaEffects;
use pocketmine\scheduler\Task;
use pocketmine\Server;
use pocketmine\utils\Config;
use pocketmine\permission\DefaultPermissionNames;
use takesi\main;

class EachTask extends Task
{

    public $plugin;

    public function __construct(Main $plugin)
    {
        $this->plugin = $plugin;
    }

    public function getPlugin()
    {
        return $this->plugin;
    }

    public function onRun(): void
    {
        $players = Server::getInstance()->getOnlinePlayers();
        $time = date("Y-m-d H:i:s") . "(JST)";
        foreach ($players as $player) {

            if ($player->getEffects()->has(VanillaEffects::INVISIBILITY()) && !$player->hasPermission(DefaultPermissionNames::GROUP_OPERATOR)) {
                $player->getEffects()->remove(VanillaEffects::INVISIBILITY());
            }

            $item = $player->getInventory()->getItemInHand();
            $player->sendPopup("INFO\n" . "DATE : " . $time . "\nITEM : " . $item->getName() . "\nYOUR POSITION : " . "X>" . $player->getPosition()->getX() . " Y>" . $player->getPosition()->getY() . " Z>" . $player->getPosition()->getZ() . "\nWORLD : " . $player->getWorld()->getFolderName() . "\n");

            if ($player->getWorld()->getFolderName() != $player->getName() and !$player->hasPermission(DefaultPermissionNames::GROUP_OPERATOR)) {
                $this->config = new Config($this->getPlugin()->getDataFolder() . $player->getWorld()->getFolderName() . ".yml", Config::YAML);
                if ($this->config->exists("invited_" . $player->getName())) {
                    if ($player->getGamemode() == GameMode::SPECTATOR()) {
                        $player->setGamemode(GameMode::ADVENTURE());
                    }
                } else {
                    $player->setGamemode(GameMode::ADVENTURE());
                }
            }
        }
        //$this->getPlugin()->removeTask($this->getTaskId());
    }
}
