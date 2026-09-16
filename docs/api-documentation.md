# API Documentation

`openapi.yaml` at the project root is a hand-authored OpenAPI 3.0 specification covering every endpoint: request/response shapes, the shared error envelope, role/state requirements in plain English, and the `Idempotency-Key` requirement where it applies.

## Why hand-authored, not annotation-generated

Laravel's ecosystem offers annotation-based generators (`darkaonline/l5-swagger` and similar) that build the spec from PHPDoc-style `@OA\...` blocks scattered across controllers. That approach was deliberately not used here:

- Every endpoint would need its annotations kept in sync with its Form Request and Resource by hand anyway — the annotations don't derive from the validation rules or the resource shape automatically, so there's no real "generated from source of truth" benefit, just a different place to maintain the same information.
- A static spec file is trivially reviewable in a pull request as a single diff, is renderable without running the application at all (any OpenAPI viewer, or just reading the YAML), and has zero risk of an annotation-scanning step silently failing or drifting from what the routes actually do.
- For a project this size, one well-organized YAML file is easier for a reader to navigate top-to-bottom than eleven controllers' worth of interleaved annotations.

The tradeoff, honestly: this file can drift from the actual implementation if a route changes and the spec isn't updated alongside it — the same risk any hand-maintained documentation carries. Annotation-based generation trades that risk for the different one above. For an API this size with a single maintainer, keeping the two in sync by discipline (updating `openapi.yaml` in the same commit as any route change) is the more practical tradeoff.

## Viewing it

```bash
docker compose up -d swagger-ui
# then open http://localhost:8081
```

Or open `openapi.yaml` directly in any OpenAPI-aware editor, or paste it into https://editor.swagger.io.
