@echo off
title SmartPOS
echo.
echo  SmartPOS - Starting...
echo.
where python >nul 2>&1
if %errorlevel% neq 0 (
    echo  [ERROR] Python not found. Install from https://www.python.org/downloads/
    echo  Check "Add Python to PATH" during install.
    pause
    exit /b 1
)
cd /d "%~dp0"
python launcher\launch.py
if %errorlevel% neq 0 (
    echo.
    echo  [ERROR] SmartPOS failed to start. See message above.
    pause
)
