---
paths:
    - 'app/Domain/**/Models/**'
---

# Models

## Tenant-owned models use BelongsToUser

Every user is their own tenant. Client, Project, TimeEntry, Invoice and HarvestImport use App\Domain\Shared\Models\Concerns\BelongsToUser, which adds UserScope (queries only return rows of auth()->id()) and fills user_id from the authenticated user on create (Project takes its client's owner). Consequences: route model binding 404s on another user's row; without an authenticated user (jobs, commands, scheduler) nothing is filtered, so scope explicitly with Model::ownedBy($user) and pass user_id on create. Never use withoutGlobalScopes() to bypass it (that also drops SoftDeletes); use withoutGlobalScope(UserScope::class). Ids in validation must use App\Domain\Shared\Validation\Owned::exists('table') instead of a plain exists rule. New tenant models: add the trait, a user_id FK and a (user_id, harvest_id) unique instead of a global harvest_id unique.
