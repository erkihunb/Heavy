const TOKEN = /^\/v\/([A-Za-z0-9_-]{1,64})\/?$/;

/**
 * The verification token in a scanned permit QR code. The code holds
 * `<verify_url>/v/<token>`; anything from another site, or any other path,
 * is not a permit of this system and gives null.
 *
 * @param verifyBase  config('permits.verify_url'), as printed in QR codes
 * @param currentOrigin  this site, also accepted (a test or local install)
 */
export function permitToken(
    text: string,
    verifyBase: string,
    currentOrigin: string,
): string | null {
    let url: URL;

    try {
        url = new URL(text.trim());
    } catch {
        return null;
    }

    if (url.protocol !== 'https:' && url.protocol !== 'http:') {
        return null;
    }

    const allowed = [currentOrigin];

    try {
        allowed.push(new URL(verifyBase).origin);
    } catch {
        // No verification address configured: this site only.
    }

    return allowed.includes(url.origin)
        ? (url.pathname.match(TOKEN)?.[1] ?? null)
        : null;
}
