# Realtime voice runtime

## Product boundary

`ai-agent/` remains a standalone service beside the Laravel application. It does not call GPT-Live. The voice runtime composes existing providers behind replaceable adapters:

- Speech recognition: the currently cloned Persian ASR / faster-whisper models.
- Reasoning: the user-selected OpenAI-compatible text model through Arvan.
- Speech generation: the local MOSS or Piper service.
- Media/session infrastructure: LiveKit is the target for WebRTC rooms, session transport, and worker dispatch. LiveKit is infrastructure; it does not replace the selected models.

Development must remain runnable without Docker. LiveKit's local server has a development mode; production deployment can add containers later.

## Current behavior

The browser captures mono PCM at 16 kHz in 20 ms packets and sends it over one WebSocket. The server applies energy-based VAD. Whisper is not a native streaming recognizer in this project: provisional recognition re-decodes accumulated audio roughly every two seconds, while only the final transcript is sent to Arvan. Arvan text is streamed, sentence-sized text is sent to MOSS/Piper, and generated PCM is played as it arrives. A new detected utterance interrupts current playback.

This is a streamed, turn-based pipeline. Continuous media transport does not mean continuous ASR or full-duplex model inference. The partial transcript is preview-only and never triggers the text model.

## Target runtime

Each conversation is a persistent session with independent input, reasoning, tool, and output tasks. The state machine coordinates listening, transcript revision, response generation, speaking, barge-in, cancellation, and conversation-history repair. Stable ASR text is surfaced incrementally; uncertain hypotheses remain provisional. TTS starts from complete, speakable clauses and keeps enough context for natural prosody. Tool work runs asynchronously so it cannot block audio ingress or playback.

The target pipeline separates three concerns:

1. **Media plane:** LiveKit/WebRTC transports microphone and generated audio and supports remote clients and concurrent sessions.
2. **Conversation runtime:** a session coordinator manages turn state, interruptions, context, cancellation, queues, and tool execution.
3. **Provider plane:** replaceable STT, text-LLM, and TTS adapters. Arvan, local Persian ASR, and MOSS remain selectable independently.

## Migration sequence

1. **Measure each stage.** Record packet-to-preview, endpoint wait, final STT, first LLM text, and first playable TTS audio. Do not store audio or transcript in telemetry. This first slice is implemented in the live WebSocket path.
2. **Replace repeated full-prefix preview decoding.** Integrate and benchmark an existing streaming Whisper wrapper against the current Persian model and two-core CPU. Keep final recognition as the correction path. LiveKit alone cannot make a non-streaming Whisper model stream.
3. **Extract the session coordinator.** Move per-socket state into an explicit session/turn runtime. Keep audio reception responsive while inference runs; bound queues and coalesce stale partials.
4. **Make response work concurrent.** Stream the LLM, synthesize complete clauses as they become safe to speak, and run tools on independent tasks. On barge-in, stop playback immediately and reconcile the model context with audio the user actually heard.
5. **Add LiveKit transport without provider lock-in.** Keep the current WebSocket path available until browser, interruption, reconnect, and multi-session behavior match. Use LiveKit Agents/Server for media and worker infrastructure, not GPT-Live or LiveKit-hosted inference.
6. **Scale and harden.** Add bounded session limits, lifecycle cleanup, health/readiness, structured traces, deployment configuration, and load/cancellation checks before production traffic.

## Quality gates

- No partial hypothesis is silently committed as the user's final utterance.
- A new speaker turn can interrupt generated audio without waiting for the old LLM/TTS request to finish.
- ASR, LLM, and TTS can be changed independently.
- Every turn exposes stage timings and a correlation ID without logging user audio or transcript.
- Latency targets are set from measured Persian speech on target hardware; they are not guessed from packet size or VAD thresholds.
- Naturalness is reviewed separately from latency. Tiny text chunks, repeated TTS prompts, or hard-coded gaps must not be introduced just to lower first-audio time.
