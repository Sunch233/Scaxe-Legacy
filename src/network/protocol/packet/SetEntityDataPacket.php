<?php

class SetEntityDataPacket extends RakNetDataPacket{
	public $eid;
	public $metadata;
	
	public function pid(){
        if($this->PROTOCOL < ProtocolInfo::CURRENT_PROTOCOL){
            return  ProtocolInfo12::SET_ENTITY_DATA_PACKET;
        }
		return ProtocolInfo::SET_ENTITY_DATA_PACKET;
	}
	
	public function decode(){

	}
	
	public function encode(){
		$this->reset();
		$this->putInt($this->eid);
		$this->put(Utils::writeMetadata($this->metadata));
	}

}