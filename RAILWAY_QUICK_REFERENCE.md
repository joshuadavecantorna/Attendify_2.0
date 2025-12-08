# 🚀 Ollama + Railway Quick Setup

## One-Command Setup

```powershell
.\start-ollama-railway.ps1
```

This will:
1. ✅ Check if Ollama is running
2. ✅ Start ngrok tunnel
3. ✅ Update Railway automatically
4. ✅ Keep monitoring connection

---

## Manual Setup (Step by Step)

### Step 1: Start Ollama
```powershell
ollama serve
```

### Step 2: Start Ngrok
```powershell
ngrok http 11434
```
Copy the HTTPS URL (e.g., `https://abc123.ngrok-free.app`)

### Step 3: Update Railway
```powershell
railway variables set OLLAMA_HOST=https://abc123.ngrok-free.app
```

---

## Railway Environment Variables Needed

```env
OLLAMA_HOST=https://your-ngrok-url.ngrok-free.app
OLLAMA_MODEL=qwen2.5:7b
OLLAMA_TIMEOUT=300
```

---

## Quick Commands

### Check Ollama Status
```powershell
curl http://localhost:11434
```

### Check Ngrok Status
```powershell
curl http://localhost:4040/api/tunnels
```

### View Railway Logs
```powershell
railway logs
```

### Test Ollama Connection
```powershell
railway run php artisan tinker
>>> app(\App\Services\OllamaService::class)->healthCheck()
```

---

## Troubleshooting

### ❌ Chatbot not responding
1. Check if Ollama is running: `curl http://localhost:11434`
2. Check if ngrok is running: Visit ngrok URL in browser
3. Check Railway logs: `railway logs`
4. Verify Railway env vars: `railway variables`

### ❌ Connection timeout
- Increase `OLLAMA_TIMEOUT` to 600 in Railway
- Check your internet upload speed
- Try a smaller model

### ❌ Model not found
```powershell
ollama list                    # Check available models
ollama pull qwen2.5:7b        # Pull if missing
```

---

## Important Notes

⚠️ **Keep These Running:**
- Ollama server
- Ngrok tunnel
- Your laptop powered on

⚠️ **Ngrok Free Limitations:**
- URL changes every restart
- 40 requests/minute limit
- Must update Railway when URL changes

💡 **Tip:** Use `start-ollama-railway.ps1` to automate everything!

---

## Alternative: Ngrok Static URL ($8/month)

```powershell
ngrok http 11434 --subdomain=your-name
```

Then set Railway once:
```env
OLLAMA_HOST=https://your-name.ngrok.io
```

No need to update URL anymore!

---

## Files Reference

- `start-ollama-railway.ps1` - Automated setup script
- `update-railway-ollama.ps1` - Update Railway URL only
- `OLLAMA_RAILWAY_SETUP.md` - Full documentation
- `RAILWAY_QUICK_REFERENCE.md` - This file

---

## Need Help?

Check the full guide: `OLLAMA_RAILWAY_SETUP.md`
