<?php

namespace pocketmine\tile;

use pocketmine\entity\Entity;
use pocketmine\event\entity\EntityGenerateEvent;
use pocketmine\item\Item;
use pocketmine\math\Vector3;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\nbt\tag\DoubleTag;
use pocketmine\nbt\tag\ListTag;
use pocketmine\nbt\tag\FloatTag;
use pocketmine\nbt\tag\IntTag;
use pocketmine\nbt\tag\StringTag;
use pocketmine\level\format\FullChunk;
use pocketmine\Player;
use pocketmine\event\entity\EntitySpawnEvent;

class MobSpawner extends Spawnable{

	public function __construct(FullChunk $chunk, CompoundTag $nbt){
		parent::__construct($chunk, $nbt);
		if(!isset($nbt->EntityId)){
			$nbt->EntityId = new IntTag("EntityId", 0);
		}
		if(!isset($nbt->SpawnCount)){
			$nbt->SpawnCount = new IntTag("SpawnCount", 4);
		}
		if(!isset($nbt->SpawnRange)){
			$nbt->SpawnRange = new IntTag("SpawnRange", 4);
		}
		if(!isset($nbt->MinSpawnDelay)){
			$nbt->MinSpawnDelay = new IntTag("MinSpawnDelay", 200);
		}
		if(!isset($nbt->MaxSpawnDelay)){
			$nbt->MaxSpawnDelay = new IntTag("MaxSpawnDelay", 799);
		}
		if(!isset($nbt->Delay)){
			$nbt->Delay = new IntTag("Delay", mt_rand($nbt->MinSpawnDelay->getValue(), $nbt->MaxSpawnDelay->getValue()));
		}

		if($this->getEntityId() > 0){
			$this->scheduleUpdate();
		}
	}

	public function getEntityId(){
		return $this->namedtag["EntityId"];
	}

	public function setEntityId(int $id){
		$this->namedtag->EntityId->setValue($id);
		$this->spawnToAll();
		if($this->chunk instanceof FullChunk){
			$this->chunk->setChanged();
			$this->level->clearChunkCache($this->chunk->getX(), $this->chunk->getZ());
		}
		$this->scheduleUpdate();
	}

	public function getSpawnCount(){
		return $this->namedtag["SpawnCount"];
	}

	public function setSpawnCount(int $value){
		$this->namedtag->SpawnCount->setValue($value);
	}

	public function getSpawnRange(){
		return $this->namedtag["SpawnRange"];
	}

	public function setSpawnRange(int $value){
		$this->namedtag->SpawnRange->setValue($value);
	}

	public function getMinSpawnDelay(){
		return $this->namedtag["MinSpawnDelay"];
	}

	public function setMinSpawnDelay(int $value){
		$this->namedtag->MinSpawnDelay->setValue($value);
	}

	public function getMaxSpawnDelay(){
		return $this->namedtag["MaxSpawnDelay"];
	}

	public function setMaxSpawnDelay(int $value){
		$this->namedtag->MaxSpawnDelay->setValue($value);
	}

	public function getDelay(){
		return $this->namedtag["Delay"];
	}

	public function setDelay(int $value){
		$this->namedtag->Delay->setValue($value);
	}

	public function getName(){
		return "Monster Spawner";
	}

	public function canUpdate(){
		if($this->getEntityId() === 0) return false;
		$hasPlayer = false;
		$count = 0;
		foreach($this->getLevel()->getEntities() as $e){
			if($e instanceof Player){
				if($e->distance($this->getBlock()) <= 15) $hasPlayer = true;
			}
			if($e::NETWORK_ID == $this->getEntityId()){
				$count++;
			}
		}
		if($hasPlayer and $count < 15 && $count < $this->server->getProperty("spawn-limits.monsters", "70")){ // Spawn limit = 15
			return true;
		}
		return false;
	}

	public function onUpdate(){
		if($this->closed === true){
			return false;
		}

		$this->timings->startTiming();

		if(!($this->chunk instanceof FullChunk)){
			return false;
		}
		if($this->canUpdate()){
			if($this->getDelay() == 0){
				$success = 0;
				for($i = 0; $i < $this->getSpawnCount(); $i++){
					$pos = $this->add(mt_rand() / mt_getrandmax() * $this->getSpawnRange(), mt_rand(-1, 1), mt_rand() / mt_getrandmax() * $this->getSpawnRange());
					$target = $this->getLevel()->getBlock($pos);
					$ground = $target->getSide(Vector3::SIDE_DOWN);
					if($target->getId() == Item::AIR && $ground->isTopFacingSurfaceSolid()){
						$success++;
						#$this->getLevel()->getServer()->getPluginManager()->callEvent($ev = new EntityGenerateEvent($pos, $this->getEntityId(), EntityGenerateEvent::CAUSE_MOB_SPAWNER));
						$nbt = new CompoundTag("", [
							"Pos" => new ListTag("Pos", [
								new DoubleTag("", $pos->x),
								new DoubleTag("", $pos->y),
								new DoubleTag("", $pos->z)
							]),
							"Motion" => new ListTag("Motion", [
								new DoubleTag("", 0),
								new DoubleTag("", 0),
								new DoubleTag("", 0)
							]),
							"Rotation" => new ListTag("Rotation", [
								new FloatTag("", mt_rand() / mt_getrandmax() * 360),
								new FloatTag("", 0)
							]),
						]);
						$entity = Entity::createEntity($this->getEntityId(), $this->chunk, $nbt);
						$this->getLevel()->getServer()->getPluginManager()->callEvent($ev = new EntitySpawnEvent($entity));
						if(!$ev->isCancelled()){
							$entity->spawnToAll();
						}
						
					}
				}
				if($success > 0) $this->setDelay(mt_rand($this->getMinSpawnDelay(), $this->getMaxSpawnDelay()));
			}else{
				$this->setDelay($this->getDelay() - 1);
			}
		}

		$this->timings->stopTiming();

		return true;
	}

	public function getSpawnCompound(){
		$c = new CompoundTag("", [
			new StringTag("id", Tile::MOB_SPAWNER),
			new IntTag("x", (int) $this->x),
			new IntTag("y", (int) $this->y),
			new IntTag("z", (int) $this->z),
			new IntTag("EntityId", (int) $this->getEntityId())
		]);

		return $c;
	}
}
