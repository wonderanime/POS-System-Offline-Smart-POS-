@echo off
title SmartPOS - Build EXE
echo.
echo  SmartPOS - Build Windows EXE
echo.
where python >nul 2>&1
if %errorlevel% neq 0 (
    echo  [ERROR] Python not found. Install from https://www.python.org/downloads/
    pause
    exit /b 1
)
python -c "import PyInstaller" >nul 2>&1
if %errorlevel% neq 0 (
    echo  Installing PyInstaller...
    python -m pip install --quiet pyinstaller
)
cd /d "%~dp0"
python build_exe.py
if %errorlevel% neq 0 (
    echo.
    echo  [FAILED] Build did not complete.
    pause
    exit /b 1
)
echo.
echo  DONE - dist\SmartPOS\SmartPOS.exe
start "" "%~dp0dist\SmartPOS"
pause
