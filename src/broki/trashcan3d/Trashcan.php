<?php

declare(strict_types=1);

namespace broki\trashcan3d;

use broki\trashcan3d\command\TrashcanCommand;
use broki\trashcan3d\entity\TrashcanEntity;
use muqsit\invmenu\InvMenu;
use muqsit\invmenu\InvMenuHandler;
use muqsit\invmenu\type\InvMenuTypeIds;
use pocketmine\entity\EntityDataHelper;
use pocketmine\entity\EntityFactory;
use pocketmine\entity\Human;
use pocketmine\entity\Location;
use pocketmine\entity\Skin;
use pocketmine\inventory\Inventory;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\player\Player;
use pocketmine\plugin\PluginBase;
use pocketmine\utils\SingletonTrait;
use pocketmine\utils\TextFormat;
use pocketmine\world\sound\BarrelCloseSound;
use pocketmine\world\sound\BarrelOpenSound;
use pocketmine\world\World;
use Ramsey\Uuid\Uuid;

class Trashcan extends PluginBase {
    use SingletonTrait;

    public array $listWhoWannaDespawnTrashcan = [];

    protected function onEnable(): void {
        self::setInstance($this);

        $this->checkResources();

        $this->getServer()->getCommandMap()->register("trashcan", new TrashcanCommand("trashcan", "trashcan command"));

        EntityFactory::getInstance()->register(TrashcanEntity::class, function(World $world, CompoundTag $nbt): TrashcanEntity {
            return new TrashcanEntity(EntityDataHelper::parseLocation($nbt, $world), Human::parseSkinNBT($nbt), $nbt);
        }, ['TrashcanEntity']);

        if (!InvMenuHandler::isRegistered()) {
            InvMenuHandler::register($this);
        }
    }

    public function checkResources(): void {
        $modelPath = $this->getDataFolder() . "model";

        if (!file_exists($modelPath)) {
            @mkdir($modelPath);
        }

        $this->saveResource("model/trashcan.png");
        $this->saveResource("model/trashcan.json");
        $this->saveResource("model/trashcan_open.json");
    }

    public function processSkin(bool $opened = false): Skin {
        $jsonPath = $this->getDataFolder() . "model/trashcan" . ($opened ? "_open" : "") . ".json";
        $texturePath = $this->getDataFolder() . "model/trashcan.png";

        $size = getimagesize($texturePath);
        $img = @imagecreatefrompng($texturePath);

        $bytes = "";
        for ($y = 0; $y < $size[1]; $y++) {
            for ($x = 0; $x < $size[0]; $x++) {
                $colorat = @imagecolorat($img, $x, $y);
                $a = ((~((int)($colorat >> 24))) << 1) & 0xff;
                $r = ($colorat >> 16) & 0xff;
                $g = ($colorat >> 8) & 0xff;
                $b = $colorat & 0xff;
                $bytes .= chr($r) . chr($g) . chr($b) . chr($a);
            }
        }

        @imagedestroy($img);

        $jsonContents = file_get_contents($jsonPath);
        $uuid = Uuid::uuid4()->toString();
        return new Skin($uuid, $bytes, "", "geometry.trashcan" . ($opened ? ".open" : ""), $jsonContents);
    }

    public function spawnTrashcan(Location $location, ?string $name): void {
        $locationMod = Location::fromObject($location->add(0, 0.5, 0), $location->getWorld());

        $human = new TrashcanEntity($locationMod, $this->processSkin());
        $human->spawnToAll();

        if ($name !== null) {
            $human->setNameTag($name);
        }
    }

    public function sendTrashcanInv(Player $player, bool $withSound = true): void {
        if ($withSound) {
            $player->getWorld()->addSound($player->getPosition()->add(0.5, 0.5, 0.5), new BarrelOpenSound(), [$player]);
        }

        $menu = InvMenu::create(InvMenuTypeIds::TYPE_DOUBLE_CHEST)->setName($this->getConfig()->get("trashcan-inv-name", "Trashcan"));

        $menu->setInventoryCloseListener(function(Player $player, Inventory $inventory) use ($withSound): void {
            $items = 0;

            foreach ($inventory->getContents() as $item) {
                $items += $item->getCount();
            }

            if ($items > 0) {
                $inventory->clearAll();
                $player->sendMessage("[Trashcan]" . TextFormat::YELLOW . " Disposed $items item(s)!");
            }

            if ($withSound) {
                $player->getWorld()->addSound($player->getPosition()->add(0.5, 0.5, 0.5), new BarrelCloseSound(), [$player]);
            }
        });

        $menu->send($player);
    }
}