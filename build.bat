@echo off
echo Cleaning old build...
if exist "public\build" rmdir /s /q "public\build"
echo Building application...
call npm run build
echo Build complete!
pause