<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Users\RegisterApplicant;
use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\RegisterApplicantRequest;
use App\Models\User;
use App\Support\EthiopianCalendar;
use App\Support\Paginated;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Applicants registered at the office (walk-ins). A Registrar, or an Admin,
 * opens the account and the organization for a person who came in; the
 * person then sets their own password from an e-mailed link. The list shows
 * every applicant, so a Registrar can check whether someone is already
 * registered before opening a second account.
 */
class ApplicantController extends Controller
{
    private const int PER_PAGE = 25;

    public function index(Request $request): Response
    {
        Gate::authorize('registerApplicant', User::class);

        $search = is_string($request->query('search')) ? trim($request->query('search')) : '';
        $source = in_array($request->query('source'), ['office', 'online'], true) ? (string) $request->query('source') : '';

        $applicants = User::query()
            ->role(Role::Applicant->value)
            ->with(['organization:id,name_am,name_en,tin,phone', 'registeredBy:id,name'])
            ->when($search !== '', fn (Builder $query) => $query->where(fn (Builder $query) => $query
                ->whereLike('name', "%{$search}%")
                ->orWhereLike('email', "%{$search}%")
                ->orWhereHas('organization', fn (Builder $query) => $query
                    ->whereLike('name_am', "%{$search}%")
                    ->orWhereLike('name_en', "%{$search}%")
                    ->orWhereLike('tin', "%{$search}%")
                    ->orWhereLike('phone', '%'.preg_replace('/\D/', '', $search).'%'))))
            ->when($source === 'office', fn (Builder $query) => $query->whereNotNull('registered_by'))
            ->when($source === 'online', fn (Builder $query) => $query->whereNull('registered_by'))
            ->latest('id')
            ->paginate(self::PER_PAGE)
            ->withQueryString()
            ->through(fn (User $user): array => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'organization' => $user->organization === null ? null : [
                    'name' => app()->getLocale() === 'en' && filled($user->organization->name_en)
                        ? $user->organization->name_en
                        : $user->organization->name_am,
                    'tin' => $user->organization->tin,
                    'phone' => $user->organization->phone,
                ],
                'registered_by' => $user->registeredBy?->name,
                'created_at' => EthiopianCalendar::present($user->created_at?->setTimezone((string) config('localization.display_timezone')), app()->getLocale()),
            ]);

        return Inertia::render('admin/applicants/index', [
            'applicants' => Paginated::present($applicants),
            'filters' => ['search' => $search, 'source' => $source],
        ]);
    }

    public function create(): Response
    {
        Gate::authorize('registerApplicant', User::class);

        return Inertia::render('admin/applicants/create', [
            'locales' => config('localization.supported'),
        ]);
    }

    public function store(RegisterApplicantRequest $request, RegisterApplicant $registerApplicant): RedirectResponse
    {
        $user = $registerApplicant->handle(
            $request->string('contact_name')->toString(),
            $request->string('contact_email')->toString(),
            $request->string('locale')->toString(),
            $request->toData(),
            $request->user(),
        );

        // Straight on to the vehicles and the application.
        return to_route('admin.applicants.show', $user)->with('success', __('applicants.messages.registered', ['email' => $user->email]));
    }
}
