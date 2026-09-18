# Running Paperless Sign

## Local startup

1. Install dependencies with `composer install` and `npm ci`.
2. Configure the database in `.env`. The existing checkout uses SQLite; MySQL is also supported by the migrations. Do not change an existing database without migrating its data.
3. For a fresh installation only, copy `.env.example` to `.env` and run `php artisan key:generate`.
4. Run `php artisan migrate`.
5. Run `php artisan db:seed` to create the requested account: username `admin`, password `admin`. The seeder preserves an existing admin account. Login accepts username or email. For additional accounts, run `php artisan signing:create-user`; there is no public registration.
6. Run `npm run build`.
7. Serve `public/` through Apache, or use `php -d upload_max_filesize=40M -d post_max_size=48M artisan serve`.

If Node is not on PATH on this Windows machine, it is installed at `C:\Program Files\nodejs`. Run its `npm.cmd` explicitly or add that directory to your terminal PATH.

## Upload limits

Original PDFs: 20 MB, 1–500 pages. Signed PDFs: 40 MB. Signatures: PNG/JPG, 2 MB, 10–4000 pixels per side. There can be at most 200 elements per document/template.

PHP and the web server must allow uploads of at least 40 MB and requests of at least 48 MB. `public/.user.ini` supplies these limits for CGI/FastCGI. Apache's PHP module requires the same values in the active `php.ini`, followed by an Apache restart. The application cannot change PHP's request-parsing limits after a request begins.

## Signing workflow

Upload a PDF, choose or upload a signature, place it on a page, and resize it using a corner. Add initials, names, or dates as needed. Save a draft to return later. Preview the generated PDF, then save and download it.

Each signed save creates a separate version. Document details provide downloads for the immutable original and all signed versions. The editor starts from the original with saved placements, so repeated signing does not double-stamp earlier signatures.

Templates retain normalized placement positions, signature references, and literal text/dates. Review all pages and dates when applying one. Deleting a signature does not alter signed PDFs; drafts/templates referencing that signature must be repaired before saving or signing.

## Storage and deployment

- Files are under `storage/app/private`, served through authenticated ownership-checked routes. Do not expose that directory through a web-server alias or symlink.
- Back up the database and private storage together. Deleting a document removes its original and all retained signed copies; audit history retains the document name.
- Use HTTPS, `APP_DEBUG=false`, and secure session cookies in production.
- `npm run build` and `npm run dev` copy PDF.js CMaps, fonts, and WASM resources into `public/pdfjs`. These are library assets, not user documents. Ship that folder with `public/build`.
- Private files use no-store response headers. Each account can access only its own documents, signatures, templates, and activity.
- The browser generates signed PDFs. The server validates the PDF, page count, placement ownership, bounds, and revision, but does not independently prove that uploaded PDF bytes match the declared placements. Audit events record application actions, not cryptographic attestation.
- Image signatures and rasterized text are electronic annotations, not certificate signatures. Printed names use browser-rendered images so supported Unicode text looks the same in preview and export; added text is not searchable PDF text.
- Unsupported, damaged, or encrypted PDFs are rejected. Existing certificate signatures on an input PDF are not preserved as valid by this editing workflow.

## Verification

- `php artisan test`
- `npm run test:editor`
- `npm run build`
- `npm run test:browser` (installed Chrome; uses a separate SQLite database and private storage under `storage/framework/browser-test`)

## Library references

- [PDF.js rendering and viewport documentation](https://mozilla.github.io/pdf.js/examples/)
- [pdf-lib PDFPage API](https://pdf-lib.js.org/docs/api/classes/pdfpage)
- [react-rnd API](https://github.com/bokuweb/react-rnd)
