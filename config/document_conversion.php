<?php

return [
    'binary' => env('LIBREOFFICE_BINARY', PHP_OS_FAMILY === 'Windows' ? 'C:\\Program Files\\LibreOffice\\program\\soffice.com' : '/usr/bin/libreoffice'),
    'timeout' => (int) env('DOCUMENT_CONVERSION_TIMEOUT', 60),
    'temporary_root' => env('DOCUMENT_CONVERSION_TEMP_ROOT', storage_path('app/conversion-tmp')),
    'connection' => env('DOCUMENT_CONVERSION_CONNECTION', 'conversion'),
    'queue' => 'conversions',
    'max_output_bytes' => 40 * 1024 * 1024,
];
