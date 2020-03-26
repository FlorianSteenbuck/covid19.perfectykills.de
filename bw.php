<?php
file_put_contents(
    "Tabelle_Coronavirus-Faelle-BW.xlsx", file_get_contents(
        "https://sozialministerium.baden-wuerttemberg.de/fileadmin/redaktion/m-sm/intern/downloads/Downloads_Gesundheitsschutz/Tabelle_Coronavirus-Faelle-BW.xlsx"
    )
);
require('vendor/autoload.php');
$reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReader('Xlsx');
$reader->setReadDataOnly(TRUE);
$spreadsheet = $reader->load("Tabelle_Coronavirus-Faelle-BW.xlsx");

$worksheet = $spreadsheet->getActiveSheet();
// Get the highest row number and column letter referenced in the worksheet
$highestRow = $worksheet->getHighestRow(); // e.g. 10
$highestColumn = $worksheet->getHighestColumn(); // e.g 'F'
// Increment the highest column letter
$highestColumn++;

echo '<table>' . "\n";
for ($row = 1; $row <= $highestRow; ++$row) {
    echo '<tr>' . PHP_EOL;
    for ($col = 'A'; $col != $highestColumn; ++$col) {
        echo '<td>' .
             $worksheet->getCell($col . $row)
                 ->getValue() .
             '</td>' . PHP_EOL;
    }
    echo '</tr>' . PHP_EOL;
}
echo '</table>' . PHP_EOL;