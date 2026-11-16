class LivePcmCaptureProcessor extends AudioWorkletProcessor {
  constructor() {
    super();
    this.targetSampleRate = 16000;
    this.ratio = sampleRate / this.targetSampleRate;
    this.carry = new Float32Array(0);
    this.position = 0;
    this.packet = new Int16Array(320);
    this.packetLength = 0;
  }

  process(inputs, outputs) {
    for (const output of outputs) {
      for (const channel of output) channel.fill(0);
    }

    const input = inputs[0]?.[0];
    if (!input?.length) return true;

    const data = new Float32Array(this.carry.length + input.length);
    data.set(this.carry);
    data.set(input, this.carry.length);

    let cursor = this.position;
    while (cursor + this.ratio <= data.length) {
      const end = cursor + this.ratio;
      let weightedSum = 0;
      let totalWeight = 0;
      for (let source = Math.floor(cursor); source < Math.ceil(end); source += 1) {
        const weight = Math.max(0, Math.min(end, source + 1) - Math.max(cursor, source));
        weightedSum += data[source] * weight;
        totalWeight += weight;
      }

      const sample = totalWeight ? weightedSum / totalWeight : 0;
      this.packet[this.packetLength] = Math.round(Math.max(-1, Math.min(1, sample)) * 32767);
      this.packetLength += 1;
      cursor = end;

      if (this.packetLength === this.packet.length) {
        const completedPacket = this.packet;
        this.packet = new Int16Array(320);
        this.packetLength = 0;
        this.port.postMessage(completedPacket.buffer, [completedPacket.buffer]);
      }
    }

    const consumed = Math.floor(cursor);
    this.position = cursor - consumed;
    this.carry = data.slice(consumed);
    return true;
  }
}

registerProcessor('live-pcm-capture', LivePcmCaptureProcessor);
