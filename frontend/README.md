# LendFlow Dashboard

The Vue frontend for LendFlow — a thin client over the Laravel API. Per the project brief, this is intentionally secondary to the backend: it covers the full loan lifecycle (apply, submit, assess, approve/reject, disburse, repay) and admin user management, styled to the fintech design tokens from the brief, in light/dark/system modes.

## Stack

- Vue 3 (Composition API, `<script setup>`)
- Vite
- Vue Router 4
- Pinia
- Axios
- Tailwind CSS

## Running it

```bash
cp .env.example .env   # point VITE_API_URL at your running LendFlow API
npm install
npm run dev             # http://localhost:5173
```

Or via Docker, from the repository root:

```bash
docker compose --profile frontend up -d frontend
```

## How it talks to the API

- `src/api/client.js` — an Axios instance that attaches the bearer access token to every request and transparently refreshes it on a `401`, queuing any other in-flight requests until the refresh resolves (so a burst of near-simultaneous requests doesn't trigger a burst of racing refresh calls).
- `src/api/tokenStorage.js` — the one place tokens are read from/written to `localStorage`, used by both the Axios client and the Pinia auth store so neither has to import the other.
- Every mutating financial action (disburse, repay) sends a fresh `Idempotency-Key: crypto.randomUUID()` header per attempt — matching the backend's idempotency contract (`docs/idempotency.md` in the API repo).

## Design notes

- Colors, light/dark tokens, and semantic status colors follow the exact palette specified in the project brief, wired through CSS custom properties (`src/style.css`) so Tailwind's `dark:` variant and the custom `theme` values both resolve against the same source.
- `StatusPill.vue` is the single place a domain status (loan application status, loan status, installment status, user status) maps to a color — new statuses get one new entry there, never a scattered set of ad hoc choices per view.
- Currency figures use tabular (fixed-width) numerals (`.tabular-figures`, via `font-variant-numeric`) so digits align down a column in schedules and transaction lists — a functional choice for scanning financial data, not a decorative font swap.
- Row-level visibility already enforced by the API (a customer only ever receives their own applications/loans) means the frontend doesn't need its own authorization logic beyond hiding actions the current role can't take — the source of truth for "can I do this" is always the API's response, never a client-side assumption.

## What's intentionally not here

No offline support, no optimistic UI updates (every action waits for the API's response before updating state — correctness over perceived speed, consistent with the backend's own emphasis), no test suite for the frontend (the project's testing effort went into the backend, per the brief's own priority order).
