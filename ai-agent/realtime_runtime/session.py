from __future__ import annotations

import re
import secrets
from collections import deque
from dataclasses import dataclass, field

from realtime_runtime.events import SessionEventBus


def _words(text: str) -> list[str]:
    return re.findall(r"\S+", " ".join(str(text or "").split()))


def _common_prefix(left: list[str], right: list[str]) -> list[str]:
    common: list[str] = []
    for old, new in zip(left, right):
        if old != new:
            break
        common.append(old)
    return common


@dataclass(slots=True)
class TranscriptRevision:
    revision: int
    text: str
    stable_text: str
    stable_changed: bool
    stable_invalidated: bool


@dataclass(slots=True)
class TranscriptState:
    """Versioned ASR hypotheses with a retractable, conservative stable prefix."""

    revision: int = 0
    current_text: str = ""
    stable_text: str = ""
    snapshots: deque[list[str]] = field(default_factory=lambda: deque(maxlen=2))
    repeated_snapshot_count: int = 0
    first_partial_emitted: bool = False

    def observe(self, candidate: str) -> TranscriptRevision | None:
        text = " ".join(str(candidate or "").split())
        if not text:
            return None

        words = _words(text)
        if text == self.current_text:
            self.repeated_snapshot_count += 1
            stable_words = words if self.repeated_snapshot_count >= 1 else []
        else:
            previous_text = self.current_text
            self.current_text = text
            self.revision += 1
            self.repeated_snapshot_count = 0
            self.snapshots.append(words)
            if len(self.snapshots) == 2:
                stable_words = _common_prefix(self.snapshots[0], self.snapshots[1])
            else:
                stable_words = []

            # If a later Whisper decode changed already-stable words, retract
            # the speculative prefix. It is a hint, never a committed transcript.
            if self.stable_text and not text.startswith(self.stable_text):
                stable_words = _common_prefix(_words(self.stable_text), words)

        stable = " ".join(stable_words)
        stable_changed = stable != self.stable_text
        stable_invalidated = bool(self.stable_text and not stable.startswith(self.stable_text))
        self.stable_text = stable
        return TranscriptRevision(
            revision=self.revision,
            text=text,
            stable_text=stable,
            stable_changed=stable_changed,
            stable_invalidated=stable_invalidated,
        )

    def commit(self, final_text: str) -> int:
        self.revision += 1
        self.current_text = " ".join(str(final_text or "").split())
        self.stable_text = self.current_text
        self.snapshots.clear()
        self.repeated_snapshot_count = 0
        return self.revision


@dataclass(slots=True)
class CaptureState:
    active_utterance_id: int | None = None
    received_audio_frames: int = 0


@dataclass(slots=True)
class ASRState:
    transcripts: dict[int, TranscriptState] = field(default_factory=dict)
    max_retained_transcripts: int = 32

    def retain_latest(self, latest_turn_id: int) -> None:
        while len(self.transcripts) > self.max_retained_transcripts:
            oldest_turn_id = next(iter(self.transcripts))
            if oldest_turn_id == latest_turn_id:
                break
            self.transcripts.pop(oldest_turn_id, None)


@dataclass(slots=True)
class LLMState:
    active_task_id: str | None = None


@dataclass(slots=True)
class TTSState:
    active_task_id: str | None = None


@dataclass(slots=True)
class PlaybackState:
    active_turn_id: str | None = None


@dataclass(slots=True)
class ToolExecutionState:
    active_task_ids: set[str] = field(default_factory=set)


@dataclass(slots=True)
class ConversationSession:
    """Per-WebSocket state; provider tasks remain owned by their respective runtimes."""

    session_id: str = field(default_factory=lambda: secrets.token_hex(8))
    events: SessionEventBus = field(default_factory=SessionEventBus)
    capture: CaptureState = field(default_factory=CaptureState)
    asr: ASRState = field(default_factory=ASRState)
    llm: LLMState = field(default_factory=LLMState)
    tts: TTSState = field(default_factory=TTSState)
    playback: PlaybackState = field(default_factory=PlaybackState)
    tools: ToolExecutionState = field(default_factory=ToolExecutionState)
    active_user_turn_id: int | None = None
    active_agent_turn_id: str | None = None
    next_utterance_id: int = 0
    closed: bool = False

    def begin_utterance(self) -> int:
        self.next_utterance_id += 1
        self.active_user_turn_id = self.next_utterance_id
        self.capture.active_utterance_id = self.next_utterance_id
        self.asr.transcripts[self.next_utterance_id] = TranscriptState()
        self.asr.retain_latest(self.next_utterance_id)
        return self.next_utterance_id

    def transcript(self, utterance_id: int) -> TranscriptState:
        return self.asr.transcripts.setdefault(utterance_id, TranscriptState())

    def finish_utterance(self, utterance_id: int, final_text: str) -> int:
        tracker = self.transcript(utterance_id)
        revision = tracker.commit(final_text)
        if self.capture.active_utterance_id == utterance_id:
            self.capture.active_utterance_id = None
        if self.active_user_turn_id == utterance_id:
            self.active_user_turn_id = None
        return revision

    async def close(self) -> None:
        self.closed = True
        await self.events.close(discard_pending=True)
        self.asr.transcripts.clear()
        self.capture.active_utterance_id = None
        self.active_user_turn_id = None
        self.active_agent_turn_id = None
        self.llm.active_task_id = None
        self.tts.active_task_id = None
        self.playback.active_turn_id = None
        self.tools.active_task_ids.clear()
