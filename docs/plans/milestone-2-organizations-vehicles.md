# Milestone 2 — Organizations & Vehicles: plan

Scope (spec §12.2): models, migrations, policies, applicant CRUD, tests — for the
organization profile and the vehicle register (spec §2, §4.2, §9, §11).
Assumptions are recorded in [../DECISIONS.md](../DECISIONS.md).

## Migrations

| Migration | Contents |
|---|---|
| `create_organizations_table` | `id`, `name_am`, `name_en` nullable, `tin` nullable **unique**, `phone`, `email`, `address`, `created_by` FK users (restrict, indexed), timestamps |
| `add_organization_and_phone_to_users_table` | `organization_id` FK organizations nullable (restrict, indexed), `phone` nullable |
| `create_vehicle_types_table` | `id`, `code` **unique**, `name_am`, `name_en`, `is_active`, `sort_order`, timestamps |
| `create_vehicles_table` | `id` ULID, `organization_id` FK (restrict), `vehicle_type_id` FK (restrict), `plate_raw`, `plate_normalized` **unique**, `region_code` (indexed), `owner_name`, timestamps |

## Backend

| Area | Classes |
|---|---|
| Models | `Organization`, `VehicleType`, `Vehicle` (+ factories); `User::organization()` |
| DTOs | `App\Data\OrganizationData`, `App\Data\VehicleData` |
| Actions | `Organizations\CreateOrganization`, `Organizations\UpdateOrganization`, `Vehicles\RegisterVehicle`, `Vehicles\UpdateVehicle` |
| Exceptions | `DuplicateVehiclePlate`, `UserAlreadyHasOrganization` |
| Policies | `OrganizationPolicy`, `VehiclePolicy` (cross-organization access is denied as 404) |
| Requests | `StoreOrganizationRequest`, `UpdateOrganizationRequest`, `StoreVehicleRequest`, `UpdateVehicleRequest` |
| Resources | `OrganizationResource`, `VehicleResource`, `VehicleTypeResource` (whitelisted fields) |
| Controllers | `Applicant\OrganizationController` (show/store/update), `Applicant\VehicleController` (index/create/store/edit/update) |
| Middleware | `EnsureUserHasOrganization` (sends an applicant without a profile to create one) |
| Support | `EthiopianPhone` (normalises to `+251…`) |
| Seeders | `VehicleTypeSeeder` (placeholder list from the spec), demo organization for the demo applicant |

Rules implemented: plate normalisation and duplicate detection (validation + DB unique
index + race handling in the Action); tenant isolation; only the organization owner edits
the profile; an applicant has at most one organization.

Removed: the starter kit's self-service "delete account" (accounts carry permit history).

## Frontend

- Pages `applicant/organization`, `applicant/vehicles/{index,create,edit}` using `useForm`.
- Components `PlateInput` (controlled region / code / number), `FlashMessage`,
  `Pagination`, `FormField`.
- Sidebar entries for applicants; dashboard "next step" card.
- Language files `organization`, `vehicles` in Amharic and English.

## Tests

| Subject | File |
|---|---|
| Organization create/update, validation, one-per-user, owner-only edit | `tests/Feature/Organizations/OrganizationTest.php` |
| Vehicle register/update/list/filter, plate normalisation, duplicates, DB constraints | `tests/Feature/Vehicles/VehicleTest.php` |
| Cross-organization access denied | `tests/Feature/Vehicles/VehicleTenantIsolationTest.php` |
| Role × action matrix | `tests/Feature/Policies/OrganizationPolicyTest.php`, `VehiclePolicyTest.php` |
| Vehicle type seeder | `tests/Feature/VehicleTypeSeederTest.php` |
| Phone normalisation | `tests/Unit/Support/EthiopianPhoneTest.php` |
| `PlateInput` | `resources/js/components/plate-input.test.tsx` |

Deferred: generated TypeScript enum types (first needed for `PermitStatus` in
Milestone 3), vehicle deletion, inviting further users into an organization, admin CRUD
for vehicle types.
