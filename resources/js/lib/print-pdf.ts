/**
 * Opens the browser's print dialog for a PDF without leaving the page.
 *
 * The PDF is fetched (same site, with the signed-in session) and loaded into
 * a hidden frame from a blob: address, because the app's own responses may
 * not be framed (X-Frame-Options: DENY). The frame is removed a while after
 * printing; the print dialog keeps its own copy.
 *
 * Resolves once the dialog has been opened; rejects if the PDF could not be
 * fetched or printed, so the caller can offer the download instead.
 */
export async function printPdf(url: string): Promise<void> {
    const response = await fetch(url, {
        credentials: 'same-origin',
        // Tells the server this is a print, for the audit log.
        headers: { 'X-Permit-Print': '1' },
    });

    if (!response.ok) {
        throw new Error(`The PDF answered with HTTP ${response.status}.`);
    }

    const blob = await response.blob();
    const blobUrl = URL.createObjectURL(
        blob.type === 'application/pdf'
            ? blob
            : new Blob([blob], { type: 'application/pdf' }),
    );

    const frame = document.createElement('iframe');
    frame.setAttribute('aria-hidden', 'true');
    frame.tabIndex = -1;
    // Not display:none: some browsers will not print a frame that is not laid out.
    frame.style.cssText =
        'position:fixed;right:0;bottom:0;width:1px;height:1px;border:0;opacity:0;pointer-events:none;';

    const cleanUp = () => {
        frame.remove();
        URL.revokeObjectURL(blobUrl);
    };

    await new Promise<void>((resolve, reject) => {
        frame.onload = () => {
            // Give the browser's PDF viewer a moment to finish drawing the pages.
            window.setTimeout(() => {
                try {
                    frame.contentWindow?.focus();
                    frame.contentWindow?.print();
                    resolve();
                } catch (error) {
                    reject(error);
                }

                // Long enough for the dialog to have taken its copy.
                window.setTimeout(cleanUp, 60_000);
            }, 400);
        };
        frame.onerror = () => {
            cleanUp();
            reject(new Error('The PDF could not be loaded for printing.'));
        };
        frame.src = blobUrl;
        document.body.appendChild(frame);
    });
}
