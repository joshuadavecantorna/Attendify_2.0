# Quick Start Script for Ollama + Railway Setup
# This script starts both ngrok and updates Railway in one command

Write-Host "🚀 Starting Ollama Railway Setup..." -ForegroundColor Cyan
Write-Host ""

# Check if Ollama is running
Write-Host "1️⃣  Checking Ollama..." -ForegroundColor Yellow
try {
    $ollamaTest = Invoke-RestMethod -Uri "http://localhost:11434" -TimeoutSec 3 -ErrorAction Stop
    Write-Host "   ✅ Ollama is running" -ForegroundColor Green
}
catch {
    Write-Host "   ❌ Ollama is not running" -ForegroundColor Red
    Write-Host ""
    Write-Host "   Please start Ollama first:" -ForegroundColor Yellow
    Write-Host "   ollama serve" -ForegroundColor White
    Write-Host ""
    Write-Host "Press any key to exit..."
    $null = $Host.UI.RawUI.ReadKey("NoEcho,IncludeKeyDown")
    exit
}

Write-Host ""
Write-Host "2️⃣  Starting ngrok tunnel..." -ForegroundColor Yellow

# Start ngrok in background
$ngrokProcess = Start-Process -FilePath "ngrok" -ArgumentList "http 11434" -PassThru -WindowStyle Normal

# Wait for ngrok to start
Write-Host "   ⏳ Waiting for ngrok to initialize..." -ForegroundColor Gray
Start-Sleep -Seconds 5

# Get ngrok URL
try {
    $ngrokApi = Invoke-RestMethod -Uri "http://localhost:4040/api/tunnels"
    $tunnel = $ngrokApi.tunnels | Where-Object { $_.config.addr -like "*11434*" } | Select-Object -First 1
    
    if ($tunnel) {
        $ngrokUrl = $tunnel.public_url
        Write-Host "   ✅ Ngrok tunnel created: $ngrokUrl" -ForegroundColor Green
    }
    else {
        Write-Host "   ❌ Could not find ngrok tunnel" -ForegroundColor Red
        exit
    }
}
catch {
    Write-Host "   ❌ Could not connect to ngrok API" -ForegroundColor Red
    Write-Host "   $($_.Exception.Message)" -ForegroundColor Gray
    exit
}

Write-Host ""
Write-Host "3️⃣  Updating Railway..." -ForegroundColor Yellow

# Check if Railway CLI is installed
$railwayInstalled = Get-Command railway -ErrorAction SilentlyContinue

if ($railwayInstalled) {
    railway variables set OLLAMA_HOST=$ngrokUrl
    Write-Host "   ✅ Railway OLLAMA_HOST updated" -ForegroundColor Green
}
else {
    Write-Host "   ⚠️  Railway CLI not found" -ForegroundColor Yellow
    Write-Host "   Please update manually in Railway dashboard" -ForegroundColor Gray
}

Write-Host ""
Write-Host "✅ Setup Complete!" -ForegroundColor Green
Write-Host ""
Write-Host "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━" -ForegroundColor DarkGray
Write-Host "📋 Configuration Summary:" -ForegroundColor Cyan
Write-Host "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━" -ForegroundColor DarkGray
Write-Host "Ollama:  " -NoNewline -ForegroundColor White
Write-Host "http://localhost:11434" -ForegroundColor Magenta
Write-Host "Ngrok:   " -NoNewline -ForegroundColor White
Write-Host "$ngrokUrl" -ForegroundColor Magenta
Write-Host "Railway: " -NoNewline -ForegroundColor White
if ($railwayInstalled) {
    Write-Host "Updated ✓" -ForegroundColor Green
} else {
    Write-Host "Manual update needed" -ForegroundColor Yellow
}
Write-Host "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━" -ForegroundColor DarkGray
Write-Host ""

if (-not $railwayInstalled) {
    Write-Host "📝 Manual Railway Setup Required:" -ForegroundColor Yellow
    Write-Host "1. Go to https://railway.app" -ForegroundColor White
    Write-Host "2. Select your Attendify project" -ForegroundColor White
    Write-Host "3. Go to Variables tab" -ForegroundColor White
    Write-Host "4. Set: OLLAMA_HOST = $ngrokUrl" -ForegroundColor Cyan
    Write-Host "5. Click Deploy" -ForegroundColor White
    Write-Host ""
}

Write-Host "🎯 Next Steps:" -ForegroundColor Cyan
Write-Host "1. Deploy your Railway app (if not auto-deployed)" -ForegroundColor White
Write-Host "2. Test the chatbot on your Railway URL" -ForegroundColor White
Write-Host "3. Keep this window open while using the app" -ForegroundColor White
Write-Host ""

Write-Host "⚠️  Important:" -ForegroundColor Red
Write-Host "   - Keep Ollama running on this laptop" -ForegroundColor Yellow
Write-Host "   - Keep this ngrok window open" -ForegroundColor Yellow
Write-Host "   - If ngrok restarts, run this script again" -ForegroundColor Yellow
Write-Host ""

Write-Host "Press Ctrl+C to stop ngrok and exit"
Write-Host ""

# Keep script running
try {
    while ($true) {
        Start-Sleep -Seconds 60
        # Check if ngrok is still running
        try {
            $null = Invoke-RestMethod -Uri "http://localhost:4040/api/tunnels" -TimeoutSec 2
        }
        catch {
            Write-Host ""
            Write-Host "❌ Ngrok tunnel lost! Exiting..." -ForegroundColor Red
            break
        }
    }
}
finally {
    Write-Host ""
    Write-Host "🛑 Stopping ngrok..." -ForegroundColor Yellow
    if ($ngrokProcess -and !$ngrokProcess.HasExited) {
        Stop-Process -Id $ngrokProcess.Id -Force
    }
    Write-Host "✅ Cleanup complete" -ForegroundColor Green
}
