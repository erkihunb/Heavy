/**
 * Icons of the admin shell (sidebar, header, toolbar): the Solar icon set, linear weight,
 * re-exported under the names the shell already uses so pages and tests keep their imports.
 * Solar icons are by 480 Design (CC BY 4.0), shipped through @solar-icons/react (MIT).
 * Add an icon here, never from the package directly, so the shell stays on one weight.
 *
 * Only the icons the app uses are listed: every signed-in page loads this module.
 * Each wrapper is marked pure so the bundler can still drop one that falls out of use.
 */
import { AltArrowDownIcon as SolarAltArrowDownIcon } from '@solar-icons/react/linear/alt-arrow-down';
import { AltArrowRightIcon as SolarAltArrowRightIcon } from '@solar-icons/react/linear/alt-arrow-right';
import { Banknote2Icon as SolarBanknote2Icon } from '@solar-icons/react/linear/banknote-2';
import { BellIcon as SolarBellIcon } from '@solar-icons/react/linear/bell';
import { BillListIcon as SolarBillListIcon } from '@solar-icons/react/linear/bill-list';
import { Buildings3Icon as SolarBuildings3Icon } from '@solar-icons/react/linear/buildings-3';
import { ChartIcon as SolarChartIcon } from '@solar-icons/react/linear/chart';
import { ClipboardListIcon as SolarClipboardListIcon } from '@solar-icons/react/linear/clipboard-list';
import { CloseCircleIcon as SolarCloseCircleIcon } from '@solar-icons/react/linear/close-circle';
import { DocumentsIcon as SolarDocumentsIcon } from '@solar-icons/react/linear/documents';
import { HamburgerMenuIcon as SolarHamburgerMenuIcon } from '@solar-icons/react/linear/hamburger-menu';
import { HistoryIcon as SolarHistoryIcon } from '@solar-icons/react/linear/history';
import { KeyMinimalisticIcon as SolarKeyMinimalisticIcon } from '@solar-icons/react/linear/key-minimalistic';
import { LockKeyholeMinimalisticIcon as SolarLockKeyholeMinimalisticIcon } from '@solar-icons/react/linear/lock-keyhole-minimalistic';
import { Logout2Icon as SolarLogout2Icon } from '@solar-icons/react/linear/logout-2';
import { MagnifierIcon as SolarMagnifierIcon } from '@solar-icons/react/linear/magnifier';
import { QrCodeIcon as SolarQrCodeIcon } from '@solar-icons/react/linear/qr-code';
import { SettingsIcon as SolarSettingsIcon } from '@solar-icons/react/linear/settings';
import { ShieldUserIcon as SolarShieldUserIcon } from '@solar-icons/react/linear/shield-user';
import { SidebarMinimalisticIcon as SolarSidebarMinimalisticIcon } from '@solar-icons/react/linear/sidebar-minimalistic';
import { TagIcon as SolarTagIcon } from '@solar-icons/react/linear/tag';
import { Tuning2Icon as SolarTuning2Icon } from '@solar-icons/react/linear/tuning-2';
import { UserPlusRoundedIcon as SolarUserPlusRoundedIcon } from '@solar-icons/react/linear/user-plus-rounded';
import { UsersGroupRoundedIcon as SolarUsersGroupRoundedIcon } from '@solar-icons/react/linear/users-group-rounded';
import { WalletMoneyIcon as SolarWalletMoneyIcon } from '@solar-icons/react/linear/wallet-money';
import { Widget5Icon as SolarWidget5Icon } from '@solar-icons/react/linear/widget-5';
import type { JSX, SVGProps } from 'react';

export type NavIcon = (
    props: Omit<SVGProps<SVGSVGElement>, 'ref'>,
) => JSX.Element;

/** Solar icons render 24px by default; the shell sizes them with classes, like the hand-drawn set they replace. */
function adapt(Icon: typeof SolarWidget5Icon): NavIcon {
    return function SolarIcon(props) {
        return <Icon strokeWidth={1.5} {...props} />;
    };
}

export const LayoutDashboard = /* @__PURE__ */ adapt(SolarWidget5Icon);
export const Users = /* @__PURE__ */ adapt(SolarUsersGroupRoundedIcon);
export const X = /* @__PURE__ */ adapt(SolarCloseCircleIcon);
export const SettingsIcon = /* @__PURE__ */ adapt(SolarSettingsIcon);
export const TagsIcon = /* @__PURE__ */ adapt(SolarTagIcon);
export const ClipboardListIcon = /* @__PURE__ */ adapt(SolarClipboardListIcon);
export const KeyIcon = /* @__PURE__ */ adapt(SolarKeyMinimalisticIcon);
export const QrCodeIcon = /* @__PURE__ */ adapt(SolarQrCodeIcon);
export const SearchIcon = /* @__PURE__ */ adapt(SolarMagnifierIcon);
export const ChevronRight = /* @__PURE__ */ adapt(SolarAltArrowRightIcon);
export const ChevronDown = /* @__PURE__ */ adapt(SolarAltArrowDownIcon);
export const HistoryIcon = /* @__PURE__ */ adapt(SolarHistoryIcon);
export const MenuIcon = /* @__PURE__ */ adapt(SolarHamburgerMenuIcon);
export const LogOut = /* @__PURE__ */ adapt(SolarLogout2Icon);
export const BellIcon = /* @__PURE__ */ adapt(SolarBellIcon);
export const SidebarIcon = /* @__PURE__ */ adapt(SolarSidebarMinimalisticIcon);

/* Sidebar menus and submenus: one descriptive icon per page (AppSidebar.tsx). */
export const TuningIcon = /* @__PURE__ */ adapt(SolarTuning2Icon);
export const ChartIcon = /* @__PURE__ */ adapt(SolarChartIcon);
export const DocumentsIcon = /* @__PURE__ */ adapt(SolarDocumentsIcon);
export const UserPlusIcon = /* @__PURE__ */ adapt(SolarUserPlusRoundedIcon);
export const WalletIcon = /* @__PURE__ */ adapt(SolarWalletMoneyIcon);
export const ShieldUserIcon = /* @__PURE__ */ adapt(SolarShieldUserIcon);
export const LockIcon = /* @__PURE__ */ adapt(SolarLockKeyholeMinimalisticIcon);

/*
 * Heavy additions: icons its menu (config/navigation.ts) uses that the source
 * set does not name. Same weight and stroke through adapt().
 */
export const Buildings3Icon = /* @__PURE__ */ adapt(SolarBuildings3Icon);
export const BanknoteIcon = /* @__PURE__ */ adapt(SolarBanknote2Icon);
export const BillListIcon = /* @__PURE__ */ adapt(SolarBillListIcon);
