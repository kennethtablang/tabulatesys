@echo off
REM Starts the Tabulation System so judge and tabulator PCs on the same network can connect.
REM Keep this window open during the event. Press Ctrl+C to stop.
cd /d "%~dp0"

echo.
echo  Tabulation Management System
echo  ----------------------------
echo  On THIS computer open:   http://127.0.0.1:8000
echo  On judge / tabulator PCs open one of these addresses:
for /f "tokens=2 delims=:" %%a in ('ipconfig ^| findstr /c:"IPv4 Address"') do (
    for /f "tokens=* delims= " %%b in ("%%a") do echo      http://%%b:8000
)
echo.
echo  (Recommended for 3+ judges: use XAMPP Apache instead, see README.)
echo.

C:\xampp\php\php.exe artisan serve --host=0.0.0.0 --port=8000
pause
