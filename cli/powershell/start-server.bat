@echo off
REM MX2LM PowerShell Static Server Launcher
REM KUHUL Class: api.local.static

setlocal

set PORT=8080
set ROOT=.\public

REM Parse arguments
:parse
if "%~1"=="" goto run
if /i "%~1"=="-port" (
    set PORT=%~2
    shift
    shift
    goto parse
)
if /i "%~1"=="-root" (
    set ROOT=%~2
    shift
    shift
    goto parse
)
shift
goto parse

:run
echo.
echo ═══════════════════════════════════════════════════════════
echo   MX2LM Static Server Launcher
echo   KUHUL Class: api.local.static
echo ═══════════════════════════════════════════════════════════
echo.

powershell -ExecutionPolicy Bypass -File "%~dp0server.ps1" -Port %PORT% -Root "%ROOT%"

endlocal
