# PDF Signature Drag-and-Drop System — Full Development Plan

## 1. Project Overview

### Project Name
**PDF Signature Tool**

### Main Problem
Sa current workflow, kapag may PDF document na kailangang pirmahan:

1. Receive PDF document.
2. Print the document.
3. Manually sign the printed copy.
4. Scan the signed document.
5. Send or upload the scanned PDF again.

Maraming unnecessary steps ito at gumagamit pa ng paper at printer/scanner.

### Proposed Solution
Gumawa ng web-based system kung saan ang user ay maaaring:

1. Mag-upload ng PDF.
2. I-preview ang PDF sa browser.
3. Pumili o mag-upload ng saved signature.
4. I-drag ang signature sa desired position.
5. I-resize ang signature.
6. Maglagay ng signature sa specific page.
7. I-preview ang final result.
8. I-apply ang signature sa actual PDF.
9. I-download ang signed PDF.

> Important: Ang first version na ito ay isang **electronic signature/image-based signature system**. Hindi pa ito certificate-based cryptographic digital signature.

---

# 2. Recommended Technology Stack

Dahil compatible ito sa existing Laravel + React workflow:

## Backend

- Laravel 12
- PHP 8.2+
- MySQL
- Laravel Filesystem / Storage
- Laravel Validation
- Laravel Authentication

## Frontend

- React
- Inertia.js
- Vite
- Tailwind CSS
- shadcn/ui
- React Hook Form
- Zod
- Lucide React
- Sonner

---

# 3. Main Libraries

## 3.1 PDF.js / pdfjs-dist

### Purpose
Gagamitin para i-render at i-preview ang PDF document directly sa browser.

### Package

```bash
npm install pdfjs-dist
```

### Responsibility

- Load PDF file.
- Detect number of pages.
- Render each PDF page.
- Handle zoom.
- Provide page dimensions.
- Create the visual layer kung saan ilalagay ang signature overlay.

### Why Recommended

PDF.js is maintained by Mozilla and is specifically designed for parsing and rendering PDF files in web applications.

Official reference:

https://mozilla.github.io/pdf.js/

---

## 3.2 pdf-lib

### Purpose
Ito ang recommended library para i-embed ang signature image sa actual PDF bago i-download.

### Package

```bash
npm install pdf-lib
```

### Responsibility

- Load existing PDF.
- Embed PNG/JPG signature.
- Draw signature image on selected PDF page.
- Add text such as:
  - Date
  - Printed name
  - Initials
- Save the modified PDF.

### Why Recommended

`pdf-lib` supports modifying existing PDFs directly in JavaScript and can embed PNG/JPEG images.

Official reference:

https://pdf-lib.js.org/

---

## 3.3 react-rnd

### Purpose
Para gawing draggable at resizable ang signature overlay.

### Package

```bash
npm install react-rnd
```

### Responsibility

- Drag signature.
- Resize signature.
- Restrict signature inside PDF page.
- Track:
  - X position
  - Y position
  - Width
  - Height

### Why Recommended

Simple itong gamitin sa React at may built-in support para sa parehong dragging at resizing.

Official reference:

https://github.com/bokuweb/react-rnd

---

# 4. Recommended Architecture

```text
Laravel Backend
│
├── Authentication
├── Document Management
├── Signature Management
├── Audit Logs
├── PDF File Storage
└── Signed Document Storage
        │
        ▼
Inertia.js
        │
        ▼
React Frontend
│
├── PDF Upload
├── PDF Viewer
├── Signature Library
├── Drag/Resize Layer
├── Signing Toolbar
└── Download / Export
        │
        ▼
PDF.js + react-rnd + pdf-lib
```

---

# 5. Core System Modules

## Module 1 — Authentication

### Features

- Login
- Logout
- User account
- Optional role management

### Suggested Roles

#### Admin

Can:

- Manage users.
- View all documents.
- Manage settings.
- View audit history.

#### User / Signer

Can:

- Upload PDFs.
- Add own signature.
- Sign documents.
- Download signed documents.

---

# 6. Dashboard

## Suggested Summary Cards

- Total Documents
- Pending Documents
- Signed Documents
- Documents This Month

Example:

```text
┌──────────────────┐
│ Total Documents  │
│       128        │
└──────────────────┘

┌──────────────────┐
│ Pending          │
│        12        │
└──────────────────┘

┌──────────────────┐
│ Signed           │
│       116        │
└──────────────────┘

┌──────────────────┐
│ This Month       │
│        24        │
└──────────────────┘
```

---

# 7. Document Upload Module

## Supported Format

Initially:

- PDF only

Later:

- DOCX to PDF conversion may be added separately.

## Upload UI

```text
┌──────────────────────────────────────────────┐
│ Upload Document                              │
│                                              │
│     Drag and drop PDF here                   │
│               or                             │
│          [ Browse File ]                     │
│                                              │
│ PDF only • Max 20 MB                         │
└──────────────────────────────────────────────┘
```

## Validation

Validate:

- File exists.
- MIME type is PDF.
- Maximum size.
- File is readable.
- PDF is not corrupted.
- Optional password-protected PDF detection.

Example Laravel validation:

```text
required
file
mimes:pdf
max:20480
```

---

# 8. PDF Viewer / Editor

Ito ang main screen ng system.

## Suggested Layout

```text
┌─────────────────────────────────────────────────────────────┐
│ Document Name                         Save | Download       │
├─────────────┬───────────────────────────────────────────────┤
│             │                                               │
│ Pages       │              PDF PREVIEW                      │
│             │                                               │
│ Page 1      │                                               │
│ Page 2      │                 [Signature]                   │
│ Page 3      │                                               │
│             │                                               │
│             │                                               │
├─────────────┤                                               │
│ Signature   │                                               │
│             │                                               │
│ [Preview]   │                                               │
│             │                                               │
│ Drag →      │                                               │
└─────────────┴───────────────────────────────────────────────┘
```

---

# 9. Signature Management

## Signature Sources

Allow users to:

### Option A — Upload Signature

Supported:

- PNG
- JPG/JPEG

Recommended:

- PNG with transparent background.

### Option B — Saved Signature

Once uploaded, the signature can be reused.

### Future Option — Draw Signature

User can manually draw using:

- Mouse
- Touchscreen
- Stylus

Possible future library:

```bash
npm install signature_pad
```

---

# 10. Signature Upload Validation

Recommended rules:

- PNG/JPG only.
- Max 2 MB.
- Validate actual MIME type.
- Recommended transparent PNG.
- Limit image dimensions.
- Store signature privately.

Never expose signature storage publicly without authorization.

---

# 11. Drag-and-Drop Signature Editor

Use:

```text
react-rnd
```

Every signature element needs to track:

```json
{
  "page": 1,
  "x": 420,
  "y": 630,
  "width": 150,
  "height": 60
}
```

However, avoid relying only on screen pixel coordinates.

Store normalized coordinates.

Example:

```json
{
  "page": 1,
  "xRatio": 0.70,
  "yRatio": 0.78,
  "widthRatio": 0.20,
  "heightRatio": 0.07
}
```

This is important because the PDF may be displayed at different zoom levels and screen sizes.

---

# 12. Coordinate Conversion

This is one of the most important technical parts.

Browser coordinates usually start at:

```text
Top Left
(0,0)
```

PDF coordinates typically start at:

```text
Bottom Left
(0,0)
```

Therefore:

```text
PDF Y = Page Height - Browser Y - Signature Height
```

The application must convert displayed coordinates into actual PDF coordinates.

Recommended flow:

```text
React Drag Position
        ↓
Normalize Coordinates
        ↓
Convert to PDF Page Coordinates
        ↓
pdf-lib drawImage()
        ↓
Generate Final PDF
```

---

# 13. Signature Toolbar

Suggested controls:

```text
[ Signature ]
[ Initials ]
[ Name ]
[ Date ]
[ Delete ]
[ Undo ]
[ Redo ]
```

When signature is selected:

```text
Position:
X: automatic
Y: automatic

Size:
Width
Height

[ Reset Size ]
[ Delete ]
```

---

# 14. Multiple Signature Support

Allow multiple signatures per document.

Example:

```text
Page 1
- Signature
- Date

Page 2
- Initials

Page 4
- Signature
- Printed Name
```

Data structure:

```json
[
  {
    "type": "signature",
    "page": 1,
    "xRatio": 0.65,
    "yRatio": 0.80,
    "widthRatio": 0.20,
    "heightRatio": 0.07
  },
  {
    "type": "date",
    "page": 1,
    "xRatio": 0.65,
    "yRatio": 0.88
  }
]
```

---

# 15. Database Design

## users

Existing Laravel users table can be used.

Suggested fields:

```text
id
name
email
password
role
created_at
updated_at
```

---

## signatures

```text
id
user_id
name
image_path
is_default
created_at
updated_at
```

Purpose:

Store reusable signatures.

---

## documents

```text
id
user_id
original_name
stored_name
file_path
signed_file_path
status
page_count
file_size
signed_at
created_at
updated_at
```

Possible status:

```text
uploaded
editing
signed
archived
```

---

## signature_placements

Optional if placements need to be saved before signing.

```text
id
document_id
signature_id
page_number
type
x_ratio
y_ratio
width_ratio
height_ratio
created_at
updated_at
```

---

## audit_logs

Recommended for accountability.

```text
id
user_id
document_id
action
ip_address
user_agent
created_at
```

Example actions:

```text
document_uploaded
document_opened
signature_added
signature_removed
document_signed
document_downloaded
document_deleted
```

---

# 16. Suggested Laravel Structure

```text
app/
├── Http/
│   ├── Controllers/
│   │   ├── DashboardController.php
│   │   ├── DocumentController.php
│   │   ├── SignatureController.php
│   │   └── SignedDocumentController.php
│   │
│   └── Requests/
│       ├── StoreDocumentRequest.php
│       └── StoreSignatureRequest.php
│
├── Models/
│   ├── Document.php
│   ├── Signature.php
│   ├── SignaturePlacement.php
│   └── AuditLog.php
│
└── Services/
    ├── DocumentService.php
    ├── PdfSignatureService.php
    └── AuditLogService.php
```

---

# 17. Suggested React Structure

```text
resources/js/
├── components/
│   ├── pdf/
│   │   ├── PdfViewer.jsx
│   │   ├── PdfPage.jsx
│   │   ├── PdfToolbar.jsx
│   │   ├── PageThumbnail.jsx
│   │   ├── SignatureOverlay.jsx
│   │   └── SignatureSidebar.jsx
│   │
│   └── signature/
│       ├── SignatureCard.jsx
│       ├── SignatureUploader.jsx
│       └── SignatureSelector.jsx
│
└── pages/
    ├── Dashboard.jsx
    ├── Documents/
    │   ├── Index.jsx
    │   ├── Create.jsx
    │   ├── Editor.jsx
    │   └── Show.jsx
    │
    └── Signatures/
        └── Index.jsx
```

---

# 18. Recommended Routes

```text
GET    /dashboard

GET    /documents
GET    /documents/create
POST   /documents
GET    /documents/{document}
GET    /documents/{document}/edit

POST   /documents/{document}/sign
GET    /documents/{document}/download
DELETE /documents/{document}

GET    /signatures
POST   /signatures
DELETE /signatures/{signature}
```

---

# 19. PDF Signing Process

## Complete Workflow

```text
1. User uploads PDF
        ↓
2. Laravel validates file
        ↓
3. File stored privately
        ↓
4. React opens PDF editor
        ↓
5. PDF.js renders document
        ↓
6. User selects signature
        ↓
7. react-rnd handles drag/resize
        ↓
8. App stores normalized placement
        ↓
9. User clicks Apply Signature
        ↓
10. Original PDF loaded
        ↓
11. Signature embedded using pdf-lib
        ↓
12. New signed PDF generated
        ↓
13. Signed PDF stored
        ↓
14. Audit log recorded
        ↓
15. User downloads final PDF
```

---

# 20. Recommended PDF Processing Strategy

## Option A — Client-side PDF Generation

React directly uses `pdf-lib`.

### Advantages

- Fast.
- Less server processing.
- Easier MVP.
- Signature coordinate handling is straightforward.

### Disadvantages

- Sensitive PDF contents exist in browser memory.
- Large PDFs may use significant client RAM.
- Audit controls are weaker.

## Option B — Server-side Processing

Frontend sends:

```json
{
  "document_id": 5,
  "placements": [
    {
      "signature_id": 2,
      "page": 1,
      "xRatio": 0.7,
      "yRatio": 0.8,
      "widthRatio": 0.2,
      "heightRatio": 0.07
    }
  ]
}
```

Server applies the signature and stores final result.

### Advantages

- Better auditability.
- Centralized document control.
- Better for company/internal use.

### Disadvantages

- More backend work.
- PHP PDF manipulation can be more complex.

## Recommended Initial Approach

For your project:

**Use PDF.js + react-rnd for the editor and pdf-lib for PDF modification.**

Laravel remains responsible for:

- Authentication.
- Permissions.
- File storage.
- Document records.
- Signature records.
- Audit logs.

For an internal system, the generated PDF can be uploaded back to Laravel after signing so that the system still retains the signed copy.

---

# 21. Optional PHP PDF Library

If later you want PDF manipulation to happen fully on the Laravel backend, consider:

## FPDI

Common packages include Setasign FPDI.

Purpose:

- Import existing PDF pages.
- Place additional graphics.
- Generate modified documents server-side.

However, for the first version, `pdf-lib` is simpler because the same browser that handles the drag coordinates can also apply the image.

---

# 22. Security Requirements

Signature images are sensitive assets.

Implement:

## Private Storage

Do not save signatures directly under:

```text
public/
```

Prefer:

```text
storage/app/private/signatures
```

Documents:

```text
storage/app/private/documents
```

Signed copies:

```text
storage/app/private/signed-documents
```

Files must only be accessed through authenticated Laravel routes/controllers.

---

# 23. Authorization

Always verify ownership.

Example rules:

- User can only see own documents.
- User can only use own signatures.
- Admin access depends on company policy.
- Prevent changing document ID in URL to access another person's PDF.

Use:

- Laravel Policies
- Middleware
- Authorization checks

---

# 24. File Security

Validate:

- MIME type.
- Extension.
- File size.
- PDF format.
- Image format.
- File ownership.

Generate random stored filenames.

Example:

```text
Original:
Contract-Juan-Dela-Cruz.pdf

Stored:
50f78c24-77dc-4ee2-a300-document.pdf
```

---

# 25. Signature Security

Recommended:

- Encrypt sensitive files at rest if required.
- Do not expose direct storage URLs.
- Record upload and usage.
- Provide delete signature function.
- Require authenticated access.
- Prevent users from selecting another user's signature.

---

# 26. Audit Trail

Every important event should be recorded.

Example:

```text
Document: Contract-001.pdf
User: Juan Dela Cruz

10:02 AM — Document uploaded
10:04 AM — Document opened
10:05 AM — Signature placed on Page 3
10:06 AM — Signed PDF generated
10:07 AM — Signed PDF downloaded
```

Useful for company use and troubleshooting.

---

# 27. User Interface Pages

## Page 1 — Login

Simple layout:

```text
PDF Signature System

Email
Password

[ Sign In ]
```

---

## Page 2 — Dashboard

Contains:

- Summary cards.
- Recent documents.
- Upload PDF button.

---

## Page 3 — Documents

Table:

```text
Document        Status     Uploaded       Action

Contract.pdf    Signed     Sep 18         View
Report.pdf      Pending    Sep 18         Sign
Agreement.pdf   Signed     Sep 17         Download
```

---

# 28. PDF Editor UI

Recommended layout:

```text
┌──────────────────────────────────────────────────────────────┐
│ ← Documents   Contract.pdf     80% - +      Save  Download  │
├─────────────┬───────────────────────────────────┬────────────┤
│ Pages       │                                   │ Tools      │
│             │                                   │            │
│ [1]         │            PDF                    │ Signature  │
│ [2]         │                                   │ Initials   │
│ [3]         │         [Signature]               │ Name       │
│ [4]         │                                   │ Date       │
│             │                                   │            │
│             │                                   │            │
└─────────────┴───────────────────────────────────┴────────────┘
```

---

# 29. Editor Features — MVP

Required:

- PDF preview.
- Page navigation.
- Zoom in.
- Zoom out.
- Upload signature.
- Select signature.
- Drag signature.
- Resize signature.
- Remove signature.
- Apply signature.
- Download PDF.

---

# 30. Editor Features — Phase 2

Add:

- Multiple signatures.
- Initials.
- Date.
- Printed name.
- Multiple page support.
- Undo.
- Redo.
- Reset.
- Keyboard delete.
- Duplicate signature.
- Page thumbnails.

---

# 31. Advanced Features — Future

## Reusable Signature Templates

Example:

A specific document format always requires signature at the same location.

Save:

```text
Template:
Purchase Approval Form

Signature:
Page 2
Bottom-right

Date:
Page 2
Below signature
```

Next time the same template is uploaded:

```text
[ Apply Saved Template ]
```

The system automatically positions the signature.

---

# 32. Signature Drawing

Future feature:

```text
Draw your signature

┌──────────────────────────────┐
│                              │
│       handwritten area       │
│                              │
└──────────────────────────────┘

[ Clear ] [ Save Signature ]
```

Possible library:

```bash
npm install signature_pad
```

---

# 33. Initials Support

User can save:

```text
Full Signature
Initials
```

Example:

```text
Signature:
Erwin Victorio

Initials:
EV
```

Both can be dragged independently.

---

# 34. Date Element

Add draggable date.

Example:

```text
September 18, 2026
```

Options:

- Current date.
- Manual date.
- Different date formats.

---

# 35. Printed Name

Allow draggable name field.

Example:

```text
Erwin Victorio
```

Can be automatically obtained from the user profile.

---

# 36. Auto Placement

Future feature:

For repeated document formats, store normalized coordinates.

Example:

```text
Form Type:
Approval Form

Signature:
Page: 3
X Ratio: 0.72
Y Ratio: 0.82
```

When same form is uploaded:

```text
Apply saved placement?
[ Apply ]
```

---

# 37. Document Versioning

Optional:

```text
Contract.pdf
├── Original
├── Signed Version 1
└── Signed Version 2
```

Never overwrite original documents.

Recommended:

```text
original.pdf
signed-v1.pdf
signed-v2.pdf
```

---

# 38. Download Naming

Original:

```text
Purchase-Order-001.pdf
```

Generated:

```text
Purchase-Order-001-signed.pdf
```

If repeated:

```text
Purchase-Order-001-signed-v2.pdf
```

---

# 39. Mobile / Responsive Support

Desktop should be the primary editing experience.

Tablet:

- Supported.
- Touch dragging.
- Signature resizing.

Mobile:

- View PDF.
- Sign basic documents.
- Use stacked toolbar.

However, complex PDF editing is easier on desktop/tablet.

---

# 40. Error Handling

Handle:

- Invalid PDF.
- Password-protected PDF.
- Corrupted PDF.
- Large file.
- Missing signature.
- Failed PDF rendering.
- Failed PDF save.
- Lost network connection.
- Upload failure.
- Unauthorized document access.

Use Sonner toast notifications.

Examples:

```text
PDF uploaded successfully.

Signature saved.

Signed PDF generated successfully.

Unable to open this PDF.

This PDF is password protected.
```

---

# 41. Loading States

Use loading indicators for:

```text
Uploading PDF...
Loading document...
Rendering page...
Generating signed PDF...
Saving document...
```

Disable buttons while processing.

---

# 42. Recommended Install Commands

Frontend libraries:

```bash
npm install pdfjs-dist pdf-lib react-rnd
```

Optional future drawing:

```bash
npm install signature_pad
```

If using shadcn already, continue using existing components for:

- Dialog
- Button
- Card
- Dropdown
- Tooltip
- Tabs
- Sheet
- Alert Dialog

---

# 43. MVP Scope

The first usable version should only contain:

## Authentication

- Login.
- Logout.

## Signature

- Upload signature.
- Save signature.
- Delete signature.

## Documents

- Upload PDF.
- List PDFs.
- Open PDF.
- Delete PDF.

## Editor

- Render PDF.
- Navigate pages.
- Drag signature.
- Resize signature.
- Remove signature.
- Apply signature.
- Download signed PDF.

## Audit

- Record upload.
- Record sign.
- Record download.

Do not initially add:

- AI.
- OCR.
- DOCX conversion.
- Workflow approvals.
- Email integration.
- Certificate-based digital signing.

Keep MVP focused.

---

# 44. Development Phases

## Phase 1 — Project Setup

Tasks:

- Laravel project setup.
- Inertia React setup.
- Authentication.
- shadcn/ui setup.
- Main application layout.
- Sidebar.
- Dashboard.

Deliverable:

Working authenticated application.

---

## Phase 2 — Document Management

Tasks:

- Documents migration.
- Document model.
- Upload PDF.
- File validation.
- Private storage.
- Documents table.
- Delete document.
- Download original document.

Deliverable:

Working PDF upload and document management.

---

## Phase 3 — Signature Management

Tasks:

- Signatures migration.
- Signature model.
- Upload signature.
- Preview signature.
- Set default signature.
- Delete signature.
- Private signature storage.

Deliverable:

Reusable signature library.

---

## Phase 4 — PDF Viewer

Tasks:

- Install PDF.js.
- Configure PDF worker.
- Render PDF.
- Render pages.
- Page navigation.
- Page thumbnails.
- Zoom controls.

Deliverable:

Complete PDF preview.

---

## Phase 5 — Signature Drag and Resize

Tasks:

- Install react-rnd.
- Add signature overlay.
- Enable dragging.
- Enable resizing.
- Restrict to page bounds.
- Track x/y.
- Track width/height.
- Normalize positions.

Deliverable:

User can visually position the signature.

---

## Phase 6 — PDF Generation

Tasks:

- Install pdf-lib.
- Load original PDF.
- Embed signature PNG.
- Convert coordinates.
- Draw signature.
- Generate signed PDF.
- Download result.

Deliverable:

Actual signed PDF file.

---

## Phase 7 — Save Signed Documents

Tasks:

- Upload generated signed PDF back to Laravel.
- Save signed file path.
- Update status.
- Preserve original document.
- Add signed timestamp.

Deliverable:

Original and signed versions retained.

---

## Phase 8 — Multiple Elements

Tasks:

- Multiple signatures.
- Initials.
- Date.
- Printed name.
- Delete element.
- Duplicate element.
- Selection state.

Deliverable:

More complete signing editor.

---

## Phase 9 — Undo / Redo

Track editor state.

Example:

```text
Action 1 — Add signature
Action 2 — Move signature
Action 3 — Resize signature
Action 4 — Add date
```

Allow:

```text
Undo
Redo
```

---

## Phase 10 — Security and Audit Logs

Tasks:

- Policies.
- Ownership validation.
- Audit log service.
- Secure download routes.
- Private signature files.
- Private PDF files.
- Activity history.

Deliverable:

Safer internal company deployment.

---

## Phase 11 — Templates

Tasks:

- Save document placement templates.
- Reuse normalized positions.
- Apply saved template.
- Template management.

Deliverable:

Faster repeated signing workflow.

---

# 45. Suggested Sidebar

```text
Dashboard

Documents
├── All Documents
└── Upload Document

Signatures

Templates

Activity Logs

Settings

Logout
```

For MVP:

```text
Dashboard
Documents
Signatures
Logout
```

---

# 46. Final Recommended Technical Setup

Use:

```text
Laravel 12
        +
Inertia React
        +
Tailwind CSS
        +
shadcn/ui
        +
PDF.js
        +
react-rnd
        +
pdf-lib
```

Responsibilities:

```text
Laravel
├── Authentication
├── Database
├── Authorization
├── File Storage
├── Document Records
├── Signature Records
└── Audit Logs

PDF.js
└── PDF preview/rendering

react-rnd
└── Signature dragging and resizing

pdf-lib
├── Load original PDF
├── Embed signature
├── Add text/date
└── Generate final PDF
```

---

# 47. Recommended MVP User Flow

```text
LOGIN
  ↓
DASHBOARD
  ↓
UPLOAD PDF
  ↓
PDF EDITOR
  ↓
SELECT SIGNATURE
  ↓
DRAG SIGNATURE
  ↓
RESIZE SIGNATURE
  ↓
PREVIEW
  ↓
APPLY SIGNATURE
  ↓
SAVE SIGNED COPY
  ↓
DOWNLOAD PDF
```

---

# 48. Important Legal / Business Note

This system should clearly distinguish between:

## Electronic Signature

Examples:

- Scanned handwritten signature.
- Uploaded PNG signature.
- Drawn signature.

This is what the proposed MVP primarily handles.

## Cryptographic Digital Signature

Uses:

- Digital certificates.
- Public/private keys.
- Certificate verification.
- Tamper detection.

This requires a different implementation and should be treated as a later phase if your organization specifically requires certificate-based signing or regulated signing workflows.

---

# 49. Success Criteria

The MVP is complete when a user can:

1. Login.
2. Upload a PDF.
3. Open it without printing.
4. Select a saved signature.
5. Drag the signature onto any page.
6. Resize it.
7. Place multiple signatures if needed.
8. Apply the placements to the real PDF.
9. Generate a new signed PDF.
10. Download the signed copy.
11. Keep the original document unchanged.
12. See a basic audit history.

---

# 50. Recommended First Implementation

Start only with:

```text
Upload PDF
        ↓
Preview PDF using PDF.js
        ↓
Upload transparent signature PNG
        ↓
Drag/resize using react-rnd
        ↓
Convert screen coordinates to PDF coordinates
        ↓
Embed image using pdf-lib
        ↓
Generate signed PDF
        ↓
Upload signed copy to Laravel
        ↓
Download
```

Once this works reliably, add:

```text
Initials
Date
Printed Name
Multiple Signatures
Undo / Redo
Templates
Audit History
Approval Workflow
```

This keeps the first version manageable while solving the actual problem immediately.
