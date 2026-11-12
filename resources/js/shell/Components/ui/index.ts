/*
 * The page-level UI of the admin shell, in one import:
 *
 *   import { AppDataTable, AppFilterBar, FilterField, Input, ListPagination } from '@/shell/Components/ui';
 *
 * The App* parts are the app wrappers (Inertia links, translations);
 * the rest comes straight from the kit (packages/app-ui, `@app/ui`).
 */
export {
    default as AppSkeleton,
    AppTableSkeleton,
    AppTableRowSkeleton,
    AppMetricCardSkeleton,
    AppPageHeaderSkeleton,
} from './AppSkeleton';
export { default as AppFilterBar, filterInputCls } from './AppFilterBar';
export { default as AppMetricCard } from './AppMetricCard';
export { default as AppTabNav } from './AppTabNav';
export type { AppTabNavItem } from './AppTabNav';
export { default as AppActionMenu } from './AppActionMenu';
export type { AppActionItem } from './AppActionMenu';
export {
    default as AppDataTable,
    AppDataTablePagination,
    createColumnHelper,
} from './AppDataTable';
export type { AppDataTableMeta, ColumnDef } from './AppDataTable';
export {
    default as ListPagination,
    hasFilterQuery,
    laravelPageMeta,
} from './ListPagination';
export type { LaravelPageMeta } from './ListPagination';
export { default as SearchableSelect } from './SearchableSelect';
export { default as AppToaster } from './AppToaster';
export { default as AppCommandPalette } from './AppCommandPalette';
export { default as AppPageLoader } from './AppPageLoader';
export { default as LocalizedUiProvider } from './LocalizedUiProvider';
export {
    Alert,
    Button,
    Card,
    CardDescription,
    CardFooter,
    CardHeader,
    CardTitle,
    ConfirmDialog,
    AppDialog,
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
    Tabs,
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
    // The one table style (packages/app-ui/src/table.tsx).
    Table,
    TableCard,
    // Sorting, columns menu, density and kept header for any table (packages/app-ui/src/tableTools.tsx).
    TableTools,
    TBody,
    Td,
    Th,
    THead,
    Tr,
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
