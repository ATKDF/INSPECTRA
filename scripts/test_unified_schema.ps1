# Test unified schema file import
$mysql = 'C:\\xampp\\mysql\\bin\\mysql.exe'
$schema_file = 'C:\\xampp\\htdocs\\inspectra\\database\\inspectra_db_complete_schema.sql'
$schema_content = Get-Content -Path $schema_file -Raw

# Drop any existing inspectra_db and import the unified schema directly
& $mysql -u root -N -e "DROP DATABASE IF EXISTS inspectra_db;"

# Import schema by piping content into the MySQL client; the schema file itself creates and selects inspectra_db
$schema_content | & $mysql -u root 2>&1 | Select-Object -First 20

# Verify table count
$count = & $mysql -u root -N -e "SELECT COUNT(*) AS table_count FROM information_schema.tables WHERE table_schema='inspectra_db';"
Write-Output "Tables in inspectra_db: $count"

# List tables
Write-Output "Tables:"
& $mysql -u root -N -e "SELECT table_name FROM information_schema.tables WHERE table_schema='inspectra_db' ORDER BY table_name;"
