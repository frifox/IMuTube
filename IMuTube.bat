@echo off
color 0a
cd /d %~dp0
if exist in del in
if "%~1"=="" goto out
:in
tools\unlocker in
cmd /u /c echo "%~1">>in
shift
if not "%~1"=="" goto in
:out
tools\php\php tools\auto.php