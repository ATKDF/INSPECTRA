# Start any MySQL service and test mysql client connectivity
$found = $false
$svcs = Get-Service -ErrorAction SilentlyContinue | Where-Object { $_.Name -match 'mysql' -or $_.DisplayName -match 'MySQL' }
if ($svcs) {
    $found = $true
    foreach ($s in $svcs) {
        Write-Output "Service: $($s.Name) - $($s.Status)"
        if ($s.Status -ne 'Running') {
            try { Start-Service -Name $s.Name -ErrorAction Stop; Write-Output "Started service $($s.Name)" } catch { Write-Output "Failed to start service $($s.Name): $($_.Exception.Message)" }
        }
    }
}
# Check mysqld process
if (Get-Process -Name mysqld -ErrorAction SilentlyContinue) {
    Write-Output 'mysqld process running'
} else {
    Write-Output 'mysqld process not running'
}
# If no service found, try starting mysqld.exe directly
if (-not $found) {
    Write-Output 'No MySQL service found; attempting to start mysqld.exe directly'
    try { Start-Process -FilePath 'C:\\xampp\\mysql\\bin\\mysqld.exe' -ArgumentList '--standalone' -WindowStyle Hidden -PassThru | Out-Null; Start-Sleep -Seconds 3; Write-Output 'Started mysqld.exe process' } catch { Write-Output "Failed to start mysqld.exe: $($_.Exception.Message)" }
}
# Test connection
try {
    $ver = & 'C:\\xampp\\mysql\\bin\\mysql.exe' -u root -N -e "SELECT VERSION();"
    if ($ver) { Write-Output "MYSQL_VERSION: $ver" } else { Write-Output 'MYSQL_CONN_FAILED' }
} catch { Write-Output 'MYSQL_CONN_FAILED' }
