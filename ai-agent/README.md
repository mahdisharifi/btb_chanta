# AI Agent Voice Bridge

Local-first voice chat with a text model. The page sends text requests directly to the configured OpenAI-compatible endpoint; speech recognition and speech synthesis run locally through the cloned `faster-whisper` and `piper1-gpl` projects.

## Local setup (without Docker)

The Piper project documents Python 3.12 for its development environment. From this directory, install the build tools and create a dedicated Python 3.12 environment:

```bash
sudo apt update
sudo apt install -y python3.12-venv python3.12-dev build-essential cmake ninja-build
python3.12 -m venv .venv
source .venv/bin/activate
python -m pip install --upgrade pip setuptools wheel
python -m pip install -r requirements.txt
python -m pip install -e ./faster-whisper
# Piper builds a native espeak bridge and bundles espeak data; install it as a wheel, not editable.
python -m pip uninstall -y piper-tts
python -m pip install ./piper1-gpl
python -m uvicorn app:app --host 127.0.0.1 --port 8787
```

Open `http://127.0.0.1:8787`. Enter the Arvan token in the page and load the model list. The token is held in page memory only; it is not written to browser storage or sent to this local Python service. It is sent directly to the configured HTTPS model API.

Whisper and Piper weights are read from `models/` and stay local. By default, STT uses CPU INT8 with two threads and a five-beam decode. Voice activity filtering is off so quiet word starts are less likely to be trimmed. A complete `faster-whisper-small` directory is preferred automatically; otherwise the service uses `faster-whisper-base`. The browser keeps a playback of each recording, lets you select the input microphone, puts the transcript in the message box for review, and reports basic capture clues such as low speech level, clipping, and long silence. Microphone noise suppression and automatic gain can be switched off to compare capture quality.

## Persian-tuned speech recognition (recommended)

The service includes the cloned [Taraneh-ASR](https://github.com/areffarhadi/persian-asr) project as an optional Persian-specific engine. When its checkpoint and optional packages are installed, it is selected automatically for recordings up to 30 seconds. Longer recordings use the installed faster-whisper model. The Taraneh-WBase checkpoint is about 290 MB and the project publishes MIT-licensed Persian fine-tuned Whisper models. The model authors report 7.88% WER on FLEURS and 10.76% on Common Voice for WBase; those are benchmark results, not a guarantee for every microphone or speaking style.

Install the optional CPU packages from the `ai-agent` directory:

```bash
source .venv/bin/activate
python -m pip install -r requirements-stt-taraneh.txt
```

Then download the checkpoint files (about 290 MB total):

```bash
mkdir -p models/stt/taraneh-wbase
cd models/stt/taraneh-wbase
for file in added_tokens.json config.json generation_config.json merges.txt model.safetensors normalizer.json preprocessor_config.json special_tokens_map.json tokenizer_config.json vocab.json; do
  curl -fL --retry 3 "https://huggingface.co/areffarhadi/Taraneh-WBase/resolve/main/$file" -o "$file" || exit 1
done
cd ../../..
```

Restart Uvicorn after installation. The page's local-engine badge shows which model is active. If the optional packages are missing, it keeps using faster-whisper; it does not send audio to Arvan or download model files automatically.

To add the stronger multilingual `small` model, download its four required files:

```bash
mkdir -p models/stt/faster-whisper-small
cd models/stt/faster-whisper-small
for file in config.json model.bin tokenizer.json vocabulary.txt; do
  curl -fL --retry 3 "https://huggingface.co/Systran/faster-whisper-small/resolve/main/$file" -o "$file" || exit 1
done
```

The app selects `small` automatically after all files are present and the service is restarted.

The interface requests a non-streaming chat completion and uses only the assistant's final `message.content` for display and speech. Separate reasoning fields are ignored, and common `<think>` / `<analysis>` blocks are stripped before Piper receives the text.
