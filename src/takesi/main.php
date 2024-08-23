<?php

namespace takesi;

use pocketmine\block\SnowLayer;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\entity\Effect;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\block\BlockPlaceEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\entity\EntityTeleportEvent;
use pocketmine\event\entity\EntitySpawnEvent;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\permission\DefaultPermissionNames;
use pocketmine\player\GameMode;
use pocketmine\player\Player;
use pocketmine\plugin\PluginBase;
use pocketmine\utils\Config;
use pocketmine\utils\TextFormat;
use pocketmine\world\Position;
use pocketmine\world\WorldCreationOptions;
use pocketmine\world\generator\GeneratorManager;
use takesi\EachTask;
/*
 * use tokyo\pmmp\libform\element\Button;
 * use tokyo\pmmp\libform\FormApi;
 */
use function Sodium\crypto_aead_aes256gcm_decrypt;

class main extends PluginBase implements Listener
{

    //public $player_touch_time = array();

    protected function onEnable(): void{
        $this->getLogger()->info(TextFormat::WHITE . "I've been enabled!");
        $this->getServer()->getPluginManager()->registerEvents($this, $this);
        $this->getScheduler()->scheduleRepeatingTask(new EachTask($this), 10);
        if (!file_exists($this->getDataFolder())) {
            mkdir($this->getDataFolder(), 0744, true);
        }
        //FormAPI::register($this);
        $geneList = GeneratorManager::getInstance()->getGeneratorList();
        $this->getLogger()->info(TextFormat::WHITE . implode($geneList));
        date_default_timezone_set('Asia/Tokyo');
    }

    /*
    public function callback($player, $response): void
    {
        if (FormApi::FormCancelled($response)) {
            // formがキャンセルされていれば
            $this->getLogger()->info("form was cancelled.");
        } else {
            // formがキャンセルされていなければ
            var_dump($response);
            switch ($response) {
                case 0:
                    $player->sendMessage("§l§eワールド管理システム>>自分のワールドに戻っています...");
                    $this->goWorld($player, $this->getServer()->getWorldManager()->getWorldByName($player->getName()), $player->getName());
                    break;
                case 1:
                    $player->sendMessage("§l§eワールド管理システム>>ロビー(world)に戻っています...");
                    $this->goWorld($player, $this->getServer()->getWorldManager()->getWorldByName("world"), $player->getName());
                    break;
            }
        }
    }
    */

    public function onJoin(PlayerJoinEvent $event): void
    {
        $player = $event->getPlayer();
        if ($this->exsistlevel($player->getName())) {
            if (!file_exists($this->getDataFolder() . $player->getName() . ".yml")) {
                new Config($this->getDataFolder() . $player->getName() . ".yml", Config::YAML, array(
                    'spawn_point_x' => 0,
                    'spawn_point_y' => 10,
                    'spawn_point_z' => 0,
                    'time_set' => 4000,
                    'time_stop' => true,
                    'weather' => 0,
                ));
            }
            $this->config = new Config($this->getDataFolder() . $player->getName() . ".yml", Config::YAML);
            if (!$this->config->exists("allow_attack")) {
                $this->config->set("allow_attack", false);
                $this->config->save();
            }
            //$player->teleport(new Position(-1, 8, 2, $this->getServer()->getWorldManager()->getDefaultWorld()));//for old world
            $player->teleport(new Position(0, 10, 0, $this->getServer()->getWorldManager()->getDefaultWorld())); //for old world
            $player->setGamemode(GameMode::SURVIVAL());
            $player->sendMessage("[§eSYSTEM§r] " . $player->getName() . "さん、おかえり！");
        } else {
            if (!file_exists($this->getDataFolder() . $player->getName() . ".yml")) {
                new Config($this->getDataFolder() . $player->getName() . ".yml", Config::YAML, array(
                    'spawn_point_x' => 0,
                    'spawn_point_y' => 10,
                    'spawn_point_z' => 0,
                    'time_set' => 4000,
                    'time_stop' => true,
                    'weather' => 0,
                ));
            }
            $options = new WorldCreationOptions();
            $options->setGeneratorClass("pocketmine\\world\\generator\\Flat");
            $this->getServer()->getWorldManager()->generateWorld($player->getName(), $options);
            $this->getServer()->getWorldManager()->loadWorld($player->getName());
            $player->teleport(new Position(0, 10, 0, $this->getServer()->getWorldManager()->getDefaultWorld()));
            $player->setGamemode(GameMode::SURVIVAL());
            $player->sendMessage("[§eSYSTEM§r] 生徒サーバーへようこそ");
            $player->sendMessage("[§eSYSTEM§r] このサーバーは§b建築サーバー§rです！");
        }
    }

    public function onBreak(BlockBreakEvent $event): void
    {
        $player = $event->getPlayer();
        $level = $player->getWorld();
        $block = $event->getBlock();
        if (!($player->getName() == $level->getFolderName())) {
            if (!$player->hasPermission(DefaultPermissionNames::GROUP_OPERATOR)) {
                $this->config = new Config($this->getDataFolder() . $level->getFolderName() . ".yml", Config::YAML);
                if (!($this->config->exists("invited_" . $player->getName()))) {
                    if ($block->getName() == "SnowLayer") {
                        if (!($player->getInventory()->getItemInHand()->getVanillaName() == "Diamond Shovel")) {
                            $player->sendMessage("§l§cワールド管理システム>>破壊権限がありません。");
                            $event->cancel();
                        }
                    } else {
                        $player->sendMessage("§l§cワールド管理システム>>破壊権限がありません。");
                        $event->cancel();
                    }
                }
            }
        }
    }

    public function onPlace(BlockPlaceEvent $event): void
    {
        $player = $event->getPlayer();
        $level = $player->getWorld();
        $item = $player->getInventory()->getItemInHand();
        $this->getLogger()->info($item->getVanillaName());
        if ($player->getName() != $level->getFolderName()) {
            if (!$player->hasPermission(DefaultPermissionNames::GROUP_OPERATOR)) {
                $this->config = new Config($this->getDataFolder() . $level->getFolderName() . ".yml", Config::YAML);
                if ($this->config->exists("invited_" . $player->getName())) {
                    $this->getLogger()->debug("Name : " . $item->getVanillaName());
                    switch ($item->getVanillaName()) {
                        case "Water":
                        case "Lava":
                        case "TNT":
                        case "Ice":
                        case "Flint and Steel":
                        case "Lava Bucket":
                        case "Water Bucket":
                            $player->sendMessage("§l§cワールド管理システム>>設置権限がありません。");
                            $event->cancel();
                            break;
                    }
                } else {
                    $player->sendMessage("§l§cワールド管理システム>>設置権限がありません。");
                    $event->cancel();
                }
            } else {
                switch ($item->getVanillaName()) {
                    case "Water":
                    case "Lava":
                    case "TNT":
                    case "Ice":
                        $player->sendMessage("§l§cワールド管理システム>>設置権限がありません。");
                        foreach ($this->getServer()->getOnlinePlayers() as $player_tmp) {
                            $player_tmp->sendMessage("§l§c警告>>管理者権限を持つ" . $player->getName() . "が" . $level->getFolderName() . "のワールドで禁止指定アイテムを置こうとしました。");
                        }
                        $event->cancel();
                        break;
                }
            }
        }
    }

    public function onTap(PlayerInteractEvent $event): void
    {
        $item = $event->getItem();
        $player = $event->getPlayer();
        /*if ($player->getInventory()->getItemInHand()->getVanillaName() == "Air") {
            if (isset($this->player_touch_time[$player->getName()])) {
                if ($this->player_touch_time[$player->getName()] + 2 > time()) {
                    $list = FormAPI::makeListForm([$this, "callback"]);
                    $list->setTitle("ワールドメニュー")
                        ->setContent("アクションを選択")
                        ->addButton((new Button("自分のワールドへ行く")))
                        ->addButton((new Button("ロビーに戻る")))
                        ->sendToPlayer($player);
                    unset($this->player_touch_time[$player->getName()]);
                }else{
                $this->player_touch_time[$player->getName()] = time();
                }
            } else {
                $this->player_touch_time[$player->getName()] = time();
            }
        }*/
        if ($player->getName() != $player->getWorld()->getFolderName()) {
            switch ($item->getVanillaName()) {
                case "Flint and Steel":
                case "Lava Bucket":
                case "Water Bucket":
                    $player->sendMessage("§l§cワールド管理システム>>設置権限がありません。");
                    $event->cancel();
                    break;
            }
        }
        $this->getLogger()->debug("PlayerName : " . $player->getName() . " LevelName : " . $player->getWorld()->getFolderName() . "ItemName : " . $item->getName() . " ItemID : " . $item->getTypeId() . " Action : " . $event->getAction());
    }

    public function onLevelChange(EntityTeleportEvent $event): void
    {
        $this->getLogger()->info(TextFormat::GREEN . $event->getEntity()->getName());
        $this->getLogger()->info(TextFormat::GREEN . $event->getTo()->getWorld()->getFolderName());
        $this->config = new Config($this->getDataFolder() . $event->getEntity()->getName() . ".yml", Config::YAML);
        if ($this->config->exists("banned_" . $event->getTo()->getWorld()->getFolderName())) {
            $event->getEntity()->sendMessage("§l§cワールド管理システム>>ワールドBanされているため行くことができません。");
            $event->cancel();
        } else {
            if ($event->getTo()->getWorld()->getFolderName() == $event->getEntity()->getName()) {
                $event->getEntity()->setGamemode(GameMode::CREATIVE());
            }
        }
    }

    public function onDamage(EntityDamageEvent $event)
    {
        if ($event->getEntity() instanceof Player) {
            $this->config = new Config($this->getDataFolder() . $event->getEntity()->getWorld()->getFolderName() . ".yml", Config::YAML);
            if (!$this->config->get("allow_attack")) {
                $event->cancel();
            }
        }
    }

    public function onSpawn(EntitySpawnEvent $event)
    {
        //$event->getEntity()->kill();
    }

    public function goWorld($player, $targetlevel, $name)
    {
        $this->config = new Config($this->getDataFolder() . $name . ".yml", Config::YAML);
        if (!$this->getServer()->getWorldManager()->isWorldLoaded($name)) {
            $this->getServer()->getWorldManager()->loadWorld($name);
        } else {
            $targetlevel->setTime($this->config->get("time_set"));
            if ($this->config->get("time_stop")) {
                $targetlevel->stopTime();
            }
        }
        $player->teleport(new Position($this->config->get("spawn_point_x"), $this->config->get("spawn_point_y"), $this->config->get("spawn_point_z"), $targetlevel));
        //$targetlevel->getWeather()->setWeather($this->config->get("weather"));

        if ($player->getName() != $targetlevel->getFolderName()) {
            if ($this->getServer()->getPlayerExact($targetlevel->getFolderName()) != null) {
                $this->getServer()->getPlayerExact($targetlevel->getFolderName())->sendMessage("§l§9通知>>§6" . $player->getName() . "さん§9があなたのワールドに来ました！");
            }
        }
    }

    public function onCommand(CommandSender $sender, Command $command, string $label, array $args): bool
    {
        switch ($command->getName()) {
            case "wo":
                if (!isset($args[0])) {
                    $sender->sendMessage("====Worldコマンドの使用方法======");
                    $sender->sendMessage("/wo me: 自分のワールドに移動します");
                    $sender->sendMessage("/wo **: **のワールドに移動します");
                    $sender->sendMessage("/wo random: ランダムで他のワールドに移動します");
                    $sender->sendMessage("/wo q * : ワールドの検索を行います");
                    $sender->sendMessage("/wo clear : ワールド内のプレイヤーのインベントリを初期化します");
                    $sender->sendMessage("/wo s: ワールドの詳細設定をします");
                    $sender->sendMessage("/wo gm ** : 自分のゲームモードの変更をします");
                    $sender->sendMessage("/wo give ** : **に自分が今持っているアイテムを渡します");
                    $sender->sendMessage("/wo invite **: **にワールドの編集権限を与えます");
                    $sender->sendMessage("/wo invitelist : ワールドの編集権限を与えてる人を一覧表示します");
                    $sender->sendMessage("/wo uninvite **: **の編集権限を剥奪します");
                    $sender->sendMessage("/wo uninvite all: ワールドの編集権限を与えてる人全員の権限を剥奪します");
                    $sender->sendMessage("/wo kick **: **をワールドからkickします");
                    $sender->sendMessage("/wo ban **: **をワールドからBanします");
                    $sender->sendMessage("/wo unban **: **のワールドBanを解除します");
                    $sender->sendMessage("/wo banlist : ワールドBanしたプレイヤーの一覧");
                } else {
                    switch ($args[0]) {
                        case "me":
                            if ($sender instanceof Player) {
                                $sender->sendMessage("§l§eワールド管理システム>>自分のワールドに戻っています...");
                                $this->goWorld($sender, $this->getServer()->getWorldManager()->getWorldByName($sender->getName()), $sender->getName());
                            }
                            return true;
                        case "q":
                            if (!isset($args[1])) {
                                $sender->sendMessage("§l§cワールド管理システム>>検索文字列を指定してください");
                            } else {
                                $levelList = array();
                                foreach ($this->getServer()->getWorldManager()->getWorlds() as $level) {
                                    if (str_starts_with($level->getFolderName(), $args[1])) {
                                        array_push($levelList, $level->getFolderName());
                                    }
                                }
                                if (empty($levelList)) {
                                    $sender->sendMessage("§l§cワールド管理システム>>ワールドが見つかりませんでした");
                                } else {
                                    $sender->sendMessage("====検索結果======");
                                    foreach ($levelList as $levelName) {
                                        $sender->sendMessage($levelName);
                                    }
                                }
                            }
                            return true;
                        case "random":
                            $fileList = array();
                            foreach ($this->getServer()->getWorldManager()->getWorlds() as $level) {
                                array_push($fileList, $level->getFolderName());
                            }
                            $name = $fileList[array_rand($fileList)];
                            $sender->sendMessage("§l§eワールド管理システム>>" . $name . "のワールドへ移動します。");
                            if ($this->exsistlevel($name)) {
                                $this->goWorld($sender, $this->getServer()->getWorldManager()->getWorldByName($name), $sender->getName());
                            } else {
                                $sender->sendMessage("エラー");
                            }
                            return true;
                        case "give":
                            if ($sender->getName() == $sender->getWorld()->getFolderName()) {
                                if (!isset($args[1])) {
                                    $sender->sendMessage("§l§eワールド管理システム>>相手の名前を指定して打ち直してください");
                                } else {
                                    if ($this->getServer()->getPlayerExact($args[1]) == null) {
                                        $sender->sendMessage("§l§eワールド管理システム>>指定されたプレイヤーが見つかりませんでした");
                                    } else {
                                        if ($this->getServer()->getPlayerExact($args[1])->getWorld()->getFolderName() == $sender->getWorld()->getFolderName()) {
                                            if ($sender->getInventory()->getItemInHand() == null) {
                                                $sender->sendMessage("§l§eワールド管理システム>>渡したいアイテムを持ってください");
                                            } else {
                                                $this->getServer()->getPlayerExact($args[1])->getInventory()->addItem($sender->getInventory()->getItemInHand());
                                                $sender->sendMessage("§l§eワールド管理システム>>成功！！");
                                            }
                                        } else {
                                            $sender->sendMessage("§l§eワールド管理システム>>相手が違うワールドにいるので出来ません");
                                        }
                                    }
                                }
                            } else {
                                $sender->sendMessage("§l§eワールド管理システム>>他人のワールドで使用することはできません");
                            }
                            return true;
                        case "clear":
                            if ($sender->getName() == $sender->getWorld()->getFolderName()) {
                                $players = $sender->getWorld()->getPlayers();
                                foreach ($players as $player) {
                                    if ($player->getName() != $sender->getName()) {
                                        $player->getInventory()->clearAll();
                                        $player->sendMessage("§l§eワールド管理システム>>ワールドの管理者によってインベントリが初期化されました。");
                                    }
                                }
                                $sender->sendMessage("§l§eワールド管理システム>>成功！！");
                            } else {
                                $sender->sendMessage("§l§cワールド管理システム>>他人のワールドで使用することはできません。");
                            }
                        case "s":
                            if (!isset($args[1])) {
                                $sender->sendMessage("-===World詳細設定コマンドの使用方法======");
                                $sender->sendMessage("/wo s setspawn: 自分のワールドのワールドのスポーン地点をセットします");
                                $sender->sendMessage("/wo s pvp on/off: 自分のワールドPVPを有効にするか無効にするかをセットします。");
                                $sender->sendMessage("/wo s settime **: 自分のワールドの時間を**にセットします");
                                $sender->sendMessage("/wo s stoptime: 自分のワールドの時間を固定します");
                                $sender->sendMessage("/wo s restarttime: 自分のワールドの時間の固定を解除します");
                                $sender->sendMessage("/wo s setweather **: (廃止)");
                            } else {
                                switch ($args[1]) {
                                    case "setspawn":
                                        $this->config = new Config($this->getDataFolder() . $sender->getName() . ".yml", Config::YAML);
                                        $this->config->set("spawn_point_x", $sender->getPosition()->getX());
                                        $this->config->set("spawn_point_y", $sender->getPosition()->getY() + 2);
                                        $this->config->set("spawn_point_z", $sender->getPosition()->getZ());
                                        $this->config->save();
                                        $sender->sendMessage("スポーン地点を X>" . $sender->getPosition()->getX() . " Y>" . $sender->getPosition()->getY() . " Z>" . $sender->getPosition()->getZ() . "に設定しました。");
                                        return true;
                                    case "pvp":
                                        $this->config = new Config($this->getDataFolder() . $sender->getName() . ".yml", Config::YAML);
                                        if (!isset($args[2])) {
                                            $sender->sendMessage("§l§cワールド管理システム>>PVPを有効にするか無効にするかを指定してください。");
                                        } else {
                                            if ($this->getReturnFromString($args[2])) {
                                                $this->config->set("allow_attack", true);
                                                $sender->sendMessage("§l§eワールド管理システム>>PVPを有効にしました。");
                                                $this->config->save();
                                            } else {
                                                $this->config->set("allow_attack", false);
                                                $sender->sendMessage("§l§eワールド管理システム>>PVPを無効にしました。");
                                                $this->config->save();
                                            }
                                        }
                                        return true;
                                    case "settime":
                                        $this->config = new Config($this->getDataFolder() . $sender->getName() . ".yml", Config::YAML);
                                        if (!isset($args[2])) {
                                            $sender->sendMessage("§l§cワールド管理システム>>セットしたい時間を指定してください。");
                                        } else {
                                            $this->config->set("time_set", $args[2]);
                                            $this->config->save();
                                            $sender->sendMessage("§l§eワールド管理システム>>時間の設定変更完了！");
                                            $this->getServer()->getWorldManager()->getWorldByName($sender->getName())->setTime($args[2]);
                                        }
                                        return true;
                                    case "stoptime":
                                        $this->config = new Config($this->getDataFolder() . $sender->getName() . ".yml", Config::YAML);
                                        $this->config->set("time_stop", true);
                                        $this->config->save();
                                        $sender->sendMessage("§l§eワールド管理システム>>時間を固定させました！");
                                        $this->getServer()->getWorldManager()->getWorldByName($sender->getName())->stopTime();
                                        return true;
                                    case "restarttime":
                                        $this->config = new Config($this->getDataFolder() . $sender->getName() . ".yml", Config::YAML);
                                        $this->config->set("time_stop", false);
                                        $this->config->save();
                                        $sender->sendMessage("§l§eワールド管理システム>>時間をリスタートさせました！");
                                        $this->getServer()->getWorldManager()->getWorldByName($sender->getName())->startTime();
                                        return true;
                                    case "setweather":
                                        $sender->sendMessage("§l§cワールド管理システム>>このコマンドはサーバーソフトの変更に伴い廃止されました");
                                        /*
                                        $this->config = new Config($this->getDataFolder().$sender->getName().".yml", Config::YAML);
                                        if(!isset($args[2])){
                                        $sender->sendMessage("/wo s setweather **: 自分のワールドの天候を**(0から2)で固定します");
                                        }else{
                                        $this->config->set("weather",$args[2]);
                                        $this->config->save();
                                        $sender->sendMessage("§l§eワールド管理システム>>天候の設定変更完了！");
                                        $this->getServer()->getWorldManager()->getWorldByName($sender->getName())->getWeather()->setWeather($this->config->get("weather"));
                                        }
                                        */
                                        return true;
                                }
                            }
                            return true;
                        case "gm":
                            if (!isset($args[1])) {
                                $sender->sendMessage("§l§cワールド管理システム>>ゲームモードを指定してください。");
                            } else {
                                switch ($args[1]) {
                                    case "0":
                                    case "s":
                                    case "survival":
                                        $gm = [GameMode::SURVIVAL(), "サバイバル"];
                                        break;
                                    case "1":
                                    case "c":
                                    case "creative":
                                        $gm = [GameMode::CREATIVE(), "クリエイティブ"];
                                        break;
                                    case "2":
                                    case "a":
                                    case "adventure":
                                        $gm = [GameMode::ADVENTURE(), "アドベンチャー"];
                                        break;
                                    case "3":
                                    case "sp":
                                    case "spectator":
                                        $gm = [GameMode::SPECTATOR(), "スペクテイター"];
                                        break;
                                    default:
                                        $gm = false;
                                }
                                if (!$gm) {
                                    $sender->sendMessage("§l§cワールド管理システム>>ゲームモードを指定してください。");
                                } elseif ($sender->getName() == $sender->getWorld()->getFolderName() || $sender->hasPermission(DefaultPermissionNames::GROUP_OPERATOR)) {
                                    $sender->setGamemode($gm[0]);
                                    $sender->sendMessage("§l§eワールド管理システム>>ゲームモードを" . $gm[1] . "に変更しました。");
                                } else {
                                    $this->config = new Config($this->getDataFolder() . $sender->getWorld()->getFolderName() . ".yml", Config::YAML);
                                    if ($this->config->exists("invited_" . $sender->getName())) {
                                        if ($gm[0] == GameMode::SPECTATOR()) {
                                            $sender->sendMessage("§l§cワールド管理システム>>他人のワールドでスペクテイターモードに変更することはできません。");
                                        } else {
                                            $sender->setGamemode($gm[0]);
                                            $sender->sendMessage("§l§eワールド管理システム>>ゲームモードを" . $gm[1] . "に変更しました。");
                                        }
                                    } else {
                                        $sender->sendMessage("§l§cワールド管理システム>>招待されていない他人のワールドで使用することはできません。");
                                    }
                                }
                            }
                            return true;
                        case "invite":
                            if (!isset($args[1])) {
                                $sender->sendMessage("§l§cワールド管理システム>>招待する人を指定してください。");
                            } else {
                                $this->config = new Config($this->getDataFolder() . $sender->getName() . ".yml", Config::YAML);
                                $this->config->set("invited_" . $args[1], true);
                                $this->config->save();
                                $sender->sendMessage("§l§eワールド管理システム>>" . $args[1] . "さんを招待しました。");
                            }
                            return true;
                        case "invitelist":
                            $this->config = new Config($this->getDataFolder() . $sender->getName() . ".yml", Config::YAML);
                            $sender->sendMessage("§l§eワールド管理システム>>あなたがワールドの編集権限を与えている(inviteしている)人");
                            $pointer = 0;
                            foreach ($this->config->getAll() as $key => $value) {
                                $this->getLogger()->info($key);
                                if (strpos($key, 'invited_') !== false) {
                                    $pointer++;
                                    $a = str_replace("invited_", "", $key);
                                    $sender->sendMessage($pointer . "人目 " . $a);
                                }
                            }
                            return true;
                        case "uninvite":
                            if (!isset($args[1])) {
                                $sender->sendMessage("§l§cワールド管理システム>>権限を剥奪する人を指定してください。");
                            } else {
                                $this->config = new Config($this->getDataFolder() . $sender->getName() . ".yml", Config::YAML);
                                if ($args[1] == "all") {
                                    $pointer = 0;
                                    foreach ($this->config->getAll() as $key => $value) {
                                        $this->getLogger()->info($key);
                                        if (strpos($key, 'invited_') !== false) {
                                            $pointer++;
                                            $this->config->remove($key);
                                        }
                                    }
                                    $this->config->save();
                                    $sender->sendMessage("§l§eワールド管理システム>>合計で" . $pointer . "人の権限を外しました");
                                } else {
                                    if ($this->config->exists("invited_" . $args[1])) {
                                        $this->config->remove("invited_" . $args[1]);
                                        $this->config->save();
                                        $sender->sendMessage("§l§eワールド管理システム>>" . $args[1] . "の権限を剥奪しました。");
                                    } else {
                                        $sender->sendMessage("§l§cワールド管理システム>>" . $args[1] . "さんにはもともとワールド編集許可が与えられていません。");
                                    }
                                }
                            }
                            return true;
                        case "kick":
                            if ($sender->getName() == $sender->getWorld()->getFolderName()) {
                                if (!isset($args[1])) {
                                    $sender->sendMessage("§l§cワールド管理システム>>Kickする人を指定してください。");
                                } else {
                                    $players = $sender->getWorld()->getPlayers();
                                    foreach ($players as $player) {
                                        if ($player->getName() == $args[1]) {
                                            $this->goWorld($player, $this->getServer()->getWorldManager()->getWorldByName($player->getName()), $player->getName());
                                            $player->kick("ワールドの管理者によりKickされました。", false);
                                            $sender->sendMessage("§l§eワールド管理システム>>" . $args[1] . "をKickしました。");
                                        }
                                    }
                                }
                            } else {
                                $sender->sendMessage("§l§cワールド管理システム>>自分のワールドで使用してください。");
                            }
                            return true;
                        case "ban":
                            if ($sender->getName() == $sender->getWorld()->getFolderName()) {
                                if (!isset($args[1])) {
                                    $sender->sendMessage("§l§cワールド管理システム>>ワールドBanする人を指定してください。");
                                } else {
                                    if ($sender->getName() == $args[1]) {
                                        $sender->sendMessage("§l§cワールド管理システム>>自分をワールドBanすることはできません。");
                                    } else {
                                        $players = $sender->getWorld()->getPlayers();
                                        foreach ($players as $player) {
                                            if ($player->getName() == $args[1]) {
                                                $this->goWorld($player, $this->getServer()->getWorldManager()->getWorldByName($player->getName()), $player->getName());
                                                $player->kick("ワールドの管理者によりワールドBanされました。", false);
                                                $sender->sendMessage("§l§eワールド管理システム>>" . $args[1] . "をワールドBanしました。");
                                            }
                                        }
                                        $this->config = new Config($this->getDataFolder() . $sender->getName() . ".yml", Config::YAML);
                                        $this->config->set("banned_" . $args[1], true);
                                        if ($this->config->exists("invited_" . $args[1])) {
                                            $this->config->remove("invited_" . $args[1]);
                                        }
                                        $this->config->save();
                                    }
                                }
                            } else {
                                $sender->sendMessage("§l§cワールド管理システム>>自分のワールドで使用してください。");
                            }
                            return true;
                        case "banlist":
                            if ($sender->getName() == $sender->getWorld()->getFolderName()) {
                                $this->config = new Config($this->getDataFolder() . $sender->getName() . ".yml", Config::YAML);
                                $sender->sendMessage("Banしている人の一覧");
                                foreach ($this->config->getAll() as $key) {
                                    if (strpos($key, 'banned_') !== false) {
                                        $a = str_replace("banned_", "", $key);
                                        $sender->sendMessage($a);
                                    }
                                }
                            } else {
                                $sender->sendMessage("§l§cワールド管理システム>>自分のワールドで使用してください。");
                            }
                            return true;
                        case "unban":
                            if ($sender->getName() == $sender->getWorld()->getFolderName()) {
                                if (!isset($args[1])) {
                                    $sender->sendMessage("§l§cワールド管理システム>>ワールドBanを解除する人を指定してください。");
                                } else {
                                    $this->config = new Config($this->getDataFolder() . $sender->getName() . ".yml", Config::YAML);
                                    if ($this->config->exists("banned_" . $args[1])) {
                                        $this->config->remove("banned_" . $args[1]);
                                        $this->config->save();
                                        $sender->sendMessage("§l§eワールド管理システム>>" . $args[1] . "のワールドBanを解除しました。");
                                    } else {
                                        $sender->sendMessage("§l§cワールド管理システム>>その人はワールドBanされていません。");
                                    }
                                }
                            } else {
                                $sender->sendMessage("§l§cワールド管理システム>>自分のワールドで使用してください。");
                            }
                            return true;
                        default:
                            if ($sender instanceof Player) {
                                if ($args[0] != "") {
                                    if ($this->exsistlevel($args[0])) {
                                        $sender->sendMessage("§l§eワールド管理システム>>" . $args[0] . "さんのワールドに移動しています...");
                                        $this->goWorld($sender, $this->getServer()->getWorldManager()->getWorldByName($args[0]), $args[0]);
                                    } else {
                                        $sender->sendMessage("§l§cワールド管理システム>>" . $args[0] . "というワールドは存在しません。");
                                    }
                                } else {
                                    $sender->sendMessage("§l§cワールド管理システム>>ワールド名を空白にすることはできません。");
                                }
                            }
                            return true;
                    }
                }
        }
        return true;
    }

    public function exsistlevel($level_name)
    {
        if (file_exists($this->getServer()->getDataPath() . DIRECTORY_SEPARATOR . "worlds" . DIRECTORY_SEPARATOR . $level_name . DIRECTORY_SEPARATOR . "level.dat")) {
            $this->getServer()->getWorldManager()->loadWorld($level_name);
            return true;
        } else {
            return false;
        }
    }

    public static function getReturnFromString($str)
    {
        return match (strtolower(trim($str))) {
            "on", "true", "1" => true,
            default => false,
        };
    }
}
