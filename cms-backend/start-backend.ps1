Set-Location $PSScriptRoot

$hosts = @("127.0.0.1", "localhost", "0.0.0.0")
$maxPort = 9010
$targetHost = $null
$targetPort = $null

foreach ($host in $hosts) {
    for ($port = 9000; $port -le $maxPort; $port++) {
        $listener = $null
        try {
            $listener = Get-NetTCPConnection -LocalPort $port -State Listen -ErrorAction Stop
        } catch {
            $listener = $null
        }

        if (-not $listener) {
            $targetHost = $host
            $targetPort = $port
            break
        }
    }

    if ($targetPort) { break }
}

if (-not $targetPort) {
    Write-Host "No free port found from 9000 to $maxPort. Stop any running web servers and try again."
    exit 1
}

Write-Host "Starting backend on http://$targetHost:$targetPort"
php artisan serve --host=$targetHost --port=$targetPort
