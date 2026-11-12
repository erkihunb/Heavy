import jsQR from 'jsqr';
import { permitToken } from '@/lib/permit-qr';

/**
 * The camera scanner on the public "Check a permit" page. The page itself is
 * server-rendered and works without JavaScript (the number-and-plate form);
 * this only reveals the "Scan QR code" button and, once a permit's QR code is
 * read, opens its result. Frames are decoded on the device with jsQR: the
 * picture never leaves the phone.
 */
function init(root: HTMLElement): void {
    const start = root.querySelector<HTMLButtonElement>('[data-scan-start]');
    const panel = root.querySelector<HTMLElement>('[data-scan-panel]');
    const video = root.querySelector<HTMLVideoElement>('video');
    const frame = root.querySelector<HTMLElement>('[data-scan-frame]');
    const status = root.querySelector<HTMLElement>('[data-scan-status]');
    const problem = root.querySelector<HTMLElement>('[data-scan-problem]');
    const close = root.querySelector<HTMLButtonElement>('[data-scan-close]');

    if (!start || !panel || !video || !frame || !status || !problem || !close) {
        return;
    }

    const text = (key: string): string => root.dataset[key] ?? '';
    const verifyBase = root.dataset.verifyBase ?? '';

    let stream: MediaStream | null = null;
    let raf = 0;
    let stopped = true;
    let notPermitShownAt = 0;
    const canvas = document.createElement('canvas');
    const context = canvas.getContext('2d', { willReadFrequently: true });

    const setStatus = (message: string): void => {
        status.textContent = message;
    };

    const showProblem = (message: string): void => {
        frame.hidden = true;
        problem.hidden = false;
        problem.textContent = message;
        setStatus('');
    };

    const stop = (): void => {
        stopped = true;
        cancelAnimationFrame(raf);
        stream?.getTracks().forEach((track) => track.stop());
        stream = null;
        video.srcObject = null;
    };

    const tick = (): void => {
        if (stopped || !context) {
            return;
        }

        if (video.readyState >= video.HAVE_ENOUGH_DATA) {
            // A smaller picture decodes faster and a QR code survives it.
            const scale = Math.min(1, 720 / video.videoWidth);
            canvas.width = Math.round(video.videoWidth * scale);
            canvas.height = Math.round(video.videoHeight * scale);
            context.drawImage(video, 0, 0, canvas.width, canvas.height);
            const image = context.getImageData(
                0,
                0,
                canvas.width,
                canvas.height,
            );
            const code = jsQR(image.data, image.width, image.height, {
                inversionAttempts: 'dontInvert',
            });

            if (code?.data) {
                const token = permitToken(
                    code.data,
                    verifyBase,
                    window.location.origin,
                );

                if (token) {
                    stop();
                    setStatus(text('msgOpening'));
                    window.location.assign(`/v/${encodeURIComponent(token)}`);

                    return;
                }

                // Another QR code: say so, at most every few seconds, and keep looking.
                if (Date.now() - notPermitShownAt > 3000) {
                    notPermitShownAt = Date.now();
                    setStatus(text('msgNotPermit'));
                }
            }
        }

        raf = requestAnimationFrame(tick);
    };

    const open = (): void => {
        panel.hidden = false;
        start.hidden = true;
        problem.hidden = true;
        frame.hidden = true;
        setStatus(text('msgStarting'));
        panel.scrollIntoView({ behavior: 'smooth', block: 'start' });

        // Cameras need a secure page (HTTPS, or this computer).
        if (!window.isSecureContext || !navigator.mediaDevices?.getUserMedia) {
            showProblem(text('msgUnavailable'));

            return;
        }

        stopped = false;

        navigator.mediaDevices
            .getUserMedia({
                video: { facingMode: { ideal: 'environment' } },
                audio: false,
            })
            .then((media) => {
                if (stopped) {
                    media.getTracks().forEach((track) => track.stop());

                    return;
                }

                stream = media;
                video.srcObject = media;
                void video.play();
                frame.hidden = false;
                setStatus(text('msgHint'));
                raf = requestAnimationFrame(tick);
            })
            .catch((error: unknown) => {
                stopped = true;
                showProblem(
                    error instanceof DOMException &&
                        error.name === 'NotAllowedError'
                        ? text('msgDenied')
                        : text('msgUnavailable'),
                );
            });
    };

    start.addEventListener('click', open);
    close.addEventListener('click', () => {
        stop();
        panel.hidden = true;
        start.hidden = false;
        start.focus();
    });
    window.addEventListener('pagehide', stop);

    // Without JavaScript the button stays hidden and the form is the way in.
    start.hidden = false;
}

document.querySelectorAll<HTMLElement>('[data-qr-scanner]').forEach(init);
