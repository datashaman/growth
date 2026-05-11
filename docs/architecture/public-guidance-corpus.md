# Public Guidance Corpus

Public NASA and NIST guidance can support internal rule-pack extraction without
serving proprietary source text.

## Sources

The initial catalog lives in `App\Growth\Guidance\PublicGuidanceCatalog`:

- `nasa-seh`: NASA Systems Engineering Handbook
- `nasa-risk`: NASA Risk Management Handbook
- `nasa-formal-inspections`: NASA Software Formal Inspections Source
- `nist-ssdf`: NIST SP 800-218 Secure Software Development Framework
- `nist-risk`: NIST SP 800-30 Rev. 1 Guide for Conducting Risk Assessments
- `nist-sse`: NIST SP 800-160 Vol. 1 Systems Security Engineering

Each entry records the publisher, official source URL, download URL, reuse
posture, and likely rule-pack opportunities.

## Runtime Surface

The public guidance catalog is currently an internal source for approved
rule-pack extraction. It is not exposed through the role-based MCP servers by
default.

The legacy guidance resource and search/list tools still exist in code for
future use, but they are not registered on `intake`, `architecture`,
`planning`, `verification`, `governance`, or `readonly`.

## Ingestion

Local extraction is explicit:

```sh
php artisan growth:ingest-public-guidance --download
```

or, using already downloaded files:

```sh
php artisan growth:ingest-public-guidance --source=/path/to/pdfs
```

The source directory must contain files named by guidance id, such as
`nasa-seh.pdf` or `nist-ssdf.pdf`. Extracted text and metadata are written under
the configured local disk at `growth/public-guidance/`.

The command requires `pdftotext` to be installed. It is for approved public
guidance only.

## Review And Audit Processes

Rule extraction for review processes should use public guidance such as
`nasa-formal-inspections`, plus NASA Software Engineering Handbook pages for
human reference. The initial review-process rule-pack should focus on review
planning, entrance criteria, participant roles, preparation, finding capture,
exit criteria, records, and effectiveness measurements.
