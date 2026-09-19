<?php

namespace App\Services;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;

class WordConverter
{
    public function available(): bool
    {
        return is_file(config('document_conversion.binary'));
    }

    /** The callback must consume the PDF before temporary files are removed. */
    public function convert(string $source, string $format, callable $consume): mixed
    {
        if (! $this->available()) {
            throw new \RuntimeException('LibreOffice is not configured. Ask the administrator to install it and set LIBREOFFICE_BINARY, then retry.');
        }
        if (! in_array($format, ['doc', 'docx'], true)) {
            throw new \InvalidArgumentException('Unsupported Word format.');
        }
        $root = config('document_conversion.temporary_root');
        File::ensureDirectoryExists($root);
        $directory = $root.DIRECTORY_SEPARATOR.Str::uuid();
        try {
            File::ensureDirectoryExists($directory.'/profile/user');
            File::ensureDirectoryExists($directory.'/output');
            File::copy($source, $directory.'/source.'.$format);
            File::put($directory.'/profile/user/registrymodifications.xcu', $this->profile());
            $profile = str_replace('\\', '/', realpath($directory.'/profile'));
            $profileUri = 'file://'.(str_starts_with($profile, '/') ? '' : '/').implode('/', array_map('rawurlencode', explode('/', $profile)));
            // Preserve the Windows drive separator in the file URI.
            $profileUri = str_replace('%3A', ':', $profileUri);
            $process = new Process([
                config('document_conversion.binary'), '-env:UserInstallation='.$profileUri,
                '--headless', '--nologo', '--nodefault', '--norestore', '--convert-to', 'pdf:writer_pdf_Export',
                '--outdir', $directory.'/output', $directory.'/source.'.$format,
            ], $directory, ['SAL_DISABLE_OPENCL' => '1']);
            $process->setTimeout(max(5, min(120, config('document_conversion.timeout'))));
            $process->mustRun();
            $output = $directory.'/output/source.pdf';
            if (! is_file($output) || filesize($output) < 1 || filesize($output) > config('document_conversion.max_output_bytes')) {
                throw new \RuntimeException('Conversion did not produce a supported PDF.');
            }

            return $consume($output);
        } finally {
            // Only this generated per-job directory can be removed.
            File::deleteDirectory($directory);
        }
    }

    public function profile(): string
    {
        return <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<oor:items xmlns:oor="http://openoffice.org/2001/registry">
  <item oor:path="/org.openoffice.Office.Common/Security/Scripting">
    <prop oor:name="DisableMacrosExecution" oor:op="fuse"><value>true</value></prop>
    <prop oor:name="DisableActiveContent" oor:op="fuse"><value>true</value></prop>
    <prop oor:name="DisableOLEAutomation" oor:op="fuse"><value>true</value></prop>
    <prop oor:name="MacroSecurityLevel" oor:op="fuse"><value>3</value></prop>
    <prop oor:name="BlockUntrustedRefererLinks" oor:op="fuse"><value>true</value></prop>
  </item>
  <item oor:path="/org.openoffice.Office.Writer/Content/Update">
    <prop oor:name="Link" oor:op="fuse"><value>2</value></prop>
  </item>
  <item oor:path="/org.openoffice.Inet/Settings">
    <prop oor:name="ooInetProxyType" oor:op="fuse"><value>2</value></prop>
    <prop oor:name="ooInetHTTPProxyName" oor:op="fuse"><value>127.0.0.1</value></prop>
    <prop oor:name="ooInetHTTPProxyPort" oor:op="fuse"><value>9</value></prop>
    <prop oor:name="ooInetHTTPSProxyName" oor:op="fuse"><value>127.0.0.1</value></prop>
    <prop oor:name="ooInetHTTPSProxyPort" oor:op="fuse"><value>9</value></prop>
    <prop oor:name="ooInetNoProxy" oor:op="fuse"><value></value></prop>
  </item>
</oor:items>
XML;
    }
}
