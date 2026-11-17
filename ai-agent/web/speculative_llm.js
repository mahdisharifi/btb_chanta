(function attachSpeculativeLlm(root) {
  class SpeculativeLlmCoordinator {
    constructor({
      enabled = false,
      request,
      timeoutMs = 2500,
      maxRequestsPerTurn = 1,
      maxConcurrentRequests = 1,
      minimumRevisionIntervalMs = 750,
      minimumStableTextLength = 24,
      now = () => performance.now()
    } = {}) {
      this.enabled = Boolean(enabled);
      this.request = request;
      this.timeoutMs = timeoutMs;
      this.maxRequestsPerTurn = maxRequestsPerTurn;
      this.maxConcurrentRequests = maxConcurrentRequests;
      this.minimumRevisionIntervalMs = minimumRevisionIntervalMs;
      this.minimumStableTextLength = minimumStableTextLength;
      this.now = now;
      this.turns = new Map();
      this.activeRequests = 0;
    }

    beginTurn(turnId, contextKey) {
      this.cancelTurn(turnId);
      this.turns.set(String(turnId), {
        contextKey,
        requests: 0,
        lastStartedAt: -Infinity,
        latestText: '',
        candidate: null
      });
    }

    observePartial(turnId, text) {
      const turn = this.turns.get(String(turnId));
      if (!turn) return;
      turn.latestText = String(text || '').trim();
      if (turn.candidate && !turn.latestText.startsWith(turn.candidate.text)) {
        this.#invalidateCandidate(turn);
      }
    }

    offerStable({ turnId, revision, text, contextKey, context = null }) {
      if (!this.enabled || typeof this.request !== 'function') return false;
      const turn = this.turns.get(String(turnId));
      const stableText = String(text || '').trim();
      if (!turn || !stableText || stableText.length < this.minimumStableTextLength) return false;
      if (contextKey !== turn.contextKey || turn.requests >= this.maxRequestsPerTurn) return false;
      if (this.activeRequests >= this.maxConcurrentRequests) return false;
      if (this.now() - turn.lastStartedAt < this.minimumRevisionIntervalMs) return false;
      if (turn.latestText && !turn.latestText.startsWith(stableText)) return false;

      this.#invalidateCandidate(turn);
      const controller = new AbortController();
      const candidate = {
        text: stableText,
        revision,
        contextKey,
        controller,
        result: null,
        startedAt: this.now(),
        timeout: null
      };
      turn.candidate = candidate;
      turn.requests += 1;
      turn.lastStartedAt = candidate.startedAt;
      this.activeRequests += 1;

      candidate.timeout = setTimeout(() => controller.abort(), this.timeoutMs);
      Promise.resolve(this.request(stableText, { signal: controller.signal, revision, context, turnId: String(turnId) }))
        .then(answer => {
          if (!controller.signal.aborted && turn.candidate === candidate) {
            const content = String(answer || '').trim();
            if (content) candidate.result = { answer: content, elapsedMs: this.now() - candidate.startedAt };
          }
        })
        .catch(() => {})
        .finally(() => {
          clearTimeout(candidate.timeout);
          this.activeRequests = Math.max(0, this.activeRequests - 1);
        });
      return true;
    }

    invalidateStable(turnId) {
      const turn = this.turns.get(String(turnId));
      if (turn) this.#invalidateCandidate(turn);
    }

    consumeFinal({ turnId, text, contextKey }) {
      const key = String(turnId);
      const turn = this.turns.get(key);
      if (!turn) return null;
      const candidate = turn.candidate;
      const canReuse = Boolean(
        candidate
        && candidate.result
        && !candidate.controller.signal.aborted
        && candidate.text === String(text || '').trim()
        && candidate.contextKey === contextKey
        && turn.contextKey === contextKey
      );
      const result = canReuse ? candidate.result : null;
      this.cancelTurn(key);
      return result;
    }

    cancelTurn(turnId) {
      const key = String(turnId);
      const turn = this.turns.get(key);
      if (turn?.candidate) this.#invalidateCandidate(turn);
      this.turns.delete(key);
    }

    #invalidateCandidate(turn) {
      const candidate = turn.candidate;
      if (!candidate) return;
      clearTimeout(candidate.timeout);
      if (!candidate.controller.signal.aborted) candidate.controller.abort();
      turn.candidate = null;
    }
  }

  const api = { SpeculativeLlmCoordinator };
  if (typeof module !== 'undefined' && module.exports) module.exports = api;
  else root.SpeculativeLlm = api;
})(typeof window !== 'undefined' ? window : globalThis);
