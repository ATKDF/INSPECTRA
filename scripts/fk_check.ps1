$mysql = 'C:\\xampp\\mysql\\bin\\mysql.exe'
$fkrows = & $mysql -u root -D inspectra_db -N -e "SELECT TABLE_NAME, COLUMN_NAME, REFERENCED_TABLE_NAME, REFERENCED_COLUMN_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE CONSTRAINT_SCHEMA='inspectra_db' AND REFERENCED_TABLE_NAME IS NOT NULL;"
foreach ($r in $fkrows) {
    $parts = $r -split "\t"
    if ($parts.Count -lt 4) { continue }
    $child=$parts[0]; $col=$parts[1]; $parent=$parts[2]; $pcol=$parts[3]
    $q = "SELECT '$child' AS child_table, '$col' AS fk_column, COUNT(*) AS orphan_count FROM $child LEFT JOIN $parent ON $child.$col = $parent.$pcol WHERE $parent.$pcol IS NULL;"
    $out = & $mysql -u root -D inspectra_db -N -e $q
    if ($out -eq '') { $out = "$child`t$col`t0" }
    Write-Output $out
}
