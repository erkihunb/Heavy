<?php

namespace App\Http\Controllers\Applicant;

use App\Actions\Organizations\CreateOrganization;
use App\Actions\Organizations\UpdateOrganization;
use App\Http\Controllers\Controller;
use App\Http\Requests\Applicant\StoreOrganizationRequest;
use App\Http\Requests\Applicant\UpdateOrganizationRequest;
use App\Http\Resources\OrganizationResource;
use App\Models\Organization;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The applicant's own organization profile. There is no organization id in
 * any URL: it is always the one the signed-in user belongs to.
 */
class OrganizationController extends Controller
{
    public function show(Request $request): Response
    {
        $user = $request->user();
        $organization = $user->organization;

        if ($organization === null) {
            Gate::authorize('create', Organization::class);
        } else {
            Gate::authorize('view', $organization);
        }

        return Inertia::render('applicant/organization', [
            'organization' => $organization === null ? null : OrganizationResource::make($organization),
            'canUpdate' => $organization === null || $user->can('update', $organization),
        ]);
    }

    public function store(StoreOrganizationRequest $request, CreateOrganization $createOrganization): RedirectResponse
    {
        $createOrganization->handle($request->user(), $request->toData());

        return to_route('organization.show')->with('success', __('organization.created'));
    }

    public function update(UpdateOrganizationRequest $request, UpdateOrganization $updateOrganization): RedirectResponse
    {
        /** @var Organization $organization */
        $organization = $request->organization();

        $updateOrganization->handle($organization, $request->toData());

        return to_route('organization.show')->with('success', __('organization.updated'));
    }
}
