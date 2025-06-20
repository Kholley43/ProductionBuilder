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
    if tools_json.strip():
        sys = (
            '<system>You are a helpful assistant for a CodeIgniter builder chat. '
            'First, converse naturally. When the user clearly requests a CodeIgniter build action, you may call one of the JSON tools described below. '
            'If you are missing any required information (e.g. entity name, field list, or confirmation), ask follow-up questions conversationally BEFORE invoking the tool. '
            'Only when you have all the details, reply with EXACTLY one JSON object matching the chosen tool schema and nothing else. '
            'Tool schema: ' + tools_json + '</system>'
        )
    else:
        sys = '<system>You are a helpful assistant for a CodeIgniter builder chat. Answer conversationally. Do not reference any scaffolder schema unless the user explicitly asks about tools.</system>'
    conv = ''.join(f"<{m['role']}>{m['content']}</{m['role']}>" for m in messages)
    return sys + conv + '<assistant>' 