<?php

class NetworkThread extends Thread{

	protected bool $shutdown = false;

	protected Threaded $externalQueue;
	protected Threaded $internalQueue;

	function __construct(
		private SleeperNotifier $mainThreadNotifier,
		protected string $server,
		protected int $port,
		protected string $serverip,
	){
		$this->externalQueue = new Threaded;
		$this->internalQueue = new Threaded;

		$this->start();
	}

	public function pushMainToThreadPacket(string $str) : void{
		$this->internalQueue[] = $str;
	}

	public function readMainToThreadPacket() : ?string{
		return $this->internalQueue->shift();
	}

	public function pushThreadToMainPacket(string $str) : void{
		$this->externalQueue[] = $str;
		if($this->mainThreadNotifier !== null){
			$this->mainThreadNotifier->wakeupSleeper();
		}
	}

	public function readThreadToMainPacket() : ?string{
		return $this->externalQueue->shift();
	}

	public function shutdown() : void{
		$this->shutdown = true;
		$this->notify();
	}

	public function run(){
		$serverip = $this->serverip;
		$socket = new UDPSocket($this->server, $this->port, true, $serverip);
		if($socket->connected === false){
			throw new RuntimeException("Couldn't bind to $serverip:" . $this->port);
		}

		while(!$this->shutdown){
			$start = microtime(true);

			$received = true; //receive
			for($i = 0; $i < PocketMinecraftServer::$PACKET_READING_LIMIT && $received && !$this->shutdown; ++$i){
				$received = $this->receivePacket($socket);
			}

			$this->sendPackets($socket);

			$time = microtime(true) - $start;
			if($time < 0.01){ //TPS 100
				@time_sleep_until(microtime(true) + 0.01 - $time);
			}
		}
		$socket->close(false);
	}

	private function receivePacket(UDPSocket $socket) : bool{
		$buf = "";
		$source = false;
		$port = 1;
		$len = $socket->read($buf, $source, $port);
		if($len === false or $len === 0){
			return false;
		}

		$this->pushThreadToMainPacket(
			Utils::writeInt(strlen($source)) . $source .
			Utils::writeShort($port) .
			Utils::writeInt($len) . $buf
		);

		return true;
	}

	private function sendPackets(UDPSocket $socket) : void{
		while(($data = $this->readMainToThreadPacket()) !== null){
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

			$socket->write($buffer, $ip, $port);
		}
	}
}