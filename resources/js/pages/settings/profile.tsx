import { Transition } from '@headlessui/react';
import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { KeyRound, SlidersHorizontal, UserRound } from 'lucide-react';
import type { FormEvent } from 'react';
import ProfileController from '@/actions/App/Http/Controllers/Settings/ProfileController';
import { SectionCard } from '@/components/section-card';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import SettingsLayout from '@/layouts/settings/layout';
import { useTranslation } from '@/lib/i18n';
import { edit } from '@/routes/profile';
import { send } from '@/routes/verification';
import { FormField, Input } from '@/shell/Components/ui';
import {
    IdentityFields,
    PreferenceFields,
    UsernameField,
    fullName,
} from '@/shell/Components/Users/StaffFormSections';
import type {
    LocaleOption,
    StaffFormData,
} from '@/shell/Components/Users/StaffFormSections';
import {
    AvatarPicker,
    internationalPhone,
    localPhone,
} from '@/shell/Components/Users/StaffProfileFields';
import type { BreadcrumbItem } from '@/types';

type OwnProfile = {
    first_name: string | null;
    middle_name: string | null;
    last_name: string | null;
    name: string;
    email: string;
    username: string | null;
    phone: string | null;
    national_id: string | null;
    gender: string | null;
    locale: string;
    timezone: string | null;
    theme: string | null;
    notify_email: boolean;
    avatar: string | null;
};

type ProfileForm = StaffFormData & {
    _method: 'patch';
    email: string;
    avatar: File | null;
    remove_avatar: boolean;
};

/** The name in its parts; an account that only has the whole name gets it split. */
function nameParts(profile: OwnProfile): [string, string, string] {
    if (profile.first_name) {
        return [
            profile.first_name,
            profile.middle_name ?? '',
            profile.last_name ?? '',
        ];
    }

    const [first = '', middle = '', ...rest] = profile.name.trim().split(/\s+/);

    return [first, middle, rest.join(' ')];
}

/**
 * A person's own record: the same photo, identity, account and preference
 * fields the user-management form has. Where they work, their branch and
 * their roles are not here; those are set by whoever manages users.
 */
export default function Profile({
    mustVerifyEmail,
    status,
    profile,
    options,
}: {
    mustVerifyEmail: boolean;
    status?: string;
    profile: OwnProfile;
    options: {
        locales: LocaleOption[];
        timezones: string[];
        defaultTimezone: string;
        themes: string[];
    };
}) {
    const { auth } = usePage().props;
    const { t } = useTranslation();
    const [first, middle, last] = nameParts(profile);

    const form = useForm<ProfileForm>({
        _method: 'patch',
        first_name: first,
        middle_name: middle,
        last_name: last,
        email: profile.email,
        username: profile.username ?? '',
        national_id: profile.national_id ?? '',
        gender: profile.gender ?? '',
        phone: localPhone(profile.phone),
        // Kept empty and never sent: a person does not set their own employment.
        institution: '',
        department: '',
        job_title: '',
        employee_number: '',
        manager_id: '',
        employment_status: '',
        branch_id: '',
        locale: profile.locale,
        timezone: profile.timezone ?? options.defaultTimezone,
        theme: profile.theme ?? options.themes[0] ?? 'system',
        notify_email: profile.notify_email,
        avatar: null,
        remove_avatar: false,
    });
    const { data, errors } = form;
    const section = {
        data,
        setData: (key: keyof StaffFormData, value: string | boolean) =>
            form.setData(key, value as never),
        errors,
    };

    const breadcrumbs: BreadcrumbItem[] = [
        { title: t('settings.profile.title'), href: edit() },
    ];

    function submit(event: FormEvent) {
        event.preventDefault();
        form.transform((values) => ({
            _method: values._method,
            first_name: values.first_name,
            middle_name: values.middle_name,
            last_name: values.last_name,
            email: values.email,
            username: values.username,
            national_id: values.national_id,
            gender: values.gender,
            phone: internationalPhone(values.phone),
            locale: values.locale,
            timezone: values.timezone,
            theme: values.theme,
            notify_email: values.notify_email ? 1 : 0,
            avatar: values.avatar,
            remove_avatar: values.remove_avatar ? 1 : 0,
        }));
        // A photo goes as a multipart POST; Laravel reads the PATCH from _method.
        form.post(ProfileController.update.url(), {
            preserveScroll: true,
            forceFormData: true,
            onSuccess: () =>
                form.setDefaults({
                    ...form.data,
                    avatar: null,
                    remove_avatar: false,
                }),
        });
    }

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={t('settings.profile.title')} />

            <SettingsLayout wide>
                <form
                    onSubmit={submit}
                    noValidate
                    className="flex flex-col gap-6"
                >
                    <SectionCard
                        id="profile-title"
                        icon={UserRound}
                        title={t('settings.profile.heading')}
                        description={t('settings.profile.description')}
                    >
                        <div className="@container space-y-6">
                            <AvatarPicker
                                name={fullName(data) || profile.name}
                                file={data.avatar}
                                current={profile.avatar}
                                removed={data.remove_avatar}
                                error={errors.avatar}
                                onChange={(file) =>
                                    form.setData('avatar', file)
                                }
                                onRemove={(remove) =>
                                    form.setData('remove_avatar', remove)
                                }
                            />
                            <IdentityFields {...section} />
                        </div>
                    </SectionCard>

                    <SectionCard
                        id="profile-account"
                        icon={KeyRound}
                        title={t('rbac.users.accountSection')}
                    >
                        <div className="@container space-y-4">
                            <div className="grid gap-4 @md:grid-cols-2">
                                <FormField
                                    label={t('settings.profile.email')}
                                    required
                                    error={errors.email}
                                >
                                    {({ id, describedBy, invalid }) => (
                                        <Input
                                            id={id}
                                            type="email"
                                            inputMode="email"
                                            aria-describedby={describedBy}
                                            aria-invalid={invalid}
                                            autoComplete="username"
                                            value={data.email}
                                            onChange={(event) =>
                                                form.setData(
                                                    'email',
                                                    event.target.value,
                                                )
                                            }
                                        />
                                    )}
                                </FormField>
                                <UsernameField {...section} />
                            </div>

                            {mustVerifyEmail &&
                                auth.user.email_verified_at === null && (
                                    <div>
                                        <p className="text-sm text-muted-foreground">
                                            {t('settings.profile.unverified')}{' '}
                                            <Link
                                                href={send()}
                                                as="button"
                                                className="text-foreground underline underline-offset-4"
                                            >
                                                {t('settings.profile.resend')}
                                            </Link>
                                        </p>

                                        {status ===
                                            'verification-link-sent' && (
                                            <div className="mt-2 text-sm font-medium text-success">
                                                {t(
                                                    'settings.profile.link_sent',
                                                )}
                                            </div>
                                        )}
                                    </div>
                                )}
                        </div>
                    </SectionCard>

                    <SectionCard
                        id="profile-preferences"
                        icon={SlidersHorizontal}
                        title={t('rbac.users.preferencesSection')}
                    >
                        <div className="@container">
                            <PreferenceFields
                                {...section}
                                locales={options.locales}
                                timezones={options.timezones}
                                themes={options.themes}
                            />
                        </div>
                    </SectionCard>

                    <div className="flex items-center gap-4">
                        <Button
                            disabled={form.processing}
                            data-test="update-profile-button"
                        >
                            {t('settings.save')}
                        </Button>

                        <Transition
                            show={form.recentlySuccessful}
                            enter="transition ease-in-out"
                            enterFrom="opacity-0"
                            leave="transition ease-in-out"
                            leaveTo="opacity-0"
                        >
                            <p className="text-sm text-muted-foreground">
                                {t('settings.saved')}
                            </p>
                        </Transition>
                    </div>
                </form>
            </SettingsLayout>
        </AppLayout>
    );
}
