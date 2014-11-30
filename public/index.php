<?php
session_start();

date_default_timezone_set('Zulu');

require_once('include/definitions_global.php');
require_once('include/vatsimparser.php');
require_once('include/gateassigner.php');
require_once('include/tools.php');

// Initialize GateAssigner: Either get it from the SESSION or create a new instance.
if(isset($_SESSION['gateAssigner'])) {
	$gateAssigner = unserialize($_SESSION['gateAssigner']);
}

if(!isset($_SESSION['gateAssigner']) || !$gateAssigner instanceof GateAssigner) {
	$gateAssigner = new GateAssigner();
}

if($gateAssigner->handleRelease() || $gateAssigner->handleReleaseCS()) {
	$_SESSION['gateAssigner'] = serialize($gateAssigner);

	header("Location: " . $_SERVER['PHP_SELF']);
	exit();
}

$vp = new VatsimParser();
$vatsimData = $vp->parseData();
$gateAssigner->loadRemoteData();

$rldLastDataFetch = (file_exists('data.txt') ? file_get_contents('data.txt', NULL, NULL, 0, 10) : time());

define('PAGE', 'vatsim');
require('include/tpl_header.php');
?>
<div class="row">
	<div class="col-md-6">
		<h1>Inbound List</h1>

		<p>VATSIM data gets updated every 2 minutes (server list every hour), real life data gets updated every 15 minutes.</p>
		<p>The last VATSIM update: <strong><?php echo date("i:s", time() - $vp->lastDataFetch()); ?> minutes ago</strong>.<br />
		Last real life data update: <strong><?php echo date("i:s", time() - $rldLastDataFetch); ?> minutes ago</strong>.</p>

		<table class="table table-hover table-condensed" id="inboundList">
			<thead>
				<tr>
					<th>C/S</th>
					<th class="hidden-xs">A/C</th>
					<th class="hidden-xs">ORGN</th>
					<th class="hidden-xs">ETA</th>
					<th>GATE</th>
					<th></th>
				</tr>
			</thead>
			<tbody>
				<?php
				if(count($vatsimData) == 0) {
					echo '<tr><td colspan="5">No inbound flights at the moment.</td></tr>';
				}

				foreach($vatsimData as $callsign => $data) {
					$result = $gateAssigner->isCallsignAssigned($callsign);
					if($result) {
						$assigned = true;
					}
					else {
						if(isset($_GET['callsign']) && $_GET['callsign'] == $callsign) {
							// Load previously calculated result (by not enforcing the find)
							$gateAssigner->findGate($callsign, $data['actype'], $data['origin'], false);
							$result = $gateAssigner->result();

							if($gateAssigner->handleAssign() || $gateAssigner->handleAssignManual() || $gateAssigner->handleOccupied()) {
								$_SESSION['gateAssigner'] = serialize($gateAssigner);

								header("Location: " . $_SERVER['PHP_SELF']);
								exit();
							}
						}

						$gateAssigner->findGate($callsign, $data['actype'], $data['origin']);
						$result = $gateAssigner->result();
						$assigned = false;
					}

					$rowClass = ($data['flightrules'] == 'V') ? 'text-muted' : '';
					$isUnknownAircraftType = !Definitions::canTranslateAircraftType($result['aircraftType']);

					echo '<tr class="'. $rowClass .'"><td>' . $callsign . '</td><td class="hidden-xs' . (($isUnknownAircraftType) ? ' danger' : '') . '">' . $result['aircraftType'] . '</td>';
					echo '<td class="hidden-xs">' . $result['origin'] . '</td>';

					if($data['groundspeed'] > 25) {
						$eta = Tools::calculateETA($data['lat'], $data['long'], Gates_EHAM::$lat, Gates_EHAM::$long, $data['groundspeed']);
						$etaTime = (time() + $eta) - (time() - $vp->lastDataFetch());

						$status = '<span class="hide">' . date("Y-m-d", $etaTime) . '</span>' . date("H:i", $etaTime) . 'z';
					}
					else {
						$dtg = Tools::calculateDTG($data['lat'], $data['long'], Gates_EHAM::$lat, Gates_EHAM::$long);

						if($dtg < 15) {
							$status = 'Landed';
						}
						else {
							$status = 'Departing';
						}
					}

					echo '<td class="hidden-xs">' . $status .'</td>';
					echo '<td><span class="glyphicon glyphicon-' . Definitions::resolveMatchTypeIcon($result['matchType']) . '"></span> ' . $result['gate'] . '</td>';
					echo '<td style="text-align: right;">';
					if($assigned) {
						echo '<a href="?releaseCS='. $callsign .'" class="btn btn-danger btn-xs"><span class="glyphicon glyphicon-log-out"></span> Release</a>';
					}
					elseif(!isset($result['matchType'])) {
						echo '<em>No Actions</em>';
					}
					elseif($result['matchType'] != 'NONE') {
						echo '<a href="?callsign='. $callsign .'&amp;assign" class="btn btn-success btn-xs"><span class="glyphicon glyphicon-log-in"></span> Assign</a>';
						echo ' <a href="?callsign='. $callsign .'&amp;occupied" class="btn btn-danger btn-xs"><span class="glyphicon glyphicon-ban-circle"></span> Occupied</a>';
					}
					else {
						$freeGates = $gateAssigner->getFreeGates($result['aircraftType'], $result['origin']);

						if(count($freeGates) > 0) {
							?>
							<form class="form-inline" method="get">
								<input type="hidden" name="callsign" value="<?php echo $callsign; ?>" />
								<label for="manual" class="sr-only">Aircraft type</label>
								<select class="form-control-xs" name="manual">
									<?php
									foreach($freeGates as $gate => $cat) {
										echo '<option value="'. $gate .'">' . $gate . ' (' . $cat . ')</option>';
									}
									?>
									<option value="<?php echo Definitions::$generalAviationGate; ?>">* GA *</option>
								</select>
								<button type="submit" class="btn btn-success btn-xs"><span class="glyphicon glyphicon-log-in"></span> Assign</button>
							</form>
							<?php
						}
						else {
							echo '<em>No Suitable Gates</em>';
						}
					}
					echo '</td></tr>';
				}
				$gateAssigner->resetSearch();
				?>
			</tbody>
		</table>
	</div>
	<script src="js/jquery.tablesorter.min.js"></script>
	<script src="js/jquery.tablesorter.widgets.min.js"></script>
	<script>
		$(document).ready(function() {
			$("#inboundList").tablesorter({
				widgets: ["saveSort"]
			});
		});
	</script>
</div>

<div class="row">
	<div class="col-md-6">
		<h2>Legend</h2>

		<p>Greyed out flights are VFR flights, the others are IFR flights. Below is an overview of the icons used in the Gate column.</p>

		<table class="table table-hover table-condensed">
			<thead>
				<tr>
					<th></th>
					<th>Description</th>
				</tr>
			</thead>
			<tbody>
				<?php
				foreach(Definitions::getAllMatchTypes() as $description) {
					echo '<tr>';
					echo '<td><span class="glyphicon glyphicon-' . $description['icon'] . '"></span></td>';
					echo '<td>' . $description['text'] . '</td>';
					echo '</tr>';
				}
				?>
				<tr><td><span class="glyphicon glyphicon-warning-sign"></span></td><td>The gate could not be determined.</td></tr>
			</tbody>
		</table>
	</div>
</div>
<?php
require('include/tpl_footer.php');

$_SESSION['gateAssigner'] = serialize($gateAssigner);
?>