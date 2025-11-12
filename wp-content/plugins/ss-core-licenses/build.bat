@echo off
REM Build script for SS Core Licenses plugin (Windows)
REM Creates a distributable ZIP file

setlocal enabledelayedexpansion

set "PLUGIN_DIR=%~dp0"
set "PLUGIN_NAME=ss-core-licenses"
set "BUILD_DIR=%PLUGIN_DIR%dist"

REM Extract version from plugin file
for /f "tokens=2 delims=: " %%a in ('findstr /C:"Version:" "%PLUGIN_DIR%ss-core-licenses.php"') do set "VERSION=%%a"
set "VERSION=%VERSION: =%"
set "ZIP_NAME=%PLUGIN_NAME%-%VERSION%.zip"

echo Building %PLUGIN_NAME% version %VERSION%...

REM Create build directory
if not exist "%BUILD_DIR%" mkdir "%BUILD_DIR%"

REM Create temporary directory
set "TEMP_DIR=%TEMP%\%PLUGIN_NAME%-build"
if exist "%TEMP_DIR%" rmdir /s /q "%TEMP_DIR%"
mkdir "%TEMP_DIR%"

REM Copy plugin files (excluding build artifacts)
echo Copying plugin files...
xcopy /E /I /Y /EXCLUDE:build-exclude.txt "%PLUGIN_DIR%*" "%TEMP_DIR%\%PLUGIN_NAME%\" >nul 2>&1

REM Create ZIP file (requires PowerShell)
echo Creating ZIP file...
powershell -Command "Compress-Archive -Path '%TEMP_DIR%\%PLUGIN_NAME%' -DestinationPath '%BUILD_DIR%\%ZIP_NAME%' -Force"

REM Cleanup
rmdir /s /q "%TEMP_DIR%"

echo.
echo Build complete: %BUILD_DIR%\%ZIP_NAME%
echo.

endlocal

