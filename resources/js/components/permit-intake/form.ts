import type { InertiaFormProps } from '@inertiajs/react';
import type { LetterValue } from '@/components/letter-fields';

/*
 * The office's application form (pages/admin/permits/create.tsx): the shape
 * of what it holds, and which field belongs to which numbered step.
 */

/** The vehicle type whose vehicles are described in words (App\Models\VehicleType::OTHER). */
export const OTHER_TYPE = 'other';
/** The type a trailer plate is registered with (App\Models\VehicleType::TRAILER). */
export const TRAILER_TYPE = 'trailer';

export type IntakeFormData = {
    // 1. The applicant: an organization on record, or a new one.
    applicant_id: string;
    contact_email: string;
    contact_name: string;
    phone: string;
    name_am: string;
    tin: string;
    // More about the organization (optional).
    name_en: string;
    email: string;
    address: string;
    locale: string;
    apply: boolean;
    submit: boolean;
    // 2. The vehicle, and its trailer if any.
    owner_name: string;
    vehicle_type_id: string;
    type_detail: string;
    plate: string;
    trailer_plate: string;
    // 3. The permit.
    permit_type: string;
    reason: string;
    valid_from: string;
    valid_to: string;
    any_time: boolean;
    move_from: string;
    move_to: string;
} & LetterValue;

export type StepKey = 'applicant' | 'vehicle' | 'permit';

export type StepState = 'done' | 'todo' | 'optional' | 'error';

export const filled = (...values: string[]) =>
    values.every((value) => value.trim() !== '');

/** Which form fields belong to which step, to mark a step that has errors. */
export const STEP_FIELDS: Record<StepKey, (field: string) => boolean> = {
    applicant: (field) =>
        [
            'applicant_id',
            'apply',
            'contact_name',
            'contact_email',
            'locale',
            'name_am',
            'name_en',
            'tin',
            'phone',
            'email',
            'address',
        ].includes(field),
    vehicle: (field) => field.startsWith('vehicles'),
    permit: (field) =>
        [
            'permit_type',
            'valid_from',
            'valid_to',
            'reason',
            'any_time',
            'move_from',
            'move_to',
            'letter_number',
            'letter_date',
            'letter_file',
        ].includes(field),
};

/** Fields the organization's details hide under "more", to open them on an error. */
export const MORE_FIELDS = ['name_en', 'email', 'address', 'locale'];

/** The form as Inertia holds it, handed to each part of the page. */
export type IntakeForm = InertiaFormProps<IntakeFormData>;

/** Errors by field; those for the vehicles arrive under keys such as "vehicles.1.plate". */
export const errorsOf = (form: IntakeForm) =>
    form.errors as Partial<Record<string, string>>;
