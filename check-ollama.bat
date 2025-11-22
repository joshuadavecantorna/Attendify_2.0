@echo off
echo Checking Ollama status...
timeout /t 3 /nobreak > nul
curl http://localhost:11434/api/tags
echo.
echo.
echo If you see models listed above, Ollama is working!
echo If you see "models":[] then restart Ollama app from system tray.
pause
