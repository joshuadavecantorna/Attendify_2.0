# Start Ollama with Localtunnel (Better than ngrok for backend access)
# Localtunnel doesn't block automated requests like ngrok free tier

Write-Host "`n[STARTING] Ollama Localtunnel Setup..." -ForegroundColor Cyan

# Check if Ollama is running
Write-Host "`n[STEP 1] Checking Ollama status..." -ForegroundColor Yellow
try {
    $ollama = Invoke-RestMethod -Uri "http://localhost:11434" -Method Get -TimeoutSec 3
    Write-Host "[SUCCESS] Ollama is running!" -ForegroundColor Green
} catch {
    Write-Host "[ERROR] Ollama is NOT running!" -ForegroundColor Red
    Write-Host "   Please start Ollama first: ollama serve" -ForegroundColor Yellow
    exit 1
}

# Start localtunnel
Write-Host "`n[STEP 2] Starting Localtunnel..." -ForegroundColor Yellow
Write-Host "   Note: Localtunnel doesn't block backend requests (unlike ngrok free)" -ForegroundColor Gray

# Start localtunnel in background
$ltProcess = Start-Process -FilePath "npx" -ArgumentList "localtunnel --port 11434" -PassThru -WindowStyle Normal

Write-Host "[SUCCESS] Localtunnel process started (PID: $($ltProcess.Id))" -ForegroundColor Green

# Wait for tunnel to establish
Write-Host "`n[STEP 3] Waiting for tunnel to establish..." -ForegroundColor Yellow
Start-Sleep -Seconds 3

# Get tunnel URL (localtunnel prints to stderr, need to check process)
Write-Host "`n[INFO] Localtunnel URL should be displayed in the new window" -ForegroundColor Cyan
Write-Host "   Format: https://something-something-something.loca.lt" -ForegroundColor Gray

Write-Host "`n[STEP 4] Next Steps:" -ForegroundColor Yellow
Write-Host "   1. Copy the localtunnel URL from the terminal window" -ForegroundColor White
Write-Host "   2. Update Railway environment variable:" -ForegroundColor White
Write-Host "      OLLAMA_HOST=https://your-subdomain.loca.lt" -ForegroundColor Cyan
Write-Host "   3. Railway will automatically redeploy" -ForegroundColor White
Write-Host "   4. Test your chatbot!" -ForegroundColor White

Write-Host "`n[PRO TIP]:" -ForegroundColor Magenta
Write-Host "   Localtunnel URLs are stable for the session" -ForegroundColor Gray
Write-Host "   No ngrok browser warnings or 403 errors!" -ForegroundColor Gray

Write-Host "`n[WARNING] To stop: Close the localtunnel window or use Ctrl+C" -ForegroundColor Yellow
Write-Host ""
