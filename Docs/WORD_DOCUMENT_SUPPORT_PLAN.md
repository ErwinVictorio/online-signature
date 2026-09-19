# Word Document Support Plan

Status: Application implementation and local verification completed on 2026-09-19. Production service-account, firewall, font licensing, and process supervision checks remain deployment responsibilities; see [OPERATIONS.md](OPERATIONS.md).

## Execution record — 2026-09-19

- Continued the existing converter, inspectors, migration, and queue implementation; connected upload, editor, draft/signing guards, layout review, retries, downloads, and deletion.
- `file_path` remains the immutable original; `editor_pdf_path` identifies the validated PDF. PDF migration backfill preserves signed versions and audit links, including SQLite table rebuilds.
- Added stale/duplicate/deleted-job protection checks, publication/storage failure checks, explicit queue audit actors, unsafe-package rejection, and timeout/retry verification.
- Local LibreOffice 26.8.0.3 runs from the verified MSI extracted under `storage/app/libreoffice/SourceDir/LibreOffice`; `.env` points to its `program/soffice.com`. The system-wide Windows Installer was busy (1618), so the extracted runtime is used instead.
- Real DOCX and binary DOC samples convert to 2-page PDFs. A richer DOCX sample converts to 3 pages with spanning/nested tables, a source image, headers/footers, Unicode, missing-font substitution, and mixed page orientation. Originals remain intact.
- Automated backend suite: 36 tests, 307 assertions. Editor unit tests and production build pass. Browser coverage includes the existing PDF signing workflow and real queued Word conversion through signed/original/converted downloads.
- The converter profile disables macros and active content and redirects HTTP(S) proxy traffic. This is defense in depth, not an operating-system sandbox; production workers still require restricted accounts, network denial, and resource supervision.
- Google Docs links and editable signed DOCX output remain outside this plan.

## Objective

Extend the PDF Signature Tool to accept Microsoft Word documents while reusing the existing PDF signing editor.

Supported uploads:

- PDF (`.pdf`)
- Microsoft Word (`.docx`)
- Legacy Microsoft Word (`.doc`)

Word documents will be converted to PDF before editing. The signed output will be a PDF. The original uploaded file must remain unchanged.

## Scope and boundaries

- Preserve the existing PDF upload and signing workflow.
- Reuse signatures, drag/resize, text, dates, page navigation, undo/redo, drafts, and signed versions.
- Keep Word originals, converted PDFs, and signed PDFs in private storage with ownership checks.
- For Google Docs, initially let users download their document as DOCX and upload it here.
- Direct Google Docs link import, Google account integration, and editable signed Word output are separate future features.
- Conversion may change pagination, font appearance, or layout. Require the user to review the converted PDF before signing.
- Existing placement templates remain available, but users must review positions against the converted document's actual pages.

## Proposed workflow

1. User uploads a PDF, DOCX, or DOC file.
2. Laravel validates the actual format and size.
3. Store the original file privately with a generated filename.
4. PDF uploads follow the existing validation and editor workflow.
5. Word uploads enter the conversion queue.
6. A server-side converter creates a separate PDF in an isolated temporary directory.
7. Validate the generated PDF and determine its page count.
8. Mark the document ready and allow the user to open the editor.
9. User reviews the conversion, then places signatures and other elements.
10. Generate, preview, save, and download the signed PDF through the existing workflow.

## Phase 1 — Conversion setup

### Tasks

- Evaluate a server-side converter, with headless LibreOffice as the initial candidate.
- Verify supported DOC/DOCX inputs, Windows execution, deployment requirements, licensing, and conversion quality before selecting the dependency.
- Configure executable location, timeout, concurrency, and temporary working directories through application configuration.
- Run conversion through a process API with separate arguments, rather than shell command concatenation.
- Use generated filenames and a separate working directory and converter profile for each job.
- Run conversion under a restricted service account with macros disabled and external resource access restricted.
- Clean up temporary output after both success and failure.
- Provide an actionable configuration error if conversion is unavailable; existing PDF uploads must remain usable.

### Deliverable

A conversion service that can safely produce a validated PDF from representative Word files on the target server.

## Phase 2 — Upload and data model

### Tasks

- Extend frontend file selection and drag/drop to PDF, DOCX, and DOC.
- Validate extensions, MIME types, and actual format structure. A renamed file must not pass solely because of its extension.
- For DOCX, verify the expected Office Open XML package structure and enforce archive expansion limits.
- Reject unsupported, corrupted, encrypted, or password-protected inputs with clear feedback.
- Retain the existing 20 MB original-upload limit initially; confirm suitability during testing.
- Reject macro-enabled formats such as DOCM; handle legacy DOC input through the restricted converter without executing embedded content.
- Add fields that distinguish the uploaded original from the PDF used by the editor.
- Ensure Word records can exist before page count is known.
- Backfill existing PDF records without copying, replacing, or modifying their stored files.

### Proposed record fields

| Field | Purpose |
| --- | --- |
| `source_format` | PDF, DOCX, or DOC |
| `original_file_path` | Immutable uploaded file |
| `editor_pdf_path` | Validated PDF used for preview and signing |
| `conversion_status` | Not required, queued, converting, ready, or failed |
| `conversion_error` | Sanitized user-facing failure message |
| `conversion_attempt` | Identifies the current attempt and rejects stale results |
| `converted_at` | Successful conversion timestamp |
| `page_count` | Nullable until the editor PDF has been validated |

These are proposed names. Reconcile them with the existing `file_path`, `status`, revisions, and download routes before migration. Keep conversion state separate from uploaded/editing/signed document state.

### Deliverable

Validated private uploads with a backward-compatible record structure for original and converted files.

## Phase 3 — Background conversion

### Tasks

- Dispatch conversion only after the upload record and original file are committed.
- Configure a queue worker for conversion jobs.
- Show Queued, Converting, Ready, and Conversion failed states in document pages.
- Add bounded execution time and limited retries for transient failures.
- Provide an owner-authorized manual retry action for failed conversions.
- Prevent simultaneous duplicate jobs for the same document.
- Check the conversion attempt before publishing results so an older job cannot overwrite a newer result.
- Handle document deletion during conversion without recreating records or leaving published files behind.
- Validate generated PDF structure, size, readability, and the existing 1–500 page limit before marking it ready.
- Keep the original after conversion failure; remove incomplete temporary output.
- Keep detailed diagnostics in server logs and expose only safe, actionable messages to users.

### Deliverable

A recoverable conversion process with clear progress, failure, and retry behavior.

## Phase 4 — Editor integration

### Tasks

- Serve the validated editor PDF through authenticated ownership-checked routes.
- Open the existing PDF editor only after conversion succeeds.
- Enforce readiness on the backend as well as disabling unavailable UI actions.
- Use the converted PDF as the immutable basis for drafts and signed exports.
- Display the original filename and identify that the user is reviewing its converted PDF.
- Require review of the converted layout before signing.
- Preserve normalized placement coordinates, rotation/crop handling, and revision conflict protection.
- Check template page references against the converted page count.
- Avoid reconverting a ready document after placements or signed versions exist. A revised Word file should be uploaded as a new document in this first version.

### Deliverable

Word uploads use the existing signing editor after conversion, with the existing PDF workflow preserved.

## Phase 5 — Downloads and activity history

### Tasks

- Provide separate downloads for the original Word file, converted PDF, and signed PDF versions.
- Use the correct MIME type and extension for each download.
- Example filenames: `Agreement.docx`, `Agreement-converted.pdf`, and `Agreement-signed.pdf`.
- Preserve all originals and previously saved signed versions.
- Record conversion queued, started, succeeded, failed, and retried events against the document owner.
- Ensure queue jobs record the initiating user explicitly rather than relying on a browser authentication session.
- Keep download and signing audit events consistent with the existing application.
- Delete all associated files when the owner deletes the document, including conversion artifacts and signed versions.

### Deliverable

Clear file provenance, private downloads, and conversion activity history.

## Phase 6 — Verification and deployment

### Conversion fixtures

- Simple DOCX and legacy DOC documents.
- Tables spanning pages and nested tables.
- Images, signatures already present in the source, headers, and footers.
- Portrait and landscape pages, page breaks, margins, and page numbering.
- Unicode text and documents using fonts absent from the server.
- Corrupted, encrypted, oversized, and mislabeled files.
- Documents that produce excessive PDF size or page counts.

### Automated checks

- Existing PDF upload, draft, signature, export, and download tests remain passing.
- Ownership checks cover original Word files, converted PDFs, retries, and signed copies.
- Conversion success, timeout, failure, retry, and duplicate execution are tested.
- Deleted documents and stale attempts cannot publish conversion output.
- Failed conversion leaves the original intact and cannot open or sign an unvalidated PDF.
- Migration backfill preserves access to existing originals and signed versions.
- Generated-file failures do not leave a document marked ready.
- Browser workflow covers Word upload, conversion progress, preview, signing, and all download options.

### Deployment checks

- Install and configure the selected converter and required fonts on the target server.
- Configure queue processing, restart behavior, timeouts, and worker permissions.
- Confirm PHP/web-server upload limits match the application limits.
- Verify temporary-directory cleanup and resource limits under concurrent uploads.
- Document converter installation, queue startup, retry handling, and troubleshooting.
- Record any known layout limitations based on actual conversion fixtures.

### Deliverable

A tested Word-to-signed-PDF workflow with documented deployment requirements and known conversion limitations.

## Acceptance criteria

The feature is complete when a user can:

1. Upload a supported PDF, DOCX, or DOC file.
2. See conversion progress or an actionable error for Word uploads.
3. Open and review the converted PDF.
4. Place signatures and other supported elements using the existing editor.
5. Save drafts and generate signed PDF versions.
6. Download the unchanged original, converted PDF, and signed PDF separately.
7. See conversion and signing activity history.

Existing PDF workflows must continue to pass verification. Each account must remain unable to access another account's files or conversion actions.

## Future separate plans

- Direct Google Docs import through an authorized Google integration.
- Editable signed DOCX output and its separate placement model.
- Support for additional Office formats.

## Implementation order

Execute phases 1–6 in order. Validate the converter on real layout samples before connecting it to the upload workflow. This document authorizes no implementation by itself; application changes begin when the user requests execution.
