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

/** Another vehicle of the same request letter: a request of its own, on the same terms. */
export type MoreVehicle = {
    /** The trailer row is open (the form's own state; not sent). */
    with_trailer: boolean;
    vehicle_type_id: string;
    type_detail: string;
    plate: string;
    trailer_plate: string;
    /** Who owns the vehicle (and its trailer); empty means the organization asking. */
    owner_name: string;
};

export type IntakeFormData = {
    // 1. The applicant: an organization on record, or a new one.
    applicant_id: string;
    contact_email: string;
    contact_name: string;
    phone: string;
    name_am: string;
    tin: string;
    locale: string;
    apply: boolean;
    submit: boolean;
    // 2. The vehicle, and its trailer if any, and who owns them.
    vehicle_type_id: string;
    type_detail: string;
    plate: string;
    trailer_plate: string;
    /** Printed on the certificate; empty means the organization asking. */
    owner_name: string;
    /** The other vehicles the same letter asks for. */
    more: MoreVehicle[];
    // 3. The permit.
    permit_type: string;
    reason: string;
    /** Where the vehicles may travel; empty when the permit does not limit it. */
    route: string;
    /** The fee rate (schedule line) chosen; the permit runs for its days from valid_from. */
    fee_schedule_id: string;
    valid_from: string;
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
    vehicle: (field) =>
        field.startsWith('vehicles') || field.startsWith('more'),
    permit: (field) =>
        [
            'permit_type',
            'fee_schedule_id',
            'valid_from',
            'reason',
            'route',
            'any_time',
            'move_from',
            'move_to',
            'letter_number',
            'letter_date',
            'letter_file',
        ].includes(field),
};

/** The form as Inertia holds it, handed to each part of the page. */
export type IntakeForm = InertiaFormProps<IntakeFormData>;

/** Errors by field; those for the vehicles arrive under keys such as "vehicles.1.plate". */
export const errorsOf = (form: IntakeForm) =>
    form.errors as Partial<Record<string, string>>;
