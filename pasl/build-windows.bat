@echo off
REM Build PASL-generated C as a Windows EXE
set SRC=%1
if "%SRC%"=="" set SRC=sum.c
where cl >nul 2>&1
if %ERRORLEVEL%==0 (
  cl /O2 /nologo %SRC% /Fe:%~n1.exe
  exit /b %ERRORLEVEL%
)
where gcc >nul 2>&1
if %ERRORLEVEL%==0 (
  gcc -O2 -o %~n1.exe %SRC%
  exit /b %ERRORLEVEL%
)
echo Install MSVC (cl) or MinGW gcc, or cross-build:
echo   x86_64-w64-mingw32-gcc -O2 -o %~n1.exe %SRC%
exit /b 1
