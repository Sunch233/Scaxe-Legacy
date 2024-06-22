<?php
require_once("LiquidBlockDynamic.php"); //TODO class loader?

class WaterBlock extends LiquidBlockDynamic{
	
	public static $blockID = WATER;
	
	public function __construct($meta = 0){
		parent::__construct(WATER, $meta, "Water");
		$this->hardness = 500;
	}
	
	public function place(Item $item, Player $player, Block $block, Block $target, $face, $fx, $fy, $fz){
		$ret = $this->level->setBlock($this, $this, true, false, true);
		return $ret;
	}
	
	public static function getTickDelay(){
		return 5;
	}
}
