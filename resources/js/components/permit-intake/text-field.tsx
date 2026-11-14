import { FormField } from '@/components/form-field';
import { errorsOf } from '@/components/permit-intake/form';
import type { IntakeForm } from '@/components/permit-intake/form';
import { Input } from '@/components/ui/input';

/**
 * A plain text field of the form, by the name of what it holds: its label,
 * its hint and the server's error under it.
 */
export function intakeTextField(form: IntakeForm) {
    const { data } = form;
    const errors = errorsOf(form);

    const text = (
        field:
            | 'contact_name'
            | 'name_am'
            | 'name_en'
            | 'address'
            | 'owner_name'
            | 'type_detail',
        label: string,
        extra: {
            lang?: string;
            maxLength?: number;
            autoComplete?: string;
            hint?: string;
            error?: string;
            translate?: 'no';
        } = {},
    ) => {
        const { hint, error, ...input } = extra;

        return (
            <FormField
                id={field}
                label={label}
                hint={hint}
                error={error ?? errors[field]}
            >
                {(control) => (
                    <Input
                        {...control}
                        {...input}
                        value={data[field]}
                        onChange={(event) =>
                            form.setData(field, event.target.value)
                        }
                    />
                )}
            </FormField>
        );
    };

    return text;
}
