<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\Admin\Concerns\ValidatesStaffProfile;
use App\Models\User;
use App\Support\Rbac\PermissionCatalog;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * The permissions a member of staff holds directly, on top of their roles.
 * Whoever may give roles may give these; what may be added is checked by
 * App\Actions\Users\SyncUserPermissions.
 */
class UpdateUserPermissionsRequest extends FormRequest
{
    use ValidatesStaffProfile;

    public function authorize(): bool
    {
        $target = $this->route('user');

        return $target instanceof User && ($this->user()?->can('assignRoles', $target) ?? false);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'permissions' => ['present', 'array'],
            'permissions.*' => ['string', 'distinct', Rule::in(PermissionCatalog::staffPermissions())],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['permissions' => __('rbac.users.fields.permissions'), 'permissions.*' => __('rbac.users.fields.permissions')];
    }
}
