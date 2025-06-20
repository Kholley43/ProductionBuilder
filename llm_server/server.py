from fastapi import FastAPI
from pydantic import BaseModel
import requests, os, json

LLAMA_URL = os.getenv('LLAMA_CPP_URL', 'http://127.0.0.1:8080/completion')

app = FastAPI()

class ChatInput(BaseModel):
    messages: list[dict]
    tools: str

@app.post('/v1/chat')
async def chat(inp: ChatInput):
    prompt = build_prompt(inp.messages, inp.tools)
    r = requests.post(LLAMA_URL, json={
        'prompt': prompt,
        'n_predict': 512,
        'stop': ['</assistant>'],
    })
    r.raise_for_status()
    return {'content': r.json()['content']}

def build_prompt(messages: list[dict], tools_json: str) -> str:
    sys = '<system>You are a helpful CodeIgniter scaffolder. You can reply with JSON tool calls matching this schema: ' + tools_json + '</system>'
    conv = ''.join(f"<{m['role']}>{m['content']}</{m['role']}>" for m in messages)
    return sys + conv + '<assistant>' 