$base = Split-Path -Parent $MyInvocation.MyCommand.Path
Start-Process powershell -ArgumentList "-NoProfile -ExecutionPolicy Bypass -File \"$base\cms-backend\start-backend.ps1\""
Start-Process powershell -ArgumentList "-NoProfile -ExecutionPolicy Bypass -File \"$base\cms-frontend\start-frontend.ps1\""
