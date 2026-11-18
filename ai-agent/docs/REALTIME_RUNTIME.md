# Realtime voice runtime

## Product boundary

`ai-agent/` remains a standalone service beside the Laravel application. It does not call GPT-Live. The voice runtime composes existing providers behind replaceable adapters:

- Speech recognition: the currently cloned Persian ASR / faster-whisper models, serialized through one bounded ASR lane.
- Reasoning: the user-selected OpenAI-compatible text model through Arvan.
- Speech generation: the local MOSS or Piper service.
- Media/session infrastructure: the current browser WebSocket, SSE and Web Audio path. LiveKit/WebRTC is optional future infrastructure, not a requirement for this orchestration layer.

The service remains runnable without Docker. Alternate media transport can be evaluated later if measurements justify it.

## Current behavior

The browser captures mono PCM at 16 kHz in 20 ms packets and sends it over one WebSocket. The server applies energy-based VAD. Whisper is not a native streaming recognizer: provisional recognition re-decodes accumulated audio at a configurable interval (2 seconds by default). Final transcript jobs have priority over one coalescing preview per session; pending finals are bounded to eight process-wide and two per session. A new utterance can be captured while the prior utterance's final ASR is running. ASR and Piper each have one independent worker, and their blocking inference runs outside the asyncio event loop. Piper accepts at most two pending jobs, two PCM chunks per response, and one outstanding job per session. WebSocket events are serialized, and the client tracks utterance revisions and audio generation IDs. Arvan text is streamed, safe sentence-sized text is sent to Piper, and generated PCM is played as it arrives. A new detected utterance interrupts playback and invalidates the old generation.

This is a streamed, turn-based text-model pipeline. Continuous media transport does not mean native streaming ASR or full-duplex model inference. Partial transcript is preview-only by default; optional speculative LLM processing is guarded by `ENABLE_SPECULATIVE_LLM=false` and never publishes its answer without exact final-text and context validation.

## Target runtime

Each conversation is a persistent session with independent input, reasoning, tool, and output tasks. The state machine coordinates listening, transcript revision, response generation, speaking, barge-in, cancellation, and conversation-history repair. Stable ASR text is surfaced incrementally; uncertain hypotheses remain provisional. TTS starts from complete, speakable clauses and keeps enough context for natural prosody. Tool work runs asynchronously so it cannot block audio ingress or playback.

The target pipeline separates three concerns:

1. **Media plane:** LiveKit/WebRTC transports microphone and generated audio and supports remote clients and concurrent sessions.
2. **Conversation runtime:** a session coordinator manages turn state, interruptions, context, cancellation, queues, and tool execution.
3. **Provider plane:** replaceable STT, text-LLM, and TTS adapters. Arvan, local Persian ASR, and MOSS remain selectable independently.

## Migration sequence

1. **Measure each stage.** Record capture-to-preview, endpoint wait, final STT, first LLM text, Piper first PCM and client playback milestones. Do not store audio or transcript in telemetry. See `VOICE_BASELINE.md` and `VOICE_BENCHMARK.md` for measured and unmeasured values.
2. **Replace repeated full-prefix preview decoding.** Integrate and benchmark an existing streaming Whisper wrapper against the current Persian model and two-core CPU. Keep final recognition as the correction path. LiveKit alone cannot make a non-streaming Whisper model stream.
3. **Extract the session coordinator.** The current WebSocket path uses an explicit per-session state object, bounded event bus, one bounded ASR lane, serialized outbound events, versioned transcript state, and explicit Piper cancellation identities. The event bus is in-process; multi-worker deployment requires sticky sessions or shared coordination.
4. **Make response work concurrent.** Stream the LLM, synthesize complete clauses as they become safe to speak, and run tools on independent tasks. On barge-in, stop playback immediately and reconcile the model context with audio the user actually heard.
5. **Evaluate alternate media transport only if needed.** Keep the current WebSocket path until measurements show a concrete need for WebRTC media rooms or distributed session routing. Transport does not make a non-streaming Whisper decoder natively incremental.
6. **Scale and harden.** Add bounded session limits, lifecycle cleanup, health/readiness, structured traces, deployment configuration, and load/cancellation checks before production traffic.

## Quality gates

- No partial hypothesis is silently committed as the user's final utterance.
- A new speaker turn can interrupt generated audio without waiting for the old LLM/TTS request to finish.
- ASR, LLM, and TTS can be changed independently.
- Every turn exposes stage timings and a correlation ID without logging user audio, transcript, provider token or reasoning text.
- Latency targets are set from measured Persian speech on target hardware; they are not guessed from packet size or VAD thresholds.
- Naturalness is reviewed separately from latency. Tiny text chunks, repeated TTS prompts, or hard-coded gaps must not be introduced just to lower first-audio time.
