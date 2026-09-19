import { mkdirSync, existsSync, writeFileSync } from 'node:fs';
import { resolve } from 'node:path';
import { spawnSync, spawn } from 'node:child_process';
const root = resolve('storage/app/browser-test');
mkdirSync(root, { recursive: true });
const database = resolve(root, 'database.sqlite');
const uploads = resolve(root, 'uploads');
mkdirSync(uploads, { recursive: true });
if (!existsSync(database)) writeFileSync(database, '');
const env = { ...process.env, APP_ENV: 'testing', APP_URL: 'http://127.0.0.1:8123', DB_CONNECTION: 'sqlite', DB_DATABASE: database, DB_URL: '', SESSION_DRIVER: 'file', CACHE_STORE: 'array', SIGNING_PRIVATE_ROOT: resolve(root, 'private'), DOCUMENT_CONVERSION_CONNECTION: 'conversion', DOCUMENT_CONVERSION_TEMP_ROOT: resolve(root, 'conversion-tmp') };
for (const args of [['artisan', 'migrate', '--force'], ['tests/browser/seed.php']]) {
    const result = spawnSync('php', args, { env, stdio: 'inherit' });
    if (result.status !== 0) process.exit(result.status || 1);
}
// Start PHP directly: artisan serve does not forward its parent's -d options.
const server = spawn('php', ['-d', `upload_tmp_dir=${uploads}`, '-d', 'upload_max_filesize=40M', '-d', 'post_max_size=48M', '-S', '127.0.0.1:8123', resolve('vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php')], { env, cwd: resolve('public'), stdio: 'inherit' });
const worker = spawn('php', ['artisan', 'queue:work', 'conversion', '--queue=conversions', '--sleep=1', '--tries=2', '--timeout=150'], { env, stdio: 'inherit' });
const stop = () => { worker.kill(); server.kill(); };
process.on('SIGTERM', stop);
process.on('SIGINT', stop);
process.on('exit', stop);
server.on('exit', code => process.exit(code || 0));
