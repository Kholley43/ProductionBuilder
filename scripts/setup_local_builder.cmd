@echo off
REM =============================================
REM  Automated Local Builder Setup (Windows 11)
REM  This script installs prerequisites (via winget),
REM  builds llama.cpp, downloads a 7B chat model,
REM  launches the llama.cpp HTTP server, FastAPI façade,
REM  and the CodeIgniter dev server.
REM ---------------------------------------------
REM  Usage:  run from an *elevated* Developer CMD.
REM  Ex:  scripts\setup_local_builder.cmd
REM ---------------------------------------------

REM Stop on errors
setlocal enabledelayedexpansion

REM -------- Variables --------
set "PROJECT_DIR=%~dp0..\.."  REM assumes script lives in scripts/
set "SRC_DIR=%USERPROFILE%\projects"
set "MODEL_DIR=%USERPROFILE%\models"
set "LLAMA_REPO=https://github.com/ggerganov/llama.cpp"
set "MODEL_REPO=https://huggingface.co/TheBloke/Llama-2-7B-Chat-GGUF"
set "MODEL_FILE=llama-2-7b-chat.Q4_K_M.gguf"

REM -------- Install prereqs with winget --------
for %%p in ("Microsoft.VisualStudio.2022.BuildTools" "Kitware.CMake" "Git.Git") do (
    echo Installing %%p ...
    winget install --id %%p -e --accept-source-agreements --accept-package-agreements
)

REM Git LFS init
where git >nul 2>nul || (
    echo Git not found in PATH. Aborting.
    exit /b 1
)

git lfs install

REM -------- Build llama.cpp --------
if not exist "%SRC_DIR%" mkdir "%SRC_DIR%"
cd /d "%SRC_DIR%"

if not exist "llama.cpp" (
    git clone %LLAMA_REPO%
)
cd llama.cpp

cmake -B build -DCMAKE_BUILD_TYPE=Release
cmake --build build --config Release -- /m

REM -------- Download model --------
if not exist "%MODEL_DIR%" mkdir "%MODEL_DIR%"
cd /d "%MODEL_DIR%"

if not exist "Llama-2-7B-Chat-GGUF" (
    git clone %MODEL_REPO%
)
copy /Y "Llama-2-7B-Chat-GGUF\%MODEL_FILE%" "%MODEL_FILE%"

REM -------- Launch llama.cpp server (background) --------
start "LLM Server" "%SRC_DIR%\llama.cpp\build\Release\server.exe" -m "%MODEL_DIR%\%MODEL_FILE%" -c 4096 -ngl 32

echo Waiting 5 seconds for llama.cpp server to start...
ping -n 6 127.0.0.1 >nul

REM -------- Launch FastAPI façade --------
cd /d "%PROJECT_DIR%\llm_server"
python -m venv .venv
call .venv\Scripts\activate.bat
pip install -r requirements.txt
start "FastAPI" cmd /k "call .venv\Scripts\activate.bat && uvicorn server:app --port 8000"

echo Waiting 3 seconds for FastAPI to start...
ping -n 4 127.0.0.1 >nul

REM -------- Launch CodeIgniter dev server --------
cd /d "%PROJECT_DIR%"
start "CodeIgniter" cmd /k "php spark serve --port 8081"

echo =============================================
echo   All services launched!
echo   Open http://localhost:8081/chat.html in your browser.
echo =============================================

endlocal 