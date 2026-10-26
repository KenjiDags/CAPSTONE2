<?php
// Shared display data for officer suggestions, including pages that previously loaded names only.
$dropdownOfficerPositions = [];
$dropdownOfficerResult = $conn->query('SELECT officer_name, officer_position FROM officers ORDER BY officer_name');
while ($dropdownOfficerRow = $dropdownOfficerResult->fetch_assoc()) {
    $dropdownOfficerPositions[$dropdownOfficerRow['officer_name']] = $dropdownOfficerRow['officer_position'];
}
?>
<script type="application/json" id="officerDropdownPositions"><?= json_encode((object)$dropdownOfficerPositions, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>
<link rel="stylesheet" href="css/officer-autocomplete.css?v=<?= time() ?>">
<script src="js/inventory-controls.js?v=<?= time() ?>"></script>