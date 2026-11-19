/**
 * The browser tab's title: the page's title, then the system's name.
 *
 * The name comes from the server (the `name` shared prop: APP_NAME, or the
 * name set in System settings), not from the build: an image built once
 * shows whatever name the installation it runs in has.
 */
export function appTitle(title: string, name: string): string {
    if (name === '') {
        return title;
    }

    return title ? `${title} - ${name}` : name;
}

/** The `name` shared prop of an Inertia page, when it has one. */
export function appNameOf(props: unknown): string | null {
    const name = (props as { name?: unknown } | null)?.name;

    return typeof name === 'string' && name.trim() !== '' ? name : null;
}
