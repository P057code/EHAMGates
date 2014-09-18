<?php
require_once('../public/include/vatsimparser.php');

class VatsimParserTest extends PHPUnit_Framework_TestCase {

	public function testFailedServerListFetch() {
		$vp1 = new VatsimParser('testdata-vatsim-servers.txt');

		$data1 = @$vp1->fetchServerList(true);

		$vp2 = new VatsimParser('not_existing.txt');

		$data2 = @$vp2->fetchServerList(true);

		$this->assertEquals($data2, $data1);
	}

	public function testFailedDataFetch() {
		$vp1 = new VatsimParser('testdata-vatsim-servers.txt');

		$data1 = @$vp1->fetchData(true, 'testdata-vatsim.txt');

		$vp2 = new VatsimParser('not_existing.txt');

		$data2 = @$vp2->fetchData(true, 'not_existing.txt');

		$this->assertEquals($data2, $data1);
	}

	public function testParser() {
		$vp1 = new VatsimParser('testdata-vatsim-servers.txt');
		$vp1->fetchData(true, 'testdata-vatsim.txt');
		$data = $vp1->parseData();

		$this->assertArrayHasKey('AFL309', $data);
		$this->assertArrayHasKey('CND294', $data);
		$this->assertArrayHasKey('KLM655', $data);
		$this->assertArrayNotHasKey('G-BAFM', $data);
		$this->assertArrayNotHasKey('KLM757', $data);
	}

	public function testParsedData() {
		$vp1 = new VatsimParser('testdata-vatsim-servers.txt');
		$vp1->fetchData(true, 'testdata-vatsim.txt');
		$data = $vp1->parseData();

		$this->assertEquals('KIAD', $data['KLM655']['origin']);
		$this->assertEquals('I', $data['KLM655']['flightrules']);
		$this->assertEquals('B77L', $data['KLM655']['actype']);
	}

	public function testAircraftTypeSingle() {
		$vp1 = new VatsimParser('testdata-vatsim-servers.txt');

		$actual = $vp1->parseAircraftType('B777L');
		$this->assertEquals('B777L', $actual);
	}

	public function testAircraftTypeDualFirst() {
		$vp1 = new VatsimParser('testdata-vatsim-servers.txt');

		$actual = $vp1->parseAircraftType('H/B744');
		$this->assertEquals('B744', $actual);
	}

	public function testAircraftTypeDualLast() {
		$vp1 = new VatsimParser('testdata-vatsim-servers.txt');

		$actual = $vp1->parseAircraftType('B777L/F');
		$this->assertEquals('B777L', $actual);
	}

	public function testAircraftTypeTriple() {
		$vp1 = new VatsimParser('testdata-vatsim-servers.txt');

		$actual = $vp1->parseAircraftType('H/B777W/F');
		$this->assertEquals('B777W', $actual);
	}

	public function testAircraftTypeTripleWeird() {
		$vp1 = new VatsimParser('testdata-vatsim-servers.txt');

		$actual = $vp1->parseAircraftType('M/B737-700/X');
		$this->assertEquals('B737-700', $actual);
	}
}
?>