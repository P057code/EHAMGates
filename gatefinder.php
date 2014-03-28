<?php

require('definitions.php');

class GateFinder {

	// For future use: one can mark gates as occupied.
	// These gates will then not be returned by the findGate function.
	private $occupiedGates = array('C15', 'E02');
	
	function resolveAircraftCat($aircraftType) {
		return Gates_EHAM::$aircraftCategories[$aircraftType];
	}

	function resolveAirlineGate($callsign) {
		preg_match('/^[A-Z]{3}/', $callsign, $airlineIATA);

		return Gates_EHAM::$airlinesDefaultGates[$airlineIATA[0]];
	}

	function resolveSchengenOrigin($origin) {
		return in_array(substr($origin, 0, 2), Gates_EHAM::$schengen);
	}

	function findGate($callsign, $aircraftType, $origin) {
		preg_match('/^[A-Z]{3}/', $callsign, $airlineIATA);

		// Determine whether flight is cargo or civil
		if(array_key_exists($airlineIATA[0], Gates_EHAM::$cargoGates)) {
			return $this->findCargoGate($callsign, $aircraftType);
		}
		else {

			// Determine whether flight origins from Schengen country
			if($this->resolveSchengenOrigin($origin)) {
				$allSchengenGates = array_merge(Gates_EHAM::$bravoApron, Gates_EHAM::$schengenGates,
					Gates_EHAM::$schengenNonSchengenGates);

				return $this->findCivilGate($allSchengenGates, $callsign, $aircraftType);
			}
			else {
				$allNonSchengenGates = array_merge(Gates_EHAM::$schengenNonSchengenGates, Gates_EHAM::$nonSchengenGates);

				return $this->findCivilGate($allNonSchengenGates, $callsign, $aircraftType);
			}
		}
	}

	function findCargoGate($callsign, $aircraftType) {
		preg_match('/^[A-Z]{3}/', $callsign, $airlineIATA);

		// Find a free cargo gate
		foreach(Gates_EHAM::$cargoGates[$airlineIATA[0]] as $gate) {
			if(!in_array($gate, $this->occupiedGates)) {
				return $gate;
			}
		}

		return false;
	}

	function findCivilGate($allGates, $callsign, $aircraftType) {
		$defaultGate = $this->resolveAirlineGate($callsign);
		$cat = $this->resolveAircraftCat($aircraftType);

		// First determine the available gates
		$availableGates = array();

		foreach($allGates as $gate => $gateCat) {
			if($gateCat >= $cat && !in_array($gate, $this->occupiedGates)) {
				$availableGates[$gate] = $gateCat;
			}
		}

		// Determine which gates are available for the airline
		$matches = array();
		foreach($defaultGate as $pier) {
			foreach($availableGates as $gate => $gateCat) {
				if(substr($gate, 0, 1) == $pier) {
					$matches[$gate] = $gateCat;
				}
			}
		}

		// Sort the available gates, based on their category
		// We do not want to use cat. 8 gates for cat. 2 aircraft if lower cat. gates are available
		asort($matches);

		// Return the first of the available gates
		if(count($matches) > 0) {
			return array_keys($matches)[0];
		}

		return false;
	}

}

?>