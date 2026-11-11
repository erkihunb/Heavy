import jsQR from 'jsqr';
import { CameraOff, ScanLine, X } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { Button } from '@/components/ui/button';
import { useTranslation } from '@/lib/i18n';

type Status = 'starting' | 'scanning' | 'denied' | 'unavailable';

/**
 * Reads a QR code with the device camera (the back camera on a phone).
 * Each video frame is drawn on a hidden canvas and decoded with jsQR, so it
 * works in every browser, with no network call; the picture never leaves
 * the device. `onResult` gets the text of the first code found and decides
 * whether it is a permit; returning false keeps the camera scanning.
 */
export function QrScanner({
    onResult,
    onClose,
}: {
    onResult: (text: string) => boolean;
    onClose: () => void;
}) {
    const { t } = useTranslation();
    const video = useRef<HTMLVideoElement>(null);
    const [status, setStatus] = useState<Status>('starting');
    const handler = useRef(onResult);

    useEffect(() => {
        handler.current = onResult;
    }, [onResult]);

    useEffect(() => {
        let stream: MediaStream | null = null;
        let frame = 0;
        let stopped = false;
        const canvas = document.createElement('canvas');
        const context = canvas.getContext('2d', { willReadFrequently: true });

        const tick = () => {
            const element = video.current;

            if (stopped || !element || !context) {
                return;
            }

            if (element.readyState >= element.HAVE_ENOUGH_DATA) {
                // A smaller picture decodes faster and a QR code survives it.
                const scale = Math.min(1, 720 / element.videoWidth);
                canvas.width = Math.round(element.videoWidth * scale);
                canvas.height = Math.round(element.videoHeight * scale);
                context.drawImage(element, 0, 0, canvas.width, canvas.height);
                const image = context.getImageData(
                    0,
                    0,
                    canvas.width,
                    canvas.height,
                );
                const code = jsQR(image.data, image.width, image.height, {
                    inversionAttempts: 'dontInvert',
                });

                if (code?.data && handler.current(code.data)) {
                    stopped = true;

                    return;
                }
            }

            frame = requestAnimationFrame(tick);
        };

        if (!navigator.mediaDevices?.getUserMedia) {
            queueMicrotask(() => setStatus('unavailable'));

            return;
        }

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

                if (video.current) {
                    video.current.srcObject = media;
                    void video.current.play();
                }

                setStatus('scanning');
                frame = requestAnimationFrame(tick);
            })
            .catch((error: unknown) => {
                setStatus(
                    error instanceof DOMException &&
                        error.name === 'NotAllowedError'
                        ? 'denied'
                        : 'unavailable',
                );
            });

        return () => {
            stopped = true;
            cancelAnimationFrame(frame);
            stream?.getTracks().forEach((track) => track.stop());
        };
    }, []);

    return (
        <div className="overflow-hidden rounded-card border border-border bg-card">
            <div className="relative aspect-square w-full bg-black sm:aspect-video">
                <video
                    ref={video}
                    muted
                    playsInline
                    aria-label={t('verification.officer.scanner.camera')}
                    className="size-full object-cover"
                />

                {status === 'scanning' && (
                    <div
                        aria-hidden="true"
                        className="pointer-events-none absolute inset-0 flex items-center justify-center"
                    >
                        <div className="size-3/5 max-h-72 max-w-72 rounded-card border-4 border-white/90 shadow-[0_0_0_9999px_rgba(0,0,0,0.35)]" />
                    </div>
                )}

                {(status === 'denied' || status === 'unavailable') && (
                    <div className="absolute inset-0 flex flex-col items-center justify-center gap-3 bg-card p-6 text-center">
                        <CameraOff
                            aria-hidden="true"
                            className="size-8 text-muted-foreground"
                        />
                        <p role="alert" className="text-sm font-medium">
                            {status === 'denied'
                                ? t('verification.officer.scanner.denied')
                                : t('verification.officer.scanner.unavailable')}
                        </p>
                    </div>
                )}
            </div>

            <div className="flex items-center justify-between gap-3 px-4 py-3">
                <p
                    aria-live="polite"
                    className="flex items-center gap-2 text-sm text-muted-foreground"
                >
                    {status === 'starting' && (
                        <>
                            <ScanLine
                                aria-hidden="true"
                                className="size-4 shrink-0"
                            />
                            {t('verification.officer.scanner.starting')}
                        </>
                    )}
                    {status === 'scanning' && (
                        <>
                            <ScanLine
                                aria-hidden="true"
                                className="size-4 shrink-0"
                            />
                            {t('verification.officer.scanner.hint')}
                        </>
                    )}
                </p>
                <Button
                    type="button"
                    variant="ghost"
                    size="sm"
                    onClick={onClose}
                >
                    <X aria-hidden="true" />
                    {t('verification.officer.scanner.close')}
                </Button>
            </div>
        </div>
    );
}
