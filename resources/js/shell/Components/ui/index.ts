/*
 * The page-level UI of the admin shell, in one import:
 *
 *   import { AppActionMenu, ListPagination, TableCard } from '@/shell/Components/ui';
 *
 * The App* parts are the app wrappers (Inertia links, translations);
 * the rest comes straight from the kit (packages/app-ui, `@app/ui`).
 */
export { default as AppActionMenu } from './AppActionMenu';
export type { AppActionItem } from './AppActionMenu';
export { default as ListPagination } from './ListPagination';
export type { LaravelPageMeta } from './ListPagination';
export { default as AppToaster } from './AppToaster';
export { default as AppCommandPalette } from './AppCommandPalette';
export { default as LocalizedUiProvider } from './LocalizedUiProvider';
export {
    Alert,
    Button,
    Card,
    CardDescription,
    CardFooter,
    CardHeader,
    CardTitle,
    EmptyState,
    ErrorState,
    FieldError,
    FileUpload,
    FormDescription,
    FormField,
    FormLabel,
    Input,
    SearchInput,
    Select,
    Skeleton,
    StatusBadge,
    Textarea,
    Tooltip,
    UiProvider,
    actionLinkClassName,
    badgeClassName,
    buttonClassName,
    controlClassName,
    inputClassName,
    selectClassName,
    textareaClassName,
    cx,
    TableCard,
    // Sorting, columns menu, density and kept header for any table (packages/app-ui/src/tableTools.tsx).
    TableTools,
    tableBodyClassName,
    tableCellClampClassName,
    tableCellClassName,
    tableClassName,
    tableContainerClassName,
    tableHeadClassName,
    tableHeaderCellClassName,
    tableRowClassName,
    // The one filter panel and pagination bar (packages/app-ui/src/data.tsx).
    FilterField,
    FilterPanel,
    Pagination,
    filterCaptionClassName,
    filterFieldClassName,
    filterPanelClassName,
    paginationItemClassName,
} from '@app/ui';
export type {
    ActionTone,
    ButtonProps,
    ButtonSize,
    ButtonVariant,
    Tone,
    UiMessages,
} from '@app/ui';
