<?php

require_once('gatefinder.php');

class GateAssigner {
	
	private $assignedCallsigns = array();
	private $assignedGates = array();
	private $gateFinder;

	private $lastRequest;

	private $foundGates = array();

	function __construct($dataSource = null) {
		$this->gateFinder = new GateFinder($dataSource);
	}

	function loadRemoteData() {
		$this->gateFinder->loadRemoteData();
	}

	function assignGate($gate, $matchType, $callsign = 'unknown', $aircraftType = null, $origin = null) {
		if((array_key_exists($gate, Gates_EHAM::allGates())
			|| array_key_exists($gate, Gates_EHAM::allCargoGates())
			|| $gate == Definitions::$generalAviationGate)) {
			if($callsign != 'unknown') {
				$this->assignedCallsigns[$callsign] = array(
					'gate' => $gate,
					'aircraftType' => $aircraftType,
					'origin' => $origin,
					'matchType' => $matchType
				);
			}

			if($gate != Definitions::$generalAviationGate) {
				$this->assignedGates[$gate] = $callsign;
				$this->gateFinder->occupyGate($gate);
			}
			
			if($matchType != 'OCCUPIED') {
				$this->resetSearch();
			}

			return true;
		}

		return false;
	}

	function assignFoundGate() {
		return $this->assignGate($this->lastRequest['gate'], $this->lastRequest['matchType'], $this->lastRequest['callsign'], $this->lastRequest['aircraftType'], $this->lastRequest['origin']);
	}

	function assignManualGate($gate) {
		return $this->assignGate($gate, 'MANUAL', $this->lastRequest['callsign'], $this->lastRequest['aircraftType'], $this->lastRequest['origin']);
	}

	function releaseGate($gate) {
		if(array_key_exists($gate, $this->assignedGates)) {
			$callsign = $this->assignedGates[$gate];
			if($callsign != 'unknown') {
				unset($this->assignedCallsigns[$callsign]);
			}

			unset($this->assignedGates[$gate]);
			$this->gateFinder->releaseGate($gate);

			return true;
		}

		return false;
	}

	function releaseCallsign($callsign) {
		if(array_key_exists($callsign, $this->assignedCallsigns)) {
			$gate = $this->assignedCallsigns[$callsign]['gate'];
			if($gate != Definitions::$generalAviationGate) {
				unset($this->assignedGates[$gate]);
				$this->gateFinder->releaseGate($gate);
			}

			unset($this->assignedCallsigns[$callsign]);

			return true;
		}

		return false;
	}

	function findGate($callsign, $aircraftType, $origin, $force = true) {
		if($force || !array_key_exists($callsign, $this->foundGates)) {
			$callsign = strtoupper($callsign);
			$origin = strtoupper($origin);

			$gate = $this->gateFinder->findGate($callsign, $aircraftType, $origin);

			$result = array(
				'callsign' 		=> $callsign,
				'aircraftType' 	=> $aircraftType,
				'origin' 		=> $origin,
				'gate'			=> $gate['gate'],
				'matchType'		=> $gate['match']
			);

			$this->foundGates[$callsign] = $result;
		}

		$this->lastRequest = $this->foundGates[$callsign];
	}

	function alreadyOccupied() {
		if($this->result()) {
			$this->assignGate($this->lastRequest['gate'], 'OCCUPIED');

			$this->findGate($this->lastRequest['callsign'], $this->lastRequest['aircraftType'], $this->lastRequest['origin']);

			return true;
		}

		return false;
	}

	function result() {
		if(!empty($this->lastRequest)) {
			return $this->lastRequest;
		}

		return false;
	}

	function getFreeGates($aircraftType, $origin) {
		$gates = $this->gateFinder->getFreeGates($aircraftType, $origin);
		ksort($gates);

		return $gates;
	}

	function getAssignedGates() {
		ksort($this->assignedGates);

		return $this->assignedGates;
	}

	function getAssignedCallsigns() {
		ksort($this->assignedCallsigns);

		return $this->assignedCallsigns;
	}

	function resetSearch() {
		$this->lastRequest = null;
	}

	function isGateAssigned($gate) {
		if(array_key_exists($gate, $this->assignedGates)) {
			return $this->assignedGates[$gate];
		}

		return false;
	}

	function isCallsignAssigned($callsign) {
		if(array_key_exists($callsign, $this->assignedCallsigns)) {
			return $this->assignedCallsigns[$callsign];
		}

		return false;
	}

	function handleAssign() {
		if(isset($_GET['assign']) && $this->result()) {
			return $this->assignFoundGate();
		}

		return false;
	}

	function handleAssignManual() {
		if(isset($_GET['manual']) && $this->result()) {
			return $this->assignManualGate($_GET['manual']);
		}

		return false;
	}

	function handleOccupied() {
		if(isset($_GET['occupied']) && $this->result()) {
			return $this->alreadyOccupied();
		}

		return false;
	}

	function handleOccupy() {
		if(isset($_GET['occupy'])) {
			return $this->assignGate($_GET['occupy'], 'OCCUPIED');
		}

		return false;
	}

	function handleRelease() {
		if(isset($_GET['release'])) {
			return $this->releaseGate($_GET['release']);
		}

		return false;
	}

	function handleReleaseCS() {
		if(isset($_GET['releaseCS'])) {
			return $this->releaseCallsign($_GET['releaseCS']);
		}

		return false;
	}
}