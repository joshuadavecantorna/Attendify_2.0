# Update Railway Ollama Host with Current Ngrok URL
# Run this script after starting ngrok to automatically update Railway

Write-Host "🔍 Checking ngrok tunnel..." -ForegroundColor Cyan

try {
    # Get ngrok tunnel info
    $ngrokApi = Invoke-RestMethod -Uri "http://localhost:4040/api/tunnels" -ErrorAction Stop
    
    # Find the HTTP/HTTPS tunnel for port 11434
    $tunnel = $ngrokApi.tunnels | Where-Object { $_.config.addr -like "*11434*" } | Select-Object -First 1
    
    if ($tunnel) {
        $ngrokUrl = $tunnel.public_url
        Write-Host "✅ Found ngrok tunnel: $ngrokUrl" -ForegroundColor Green
        
        # Check if Railway CLI is installed
        $railwayInstalled = Get-Command railway -ErrorAction SilentlyContinue
        
        if ($railwayInstalled) {
            Write-Host "🚂 Updating Railway OLLAMA_HOST..." -ForegroundColor Yellow
            
            # Update Railway environment variable
            railway variables set OLLAMA_HOST=$ngrokUrl
            
            Write-Host "✅ Railway OLLAMA_HOST updated successfully!" -ForegroundColor Green
            Write-Host ""
            Write-Host "Next steps:" -ForegroundColor Cyan
            Write-Host "1. Deploy your Railway app to apply changes" -ForegroundColor White
            Write-Host "2. Test the chatbot on your Railway URL" -ForegroundColor White
            Write-Host ""
            Write-Host "Current Ollama URL: $ngrokUrl" -ForegroundColor Magenta
        }
        else {
            Write-Host "⚠️  Railway CLI not found" -ForegroundColor Yellow
            Write-Host ""
            Write-Host "Ngrok URL: $ngrokUrl" -ForegroundColor Magenta
            Write-Host ""
            Write-Host "To install Railway CLI:" -ForegroundColor Cyan
            Write-Host "npm install -g @railway/cli" -ForegroundColor White
            Write-Host ""
            Write-Host "Or manually update in Railway dashboard:" -ForegroundColor Cyan
            Write-Host "1. Go to https://railway.app" -ForegroundColor White
            Write-Host "2. Select your project" -ForegroundColor White
            Write-Host "3. Go to Variables tab" -ForegroundColor White
            Write-Host "4. Set OLLAMA_HOST = $ngrokUrl" -ForegroundColor White
            Write-Host "5. Click Deploy" -ForegroundColor White
        }
    }
    else {
        Write-Host "❌ No ngrok tunnel found for port 11434" -ForegroundColor Red
        Write-Host ""
        Write-Host "Please start ngrok first:" -ForegroundColor Yellow
        Write-Host "ngrok http 11434" -ForegroundColor White
    }
}
catch {
    Write-Host "❌ Error: Cannot connect to ngrok API" -ForegroundColor Red
    Write-Host ""
    Write-Host "Make sure ngrok is running:" -ForegroundColor Yellow
    Write-Host "ngrok http 11434" -ForegroundColor White
    Write-Host ""
    Write-Host "Error details: $($_.Exception.Message)" -ForegroundColor Gray
}

Write-Host ""
Write-Host "Press any key to exit..."
$null = $Host.UI.RawUI.ReadKey("NoEcho,IncludeKeyDown")
