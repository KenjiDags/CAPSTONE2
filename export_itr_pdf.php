<?php
// Disabled: ITR PDF export has been removed.
http_response_code(410); // Gone
header('Content-Type: text/plain');
echo "The ITR PDF export endpoint has been disabled. Use the Print/Save as PDF option on the ITR export page.";
exit;
