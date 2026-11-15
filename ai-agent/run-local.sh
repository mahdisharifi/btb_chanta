#!/usr/bin/env bash
set -Eeuo pipefail

AGENT_DIR="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")" && pwd)"
MOSS_DIR="$AGENT_DIR/emotional-tts/moss-tts-nano"
MOSS_PYTHON="$AGENT_DIR/emotional-tts/.venv-moss/bin/python"
ASSISTANT_PYTHON="$AGENT_DIR/.venv/bin/python"
MOSS_URL="http://127.0.0.1:18083"
WEB_URL="http://127.0.0.1:8787"
MOSS_LOG="${TMPDIR:-/tmp}/btb-chanta-moss.log"
moss_pid=""

cleanup() {
  if [[ -n "$moss_pid" ]] && kill -0 "$moss_pid" 2>/dev/null; then
    echo "Stopping MOSS started by this script..."
    kill "$moss_pid" 2>/dev/null || true
    wait "$moss_pid" 2>/dev/null || true
  fi
}
trap cleanup EXIT
trap 'exit 130' INT
trap 'exit 143' TERM

if [[ ! -x "$MOSS_PYTHON" ]]; then
  echo "MOSS Python environment not found: $MOSS_PYTHON" >&2
  exit 1
fi
if [[ ! -x "$ASSISTANT_PYTHON" ]]; then
  echo "Assistant Python environment not found: $ASSISTANT_PYTHON" >&2
  exit 1
fi

echo "Checking MOSS at $MOSS_URL ..."
status="$(curl -fsS --max-time 2 "$MOSS_URL/api/warmup-status" 2>/dev/null || true)"
if [[ -z "$status" ]]; then
  echo "Starting MOSS (first startup can take a little while)..."
  (
    cd "$MOSS_DIR"
    exec "$MOSS_PYTHON" app_onnx.py --host 127.0.0.1 --port 18083 --cpu-threads 2
  ) >"$MOSS_LOG" 2>&1 &
  moss_pid=$!
fi

for _ in $(seq 1 240); do
  status="$(curl -fsS --max-time 2 "$MOSS_URL/api/warmup-status" 2>/dev/null || true)"
  if [[ "$status" == *'"ready":true'* ]]; then
    echo "MOSS is ready."
    break
  fi
  if [[ -n "$moss_pid" ]] && ! kill -0 "$moss_pid" 2>/dev/null; then
    echo "MOSS stopped before becoming ready. Recent log: $MOSS_LOG" >&2
    tail -n 40 "$MOSS_LOG" >&2 || true
    exit 1
  fi
  sleep 1
done

if [[ "$status" != *'"ready":true'* ]]; then
  echo "MOSS did not become ready within 4 minutes. Recent log: $MOSS_LOG" >&2
  tail -n 40 "$MOSS_LOG" >&2 || true
  exit 1
fi

web_status="$(curl -fsS --max-time 2 "$WEB_URL/api/status" 2>/dev/null || true)"
if [[ -n "$web_status" ]]; then
  echo "The assistant is already running at $WEB_URL."
  echo "Both services are ready; open $WEB_URL"
  exit 0
fi

cd "$AGENT_DIR"
echo "Starting the assistant at $WEB_URL (press Ctrl+C to stop)..."
"$ASSISTANT_PYTHON" -m uvicorn app:app --host 127.0.0.1 --port 8787
