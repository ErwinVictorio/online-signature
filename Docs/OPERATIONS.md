# Running Paperless Sign

## Local startup

1. Install dependencies with `composer install` and `npm ci`.
2. Configure the database in `.env`. The existing checkout uses SQLite; MySQL is also supported by the migrations. Do not change an existing database without migrating its data.
3. For a fresh installation only, copy `.env.example` to `.env` and run `php artisan key:generate`.
4. Run `php artisan migrate`.
5. Run `php artisan db:seed` to create the requested account: username `admin`, password `admin`. The seeder preserves an existing admin account. Login accepts username or email. For additional accounts, run `php artisan signing:create-user`; there is no public registration.
6. Run `npm run build`.
7. Serve `public/` through Apache, or use `php -d upload_max_filesize=40M -d post_max_size=48M -S 127.0.0.1:8000 -t public vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php`.

If Node is not on PATH on this Windows machine, it is installed at `C:\Program Files\nodejs`. Run its `npm.cmd` explicitly or add that directory to your terminal PATH.

## Upload limits

Original PDF/DOCX/DOC files: 20 MB. Editor PDFs: 1–500 pages; converted and signed PDFs: 40 MB. Signatures: PNG/JPG, 2 MB, 10–4000 pixels per side. There can be at most 200 elements per document/template.

PHP and the web server must allow uploads of at least 40 MB and requests of at least 48 MB. `public/.user.ini` supplies these limits for CGI/FastCGI; `public/.htaccess` supplies them for Apache's `php_module` when overrides are allowed, as in the local XAMPP configuration. Otherwise set them in the active web-server `php.ini` and restart the server. The application cannot change PHP's request-parsing limits after a request begins.

## Signing workflow

Upload a PDF, DOCX, or DOC. Word files enter a separate conversion queue; their details page refreshes progress automatically. Once ready, open the editor and confirm that you reviewed the converted layout before signing. Choose or upload a signature, place it on a page, and resize it using a corner. Add initials, names, or dates as needed. Save a draft to return later. Preview the generated PDF, then save and download it. For Google Docs, download a DOCX and upload it here.

Each signed save creates a separate version. Document details provide downloads for the immutable original, converted PDF (for Word), and all signed versions. The editor starts from the original PDF or immutable converted PDF with saved placements, so repeated signing does not double-stamp earlier signatures. Upload revised Word files as new documents; ready documents cannot be reconverted.

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
- `php scripts/check-word-conversion.php` (requires LibreOffice; creates DOCX/DOC/layout samples and validated PDFs under `storage/app/word-fixtures`)
- `php scripts/check-word-conversion.php path/to/sample.docx path/to/sample.doc` (inspect and convert additional local samples without modifying them)
- `npm run test:browser` (installed Chrome and configured LibreOffice; starts a separate conversion worker, SQLite database, and private storage under `storage/app/browser-test`)

## Library references

- [PDF.js rendering and viewport documentation](https://mozilla.github.io/pdf.js/examples/)
- [pdf-lib PDFPage API](https://pdf-lib.js.org/docs/api/classes/pdfpage)
- [react-rnd API](https://github.com/bokuweb/react-rnd)

## Word conversion setup

Install LibreOffice from its [official download page](https://www.libreoffice.org/download/). Its [command-line documentation](https://help.libreoffice.org/latest/en-US/text/shared/guide/start_parameters.html) describes headless conversion, output directories, and separate user profiles. Retain the distributed license and notices when redistributing its runtime.

Set `LIBREOFFICE_BINARY` to the full executable path (`C:/Program Files/LibreOffice/program/soffice.com` on a standard Windows installation, `/usr/bin/libreoffice` on Linux). The current local checkout uses an extracted LibreOffice 26.8.0.3 runtime at `storage/app/libreoffice/SourceDir/LibreOffice/program/soffice.com`; this ignored directory is machine-specific and is not shipped in Git. The official MSI checksum was verified by winget. Extraction with [lessmsi](https://github.com/activescott/lessmsi) avoided Windows Installer error 1618 without stopping unrelated installers.

Run `php artisan config:clear` after changing configuration, then `php scripts/check-word-conversion.php`. PHP requires ZIP, DOM/XML, fileinfo, and mbstring extensions. Install the fonts used by your documents only when you have appropriate licenses. Missing fonts are substituted and may change pagination. The missing-font/Unicode fixture and mixed portrait/landscape fixture demonstrate why the app requires review; they do not certify exact fidelity for all Word documents. Macros, embedded objects, external-content relationships, encrypted files, and nonstandard legacy variants can be rejected; export a clean DOCX/PDF when this happens.

Start the dedicated worker:

```sh
php artisan queue:work conversion --queue=conversions --sleep=1 --tries=2 --timeout=150 --memory=256
```

`composer run dev` also starts this conversion worker. A default-queue worker alone does not process `conversions`. Keep `DOCUMENT_CONVERSION_CONNECTION=conversion` unless an equivalent separate queue is configured. Start with one worker; worker process count is the concurrency limit. Each job has a unique temporary directory and LibreOffice profile. Conversion process timeout defaults to 60 seconds, is clamped to 5–120 seconds, worker timeout is 150 seconds, and queue reservation is 240 seconds. Do not lower the reservation below the worker timeout. On Windows, PHP does not support PCNTL worker alarms; the Symfony converter timeout still applies, and production supervision must also bound the whole worker process.

For a production deployment, run workers using a dedicated unprivileged account, permit only the required database/storage/runtime paths, deny outbound network access at the OS/firewall or container level, and cap worker CPU/memory/processes. The isolated profile and proxy settings are not a complete security sandbox. Use a Windows service supervisor or systemd/Supervisor on Linux to restart workers after crashes and boot; run `php artisan queue:restart` during deployments. These machine-wide account, firewall, and service policies have not been installed by the local feature implementation.

## Conversion recovery

- **Queued indefinitely:** start/check the dedicated worker and its database connection. The upload and original download remain available.
- **Conversion unavailable:** fix `LIBREOFFICE_BINARY`, verify the executable can run as the worker account, clear configuration, restart the worker, and use **Retry conversion** on document details.
- **Conversion failed:** the original is retained. Inspect `storage/logs/laravel.log` for diagnostics; the UI shows a sanitized message. Process timeouts receive one automatic retry after 15 seconds. The owner may retry failed conversions manually.
- **Worker interrupted:** a conversion that has remained `converting` for more than five minutes exposes manual retry. Attempt numbers prevent an older job from publishing over the retried result.
- **Temporary files:** normal success and failure clean their own job directory. A force-killed OS process can leave an orphan under `DOCUMENT_CONVERSION_TEMP_ROOT` (default `storage/app/conversion-tmp`). Stop the affected workers, verify no converter is using that generated directory, then remove only the confirmed orphan; never remove originals or published PDFs during cleanup.
- **Upload fails or JSON responses contain PHP notices:** verify `upload_tmp_dir` exists and is writable by the PHP account. Configure limits on the actual web-server PHP process. Parent `php -d ... artisan serve` options are not forwarded to the spawned server; configure `php.ini`, or start `php -d upload_max_filesize=40M -d post_max_size=48M -S 127.0.0.1:8000 -t public vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php` directly. Disable displayed PHP errors in production and retain logs.
