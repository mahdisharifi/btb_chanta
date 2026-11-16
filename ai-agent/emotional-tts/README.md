# Persian voice assistant TTS

The main voice-assistant UI now offers Piper and MOSS as selectable answer-voice engines. The MOSS ONNX server runs separately on `127.0.0.1:18083`; the existing assistant backend proxies requests locally, so the browser keeps using one UI and one origin. No Docker or cloud audio API is involved.

## Live conversation streaming

The assistant sends text-model requests with OpenAI-compatible SSE streaming. It starts speech synthesis at sentence boundaries while the model is still producing later sentences. Both Piper and MOSS return raw PCM chunks to the browser as they are synthesized; a replayable WAV player is added after the streamed reply finishes. MOSS uses its `/api/generate-stream/start` PCM endpoint. If the selected model gateway declines SSE, the client retries a regular JSON response; TTS still streams, but can only start after that full text response arrives.

Live conversation mode keeps one local WebSocket open and sends 16 kHz mono PCM while the microphone is active. The provisional Whisper hypothesis is shown in a separate, explicitly temporary preview; it never edits the message box or becomes the model input. Only the final utterance transcript is added as the user's message and sent to the text model. Speaking again interrupts browser playback and cancels the active text/TTS turn. Manual recording remains available for review-before-send.

The MOSS stream close request now propagates cancellation into the ONNX frame-generation loop. Cancellation is checked between generated frames, so a barge-in does not leave a completed reply consuming CPU in the background.

MOSS-TTS-Nano supports Persian, reference-audio voice cloning, and CPU inference. It does **not** expose named emotion controls such as happy or sad. A reference recording may carry some of its speaker style into the output; test this by ear, since it is not a guaranteed emotion switch. The default Mana reference was synthesized by Piper, so it can carry a synthetic timbre into MOSS. For more natural prosody, use a clean 8–15 second human Persian recording with the speaking style you want. The assistant UI accepts an optional replacement clip and automatically converts browser recordings such as WebM/Opus into WAV for MOSS. The assistant now sends longer voice-clone segments and the local ONNX runtime inserts shorter gaps between segments.

## Start both local services

From a terminal, start or reuse MOSS and then start the assistant with one command:

```bash
cd /home/rafael/btb_chanta/ai-agent
./run-local.sh
```

The script reuses a healthy MOSS server if one is already listening on port `18083`; otherwise it starts MOSS in its `.venv-moss` environment, waits for its CPU warmup to finish, then starts the assistant. Press `Ctrl+C` to stop the assistant and any MOSS process that this script started.

To start the services manually instead, use the two terminal commands below.

In one terminal, run the MOSS ONNX server:

```bash
cd /home/rafael/btb_chanta/ai-agent/emotional-tts
source .venv-moss/bin/activate
cd moss-tts-nano
python app_onnx.py --host 127.0.0.1 --port 18083 --cpu-threads 2
```

In another terminal, run the assistant as usual:

```bash
cd /home/rafael/btb_chanta/ai-agent
source .venv/bin/activate
python -m uvicorn app:app --host 127.0.0.1 --port 8787
```

Open <http://127.0.0.1:8787>, choose **MOSS** under “موتور صدای پاسخ”, then send a text or voice message. By default, the Persian Mana reference is used. Choose a short clean Persian audio file in the optional reference field to try another voice. The backend disables MOSS's Chinese/English WeText processing for these Persian replies and keeps its general text cleanup enabled.

The upstream MOSS environment is created and installed as described below. On its first launch, MOSS downloads public ONNX TTS and audio-tokenizer files into `moss-tts-nano/models`; no gated-model approval is needed. The ONNX assets are about 740 MB combined. The 2-core laptop may generate audio slowly.

```bash
cd /home/rafael/btb_chanta/ai-agent/emotional-tts
python3.12 -m venv .venv-moss
source .venv-moss/bin/activate
python -m pip install --upgrade pip setuptools wheel
cd moss-tts-nano
python -m pip install -r requirements.txt
```

## Emotion-capable model option

The separate VoxCPM wrapper in `server.py` and `web/` supports named Persian emotion captions, but its weights are gated and the request currently needs author approval. We are not using it as the immediate path. The published checkpoint is also large for this 2-core, 16 GB laptop. Keep it as a future GPU-host experiment if access is granted.

## Piper baseline

The original Piper engine remains under `ai-agent/app.py` with its Persian Mana voice. Markdown and list markers are cleaned before synthesis in the browser client. Piper's controls change pacing and signal variation; they do not create true emotion categories. MOSS remains an experimental alternate while the main UI can switch back to Piper at any time.
