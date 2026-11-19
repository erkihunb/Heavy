import { Link, router } from '@inertiajs/react';
import { Pencil, Trash2 } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { useTranslation } from '@/lib/i18n';
import { useConfirm } from '@/shell/hooks/useConfirm';

/**
 * A list row's edit and delete buttons, each shown only to whoever holds its
 * permission. Deleting asks first; a record still in use cannot be deleted
 * and says why. Needs a ConfirmProvider above it.
 */
export function RowActions({
    name,
    editHref,
    deleteUrl,
    deletable = true,
}: {
    /** What the row is, for the confirmation ("Delete :name?"). */
    name: string;
    /** Null hides the edit button (no permission). */
    editHref: string | null;
    /** Null hides the delete button (no permission). */
    deleteUrl: string | null;
    /** False when the record is in use: the button is shown but disabled. */
    deletable?: boolean;
}) {
    const { t } = useTranslation();
    const { confirm } = useConfirm();

    const remove = async () => {
        if (deleteUrl === null) {
            return;
        }

        const { confirmed } = await confirm({
            title: t('common.delete.title', { name }),
            description: t('common.delete.body'),
            confirmLabel: t('common.delete.button'),
            variant: 'danger',
        });

        if (confirmed) {
            router.delete(deleteUrl, { preserveScroll: true });
        }
    };

    return (
        <span className="inline-flex flex-wrap justify-end gap-2">
            {editHref !== null && (
                <Button asChild variant="outline" size="sm">
                    <Link href={editHref}>
                        <Pencil aria-hidden="true" />
                        {t('permits.actions.edit')}
                    </Link>
                </Button>
            )}
            {deleteUrl !== null && (
                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    disabled={!deletable}
                    title={deletable ? undefined : t('common.delete.in_use')}
                    onClick={() => void remove()}
                    className="text-destructive hover:text-destructive"
                >
                    <Trash2 aria-hidden="true" />
                    {t('common.delete.button')}
                </Button>
            )}
        </span>
    );
}
