# Architecture

How the permit system is put together. Decisions and assumptions are in
[DECISIONS.md](DECISIONS.md); what each milestone built is in [plans/](plans/).

## Layers

```
HTTP (thin controllers) → FormRequest (validation, authorisation) → DTO (App\Data)
    → Action (App\Actions, one per state change, inside a DB transaction)
        → Domain services (App\Domain: state machine, overlap guard, fees, tokens, verifier)
            → Eloquent models → MySQL / MariaDB
Policies (App\Policies) decide who; the state machine decides when.
Resources (App\Http\Resources) are explicit whitelists of what reaches the browser.
```

## Domain model

```mermaid
erDiagram
    ORGANIZATION ||--o{ USER : "applicant members"
    ORGANIZATION ||--o{ VEHICLE : owns
    ORGANIZATION ||--o{ PERMIT : applies
    VEHICLE_TYPE ||--o{ VEHICLE : classifies
    VEHICLE_TYPE |o--o{ FEE_SCHEDULE : "prices (null = every type)"
    PERMIT ||--|{ PERMIT_VEHICLE : lists
    VEHICLE ||--o{ PERMIT_VEHICLE : "is on"
    PERMIT ||--o{ PERMIT_STATUS_TRANSITION : "history (append-only)"
    PERMIT ||--o{ PAYMENT : "paid by"
    PERMIT ||--o{ VERIFICATION_SCAN : "checked"
    PERMIT ||--o{ PERMIT_REPORT : "reported by officers"
    USER ||--o{ PERMIT_STATUS_TRANSITION : acts
    USER ||--o{ PAYMENT : "submits / verifies"

    PERMIT {
        ulid id
        string permit_number "AACRA-<E.C. year>-<6 digits>, set at approval"
        string status
        date valid_from
        date valid_to
        bigint fee_cents "snapshot at approval"
        char verification_token_hash "sha256, set at issuance"
        string document_path "private disk"
    }
    PERMIT_VEHICLE {
        string role "primary | trailer"
        bool is_blocking "reserves the vehicle for its dates"
    }
    PAYMENT {
        string receipt_number "unique while not rejected"
        bigint amount_cents
        string status "submitted | verified | rejected"
    }
    VERIFICATION_SCAN {
        string result
        string channel "qr | lookup"
        char ip_hash "HMAC of the IP"
    }
```

## Permit lifecycle

`active` is never stored: an `issued` permit is *active* on the days it covers and
*expired* after them (`Permit::effectiveStatus()`), so verification is right even before
the nightly job runs.

```mermaid
stateDiagram-v2
    [*] --> draft
    draft --> submitted: submit
    draft --> withdrawn: withdraw
    submitted --> under_review: start review
    submitted --> withdrawn: withdraw
    under_review --> changes_requested: request changes
    changes_requested --> submitted: resubmit
    under_review --> rejected: reject (Approver)
    under_review --> awaiting_payment: approve (Approver, fee snapshot, number)
    awaiting_payment --> payment_submitted: submit receipt
    payment_submitted --> awaiting_payment: reject payment
    payment_submitted --> issued: verify payment (token + PDF)
    issued --> expired: valid_to passed
    issued --> revoked: revoke (reason)
    rejected --> [*]
    withdrawn --> [*]
    expired --> [*]
    revoked --> [*]
```

Every arrow is one Action calling `PermitTransitions::apply()`: lock the permit row, ask
`PermitStateMachine`, apply the Action's changes, save, re-sync `permit_vehicles`, append
a history row. Reissuing an issued permit records an `issued → issued` row.

## Verification flow

```mermaid
sequenceDiagram
    autonumber
    participant Cashier
    participant App
    participant Queue
    participant Chrome as Chrome (headless)
    participant Phone as Phone / officer
    Cashier->>App: verify payment
    App->>App: transaction: status issued, token = 32 random bytes,<br/>store sha256(token) only
    App-->>Queue: after commit: GeneratePermitDocument(permit, token)<br/>(payload encrypted)
    Queue->>Chrome: HTML (QR SVG, fonts embedded)
    Chrome-->>Queue: PDF
    Queue->>App: store on private disk if the token is still current
    Phone->>App: GET /v/{token} (QR) or POST /verify (number + plate)
    App->>App: sha256(token) lookup → effectiveStatus() → result
    App->>App: log scan (HMAC IP, user agent, officer if signed in)
    App-->>Phone: VALID / NOT YET VALID / EXPIRED / REVOKED / NOT FOUND<br/>(no-store, noindex, 30/min/IP)
```

- The QR code holds only `https://{verify_url}/v/{token}`: no personal data, no ids.
- Reissuing rotates the token: the old QR code answers NOT FOUND at once, a stale PDF job
  does nothing, and the old file is deleted.
- Revoking changes the status; the next scan answers REVOKED (nothing is cached).
- The public page is server-rendered Blade and works without JavaScript. A signed-in
  traffic officer (or Admin) gets the Inertia officer view of the same scan, with
  history, recent scans, reports and a report form.

## Audit log and settings

```mermaid
flowchart LR
    A[Action<br/>ApprovePermit, VerifyPayment,<br/>ChangeUserRole, SystemSettings…] -->|in the same transaction| H[(permit_status_transitions)]
    A -->|AuditLog::record| L[(activity_log<br/>append-only triggers)]
    A -->|after commit| N[Notifications<br/>mail · SMS]
    A -->|after commit| T[SendTelegramMessage job]
    S[(settings)] -->|boot + every queued job| C[config&#40;&#41;]
    C --> A
```

- Actions record who, what, before and after; database triggers refuse updates and deletes.
- Saved settings override `config()` keys; secrets are encrypted with `APP_KEY`.

## Deployment

```mermaid
flowchart LR
    U[Browser / phone] -->|HTTPS| P[Reverse proxy<br/>TLS]
    P -->|HTTP| W[web<br/>nginx]
    W -->|FastCGI| APP[app<br/>PHP-FPM]
    APP --> DB[(mariadb)]
    APP --> R[(redis<br/>cache · sessions · queue)]
    WK[worker<br/>queue:work] --> R
    WK --> DB
    WK --> G[Chrome headless<br/>PDF]
    SC[scheduler<br/>schedule:work] --> R
    APP & WK & SC --- V[(storage volume<br/>private documents)]
    B[backup<br/>daily dump + files] --> DB
    B --- V
```

`/up` checks the database, the cache and the queue. Errors go to Sentry when a DSN is set.

## Phase 2: offline verification (designed, not built)

For roadside checks without a network, the QR code would carry a compact signed payload
next to the online address:

- Payload (CBOR or a fixed binary layout, then base45 for an alphanumeric QR):
  permit number, plate(s) normalised, valid_from, valid_to, permit type, issued_at, key id.
- Signature: Ed25519 (`sodium_crypto_sign_detached`) by a key held only by the issuing
  server; officer apps ship the public keys, rotated by key id.
- The officer app verifies the signature and dates offline, and shows "signature valid,
  status unknown — revocation not checked" until it can sync a revocation list
  (permit numbers revoked since the last sync).
- Consequences to accept: a revoked permit still verifies offline until the next sync;
  the payload makes the QR code denser (error correction must stay ≥ M).

Nothing in the current schema blocks this: tokens and documents are already rotated per
issuance, and the verifier is a single service.
