# معماری چندجریانی مکالمهٔ صوتی

## Event flow

```text
Browser capture (AudioWorklet, 20 ms PCM)
  ├─ WebSocket audio → VAD / session capture
  │                    └─ bounded ASR queue → one ASR worker → partial/final events
  ├─ assistant audio → bounded HTTP PCM stream → one Piper worker
  └─ control events  → stop playback / cancel LLM / invalidate TTS generation

Final transcript → selected text LLM (SSE in browser) → Persian speech chunker
               → Piper generation slots → PCM ordered by one worker → Web Audio
```

This is a turn-taking text LLM with streamed input recognition and streamed TTS. It is not a native audio-to-audio model and does not stream live ASR tokens into LLM reasoning by default.

## Ownership and bounds

| Resource | Owner | Bound / policy |
|---|---|---|
| WebSocket outbound events | one `ConversationSession` | ۳۲ events; stale partials coalesce; critical events wait with backpressure |
| ASR pending finals | process-level `RealtimeAsrQueue` | ۸ total, maximum ۲ per session |
| ASR provisional work | process-level queue | one latest pending partial per session; replaced by newer audio |
| ASR execution | one background worker | inference uses an executor; ۶۰ s request deadline, ۳۰ s queue-age deadline |
| Piper pending jobs | process-level `PiperStreamScheduler` | one worker, maximum ۲ pending globally and one pending per session; the pending slot is used behind a canceled native call only |
| Piper PCM output | each request | queue of ۲ chunks, ۱۰ s backpressure deadline; ۶۰ s request and ۳۰ s chunk deadline |
| Per-session transcript revisions | `ConversationSession` | latest ۳۲ utterances retained; cleaned on session close |
| Browser text-to-speech handoff | current turn | maximum ۳ not-yet-completed segments; wait before accepting more |

Configuration defaults can be overridden within bounded ranges using `VOICE_ASR_REQUEST_TIMEOUT_MS`, `VOICE_ASR_QUEUE_TIMEOUT_MS`, `VOICE_TTS_PENDING_LIMIT`, `VOICE_TTS_REQUEST_TIMEOUT_MS`, `VOICE_TTS_CHUNK_TIMEOUT_MS` and `VOICE_TTS_BACKPRESSURE_TIMEOUT_MS`.

## Piper Request Slots

The slot scheduler is a single FIFO worker, not a pool. It calls the synchronous Piper iterator through Starlette's thread pool, so ONNX inference does not block the asyncio event loop. It emits each sentence PCM in iterator order. The async consumer applies backpressure through a bounded two-item queue.

Each job is keyed by `(session_id, generation_id)` and also carries `turn_id`. Barge-in sends all three identities. A queued generation is removed immediately. An active generation is marked canceled and buffered PCM is cleared immediately. Piper's active native inference cannot be safely killed, so it retains the sole worker slot until the current native call returns; its late PCM is discarded. One fresh generation for that session may wait in the bounded queue behind the unwinding call; a second pending generation is rejected. No second inference starts in parallel.

An HTTP stream disconnect invokes the same cancellation path. Request completion removes session and generation indexes exactly once. Scheduler close cancels pending jobs, invalidates the active output, then waits for the worker's bounded native call.

## ASR stays independent

ASR has its own single worker and its own queue; it is not scheduled through Piper's lock or queue. Every Whisper/Taraneh inference is run outside asyncio. During a Piper stream, the microphone WebSocket continues accepting audio and ASR jobs can run.

Native ASR also cannot be forcibly interrupted safely. At its deadline, the client receives a timeout and its eventual transcript is discarded, while the sole ASR lane remains occupied until the native call returns. This prevents a timeout from silently spawning more native CPU work. If inference never returns, ASR availability remains degraded and the bounded queue eventually returns a clear overload response.

## Session and deployment boundary

`ConversationSession` and the event bus are in-process. Run one Uvicorn worker unless WebSocket sessions are pinned to the same worker. Multiple independent workers would have separate Piper/ASR queues and session state, and could exceed the CPU budget. A future multi-process deployment needs sticky routing or a shared broker plus explicit resource partitioning.

## Safety boundary

- Final transcript is the commit point for normal model requests.
- Partial and stable-prefix text is revisioned and retractable; it never triggers product tools.
- Speculative LLM is off by default and its answer is reused only on exact final-text and context match.
- No product-changing tools are registered. `ToolExecutionRegistry` is an interface/test policy only.
- LLM response cancellation is separate from TTS cancellation in the browser, but the current barge-in policy aborts both to avoid spending on stale output.
