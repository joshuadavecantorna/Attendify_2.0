# Ollama AI Chatbot Setup for Railway Deployment

This guide explains how to connect your Railway-hosted Attendify app to your local Ollama model.

## Prerequisites

- Ollama installed and running on your local machine
- Ngrok installed on your local machine
- Railway project deployed

## Setup Steps

### 1. Start Ollama on Your Local Machine

Make sure Ollama is running:
```bash
# Check if Ollama is running
curl http://localhost:11434

# Start Ollama if not running
ollama serve
```

### 2. Create Ngrok Tunnel for Ollama

Open a terminal and run:
```bash
ngrok http 11434
```

You'll see output like:
```
Forwarding  https://abc-123-xyz.ngrok-free.app -> http://localhost:11434
```

**Copy the https URL** (e.g., `https://abc-123-xyz.ngrok-free.app`)

### 3. Update Railway Environment Variables

1. Go to your Railway project dashboard
2. Click on your service
3. Go to **Variables** tab
4. Add/Update these variables:

```env
OLLAMA_HOST=https://abc-123-xyz.ngrok-free.app
OLLAMA_MODEL=qwen2.5:7b
OLLAMA_TIMEOUT=300
```

5. Click **Deploy** to apply changes

### 4. Verify Connection

Once deployed, test the chatbot:
1. Open your Railway app URL
2. Click the chat icon
3. Send a test message
4. Check Railway logs for any errors

## Important Notes

### Ngrok Free Tier Limitations:
- URL changes every time you restart ngrok
- 40 connections/minute limit
- Must update Railway env vars when URL changes

### Keeping Ngrok Running:
```bash
# Run ngrok in background (Windows PowerShell)
Start-Process -NoNewWindow ngrok http 11434

# Or use a separate terminal window that stays open
```

### Alternative: Ngrok Static Subdomain

Upgrade to ngrok paid plan for a static URL:
```bash
ngrok http 11434 --subdomain=your-static-name
# URL will always be: https://your-static-name.ngrok.io
```

Then you only need to set Railway env once:
```env
OLLAMA_HOST=https://your-static-name.ngrok.io
```

## Troubleshooting

### Chatbot Not Responding

1. **Check if Ollama is running:**
   ```bash
   curl http://localhost:11434
   ```

2. **Check if ngrok tunnel is active:**
   - Visit the ngrok URL in your browser
   - You should see Ollama's API response

3. **Check Railway logs:**
   ```bash
   railway logs
   ```
   Look for connection errors to Ollama

4. **Verify environment variables:**
   ```bash
   railway variables
   ```
   Ensure OLLAMA_HOST is set correctly

### Connection Timeout

If you see timeout errors:
1. Increase `OLLAMA_TIMEOUT` in Railway (try 600)
2. Check your internet upload speed
3. Consider using a smaller model

### Model Not Found

If Railway says model not found:
1. Check model is pulled locally:
   ```bash
   ollama list
   ```

2. Pull the model if missing:
   ```bash
   ollama pull qwen2.5:7b
   ```

3. Update `OLLAMA_MODEL` in Railway to match

## Security Considerations

### Ngrok Free Tier:
- URLs are publicly accessible
- Anyone with the URL can access your Ollama
- Consider adding authentication or using paid ngrok

### Production Recommendations:

1. **Use Ollama Cloud** (when available):
   - No need for local tunneling
   - Better reliability
   - Set `OLLAMA_HOST` to cloud URL

2. **Host Ollama on VPS**:
   - Deploy Ollama on a server
   - Use direct HTTPS connection
   - No tunneling needed

3. **Use OpenAI/Anthropic API**:
   - More reliable for production
   - No local setup needed
   - Pay per usage

## Monitoring

Check if your setup is working:

```bash
# Test local Ollama
curl http://localhost:11434/api/generate -d '{
  "model": "qwen2.5:7b",
  "prompt": "Hello"
}'

# Test ngrok tunnel
curl https://your-ngrok-url.ngrok-free.app/api/generate -d '{
  "model": "qwen2.5:7b", 
  "prompt": "Hello"
}'
```

## Updating Ngrok URL

When your ngrok URL changes:

### Option 1: Railway Dashboard
1. Go to Variables
2. Update `OLLAMA_HOST`
3. Click Deploy

### Option 2: Railway CLI
```bash
railway variables set OLLAMA_HOST=https://new-ngrok-url.ngrok-free.app
```

### Option 3: Automation Script

Create `update-railway-ollama.ps1`:
```powershell
# Get current ngrok URL
$ngrokUrl = (Invoke-RestMethod http://localhost:4040/api/tunnels).tunnels[0].public_url

# Update Railway
railway variables set OLLAMA_HOST=$ngrokUrl

Write-Host "Updated Railway OLLAMA_HOST to: $ngrokUrl"
```

Run after starting ngrok:
```powershell
.\update-railway-ollama.ps1
```

## Cost Comparison

### Free (Current Setup):
- ✅ Free Ollama (local)
- ✅ Free ngrok (with limitations)
- ❌ Must keep laptop running
- ❌ URL changes frequently

### Ngrok Pro ($8/month):
- ✅ Static subdomain
- ✅ Higher rate limits
- ❌ Still need laptop running

### VPS + Ollama ($5-20/month):
- ✅ Always online
- ✅ Static URL
- ✅ No laptop needed
- ❌ Setup complexity

### OpenAI API (~$0.01/1K tokens):
- ✅ Most reliable
- ✅ No setup needed
- ❌ Pay per usage
- ❌ Less privacy

## Questions?

Check Railway logs for errors:
```bash
railway logs --follow
```

Test Ollama connection from Railway:
```bash
railway run php artisan tinker
>>> app(\App\Services\OllamaService::class)->healthCheck()
```
