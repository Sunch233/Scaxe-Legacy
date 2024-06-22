<?php

class MelonBlock extends TransparentBlock{
	public function __construct(){
		parent::__construct(MELON_BLOCK, 0, "Melon Block");
		$this->hardness = 5;
	}
	public function getDrops(Item $item, Player $player){
		return array(
			array(MELON_SLICE, 0, mt_rand(3, 7)),
		);
	}
	
	public static function getCollisionBoundingBoxes(Level $level, $x, $y, $z, Entity $entity){
		return [new AxisAlignedBB($x, $y, $z, $x + 1, $y + 1, $z + 1)];
	}
}