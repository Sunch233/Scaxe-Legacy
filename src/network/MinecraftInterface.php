<?php

class MinecraftInterface{

	public $bandwidth;
	private $socket;
	public $start;

	private SleeperNotifier $notifier;
	private NetworkThread $networkThread;
	private $server;

	function __construct(PocketMinecraftServer $pmServer, $server, $port = 25565, $serverip = "0.0.0.0"){
		$this->notifier = new SleeperNotifier();
		$this->server = $pmServer;
		$this->server->getTickSleeper()->addNotifier($this->notifier, function () : void{
			$this->process();
		});
		$this->networkThread = new NetworkThread($this->notifier, $server, $port, $serverip);

		$this->bandwidth = [0, 0, microtime(true)];
		$this->start = microtime(true);
	}

	public function close(){
		return $this->socket->close(false);
	}

	public function process(){
		while(($data = $this->networkThread->readThreadToMainPacket()) !== null){
			$offset = 0;
			$ipLen = Utils::readInt(substr($data, $offset, 4));
			$offset += 4;
			$ip = substr($data, $offset, $ipLen);
			$offset += $ipLen;

			$port = Utils::readShort(substr($data, $offset, 2));
			$offset += 2;

			$bufferLen = Utils::readInt(substr($data, $offset, 4));
			$offset += 4;
			$buffer = substr($data, $offset, $bufferLen);

			$this->bandwidth[0] += $bufferLen;
			ServerAPI::request()->api->dhandle("mcinterface.read", ["buffer" => $buffer, "source" => $ip, "port" => $port]);

			$packet = $this->parsePacket($buffer, $ip, $port);
			if($packet instanceof Packet){
				$this->server->packetHandler($packet);
			}
		}
	}

	private function parsePacket($buffer, $source, $port){
		$pid = ord($buffer[0]);

		if(RakNetInfo::isValid($pid)){
			$parser = new RakNetParser($buffer);
			if($parser->packet !== false){
				$parser->packet->ip = $source;
				$parser->packet->port = $port;
				if(EventHandler::callEvent(new PacketReceiveEvent($parser->packet)) === BaseEvent::DENY){
					return false;
				}
				return $parser->packet;
			}
			return false;
		}elseif($pid === 0xfe and $buffer[1] === "\xfd" and ServerAPI::request()->api->query instanceof QueryHandler){
			$packet = new QueryPacket;
			$packet->ip = $source;
			$packet->port = $port;
			$packet->buffer =& $buffer;
			if(EventHandler::callEvent(new PacketReceiveEvent($packet)) === BaseEvent::DENY){
				return false;
			}
			ServerAPI::request()->api->query->handle($packet);
		}else{
			$packet = new Packet();
			$packet->ip = $source;
			$packet->port = $port;
			$packet->buffer =& $buffer;
			EventHandler::callEvent(new PacketReceiveEvent($packet));
			return false;
		}
	}

	public function writePacket(Packet $packet){
		if(EventHandler::callEvent(new PacketSendEvent($packet)) === BaseEvent::DENY){
			return 0;
		}elseif($packet instanceof RakNetPacket){
			$codec = new RakNetCodec($packet);
		}

		$this->networkThread->pushMainToThreadPacket(
			Utils::writeInt(strlen($packet->ip)) . $packet->ip .
			Utils::writeShort($packet->port) .
			Utils::writeInt($bufferLen = strlen($packet->buffer)) . $packet->buffer
		);
		$this->bandwidth[1] += $bufferLen;
		return $bufferLen;
	}
}