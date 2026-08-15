import { play, soundEnabled, setSoundEnabled } from './sound';

/**
 * Records mono 16-bit PCM WAV at 16 kHz.
 *
 * MediaRecorder would be less code, but Chrome records `audio/webm;codecs=opus`
 * and iOS Safari cannot play it. Transcoding server-side needs ffmpeg, which
 * shared hosting does not have. WAV plays everywhere, and at 16 kHz mono a
 * minute of speech is under 2 MB — well inside the 25 MB upload cap.
 */
class WavRecorder {
    constructor(sampleRate = 16000) {
        this.targetRate = sampleRate;
        this.chunks = [];
        this.peaks = [];
    }

    async start() {
        this.stream = await navigator.mediaDevices.getUserMedia({ audio: true });
        this.context = new (window.AudioContext || window.webkitAudioContext)();
        this.source = this.context.createMediaStreamSource(this.stream);

        // ScriptProcessorNode is deprecated but is the only path that works on
        // every browser this app has to run on, iOS Safari included.
        this.processor = this.context.createScriptProcessor(4096, 1, 1);

        this.processor.onaudioprocess = (event) => {
            const input = event.inputBuffer.getChannelData(0);
            this.chunks.push(new Float32Array(input));

            let peak = 0;
            for (let i = 0; i < input.length; i++) peak = Math.max(peak, Math.abs(input[i]));
            this.peaks.push(Math.round(Math.min(1, peak) * 100));
        };

        this.source.connect(this.processor);
        this.processor.connect(this.context.destination);
    }

    stop() {
        this.processor?.disconnect();
        this.source?.disconnect();
        this.stream?.getTracks().forEach((track) => track.stop());

        const rate = this.context?.sampleRate ?? this.targetRate;
        const samples = this.downsample(this.flatten(), rate, this.targetRate);

        this.context?.close();

        return {
            blob: this.encode(samples, this.targetRate),
            duration: Math.round(samples.length / this.targetRate),
            waveform: this.normalisePeaks(),
        };
    }

    cancel() {
        this.processor?.disconnect();
        this.source?.disconnect();
        this.stream?.getTracks().forEach((track) => track.stop());
        this.context?.close();
    }

    flatten() {
        const length = this.chunks.reduce((total, chunk) => total + chunk.length, 0);
        const out = new Float32Array(length);
        let offset = 0;

        for (const chunk of this.chunks) {
            out.set(chunk, offset);
            offset += chunk.length;
        }

        return out;
    }

    downsample(buffer, from, to) {
        if (to >= from) return buffer;

        const ratio = from / to;
        const out = new Float32Array(Math.round(buffer.length / ratio));

        for (let i = 0; i < out.length; i++) {
            // Average the source window rather than picking one sample, which
            // would alias badly on sibilants.
            const start = Math.round(i * ratio);
            const end = Math.min(Math.round((i + 1) * ratio), buffer.length);
            let sum = 0;

            for (let j = start; j < end; j++) sum += buffer[j];
            out[i] = sum / Math.max(1, end - start);
        }

        return out;
    }

    encode(samples, rate) {
        const buffer = new ArrayBuffer(44 + samples.length * 2);
        const view = new DataView(buffer);

        const writeString = (offset, text) => {
            for (let i = 0; i < text.length; i++) view.setUint8(offset + i, text.charCodeAt(i));
        };

        writeString(0, 'RIFF');
        view.setUint32(4, 36 + samples.length * 2, true);
        writeString(8, 'WAVE');
        writeString(12, 'fmt ');
        view.setUint32(16, 16, true);
        view.setUint16(20, 1, true);
        view.setUint16(22, 1, true);
        view.setUint32(24, rate, true);
        view.setUint32(28, rate * 2, true);
        view.setUint16(32, 2, true);
        view.setUint16(34, 16, true);
        writeString(36, 'data');
        view.setUint32(40, samples.length * 2, true);

        let offset = 44;
        for (let i = 0; i < samples.length; i++) {
            const clamped = Math.max(-1, Math.min(1, samples[i]));
            view.setInt16(offset, clamped < 0 ? clamped * 0x8000 : clamped * 0x7fff, true);
            offset += 2;
        }

        return new Blob([view], { type: 'audio/wav' });
    }

    normalisePeaks() {
        if (!this.peaks.length) return [];

        const bars = 40;
        const step = Math.max(1, Math.floor(this.peaks.length / bars));
        const out = [];

        for (let i = 0; i < this.peaks.length; i += step) {
            const slice = this.peaks.slice(i, i + step);
            out.push(Math.max(6, Math.round(slice.reduce((a, b) => a + b, 0) / slice.length)));
        }

        return out.slice(0, bars);
    }
}

document.addEventListener('alpine:init', () => {
    const Alpine = window.Alpine;

    Alpine.data('chatThread', () => ({
        connected: true,
        soundOn: soundEnabled(),
        typing: [],
        recording: false,
        recorder: null,
        recordedSeconds: 0,
        recordingTimer: null,
        typingTimer: null,

        init() {
            this.scrollToEnd(false);

            window.addEventListener('echo-state', (e) => {
                this.connected = e.detail.connected;
            });

            // A new message that is not mine should land at the bottom, but
            // only if the reader was already there — never yank the view away
            // from someone reading history.
            Livewire.hook('morph.updated', ({ component }) => {
                if (component.name?.endsWith('tasks.chat')) this.scrollToEnd(false);
            });
        },

        get typingLabel() {
            if (!this.typing.length) return '';

            return this.typing.length === 1
                ? `${this.typing[0]} is typing`
                : `${this.typing.length} people are typing`;
        },

        get recordingLabel() {
            const s = this.recordedSeconds;

            return `${Math.floor(s / 60)}:${String(s % 60).padStart(2, '0')}`;
        },

        scrollToEnd(force) {
            const el = this.$refs.scroller;
            if (!el) return;

            const nearBottom = el.scrollHeight - el.scrollTop - el.clientHeight < 160;

            if (force || nearBottom) {
                this.$nextTick(() => {
                    el.scrollTop = el.scrollHeight;
                });
            }
        },

        autoGrow(el) {
            el.style.height = 'auto';
            el.style.height = `${Math.min(el.scrollHeight, 128)}px`;
        },

        notifyTyping() {
            clearTimeout(this.typingTimer);
            this.typingTimer = setTimeout(() => {}, 1200);
        },

        toggleSound() {
            this.soundOn = !this.soundOn;
            setSoundEnabled(this.soundOn);

            if (this.soundOn) play('message');
        },

        async startRecording() {
            try {
                this.recorder = new WavRecorder();
                await this.recorder.start();

                this.recording = true;
                this.recordedSeconds = 0;
                this.recordingTimer = setInterval(() => this.recordedSeconds++, 1000);
            } catch {
                this.recording = false;
                Alpine.store('toasts').push({
                    message: 'Microphone access was refused.',
                    tone: 'danger',
                });
            }
        },

        async stopRecording() {
            if (!this.recorder) return;

            clearInterval(this.recordingTimer);

            const { blob, duration, waveform } = this.recorder.stop();

            this.recording = false;
            this.recorder = null;

            if (duration < 1) return;

            const file = new File([blob], 'voice-note.wav', { type: 'audio/wav' });

            this.$wire.set('voiceDuration', duration);
            this.$wire.set('voiceWaveform', waveform);
            await this.$wire.upload('voice', file, () => this.$wire.send());
        },

        cancelRecording() {
            clearInterval(this.recordingTimer);
            this.recorder?.cancel();
            this.recorder = null;
            this.recording = false;
        },
    }));

    Alpine.data('voiceNote', (url, waveform) => ({
        playing: false,
        progress: 0,
        speed: 1,
        audio: null,
        bars: waveform?.length ? waveform : Array.from({ length: 40 }, () => 30),

        toggle() {
            if (!url) return;

            this.audio = this.audio ?? this.createAudio();

            if (this.playing) {
                this.audio.pause();
                this.playing = false;
                return;
            }

            this.audio.playbackRate = this.speed;
            this.audio.play();
            this.playing = true;
        },

        createAudio() {
            const audio = new Audio(url);

            audio.addEventListener('timeupdate', () => {
                this.progress = audio.duration ? audio.currentTime / audio.duration : 0;
            });

            audio.addEventListener('ended', () => {
                this.playing = false;
                this.progress = 0;
            });

            return audio;
        },

        cycleSpeed() {
            this.speed = { 1: 1.5, 1.5: 2, 2: 1 }[this.speed] ?? 1;

            if (this.audio) this.audio.playbackRate = this.speed;
        },
    }));
});
