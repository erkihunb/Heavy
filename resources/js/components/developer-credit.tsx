/**
 * The credit line at the foot of every page. The name is a proper name, so
 * it is shown as written in both languages and kept from page translators.
 */
export function DeveloperCredit({ className }: { className?: string }) {
    return (
        <p className={className} translate="no">
            Designed &amp; Developed by{' '}
            <span className="font-medium">Erkihun Bewket</span>
        </p>
    );
}
