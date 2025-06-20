# Local LLM Server

This folder contains a minimal FastAPI wrapper around `llama.cpp` (or any HTTP completion engine).

## Quick start

1. Build or download a GGUF model, e.g. `llama-2-7b-chat.gguf` and place it in `./models`.
2. Build llama.cpp and start the HTTP server:

```bash
make -j -C llama.cpp
./llama.cpp/server -m ./models/llama-2-7b-chat.gguf -c 4096 -ngl 32 -r 0.3
```

3. In a new shell, create a virtualenv and run the FastAPI facade:

```bash
cd llm_server
python -m venv .venv && source .venv/bin/activate
pip install -r requirements.txt
uvicorn server:app --port 8000
```

The Builder app will POST to `http://127.0.0.1:8000/v1/chat`. 