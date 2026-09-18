import { mkdirSync, existsSync, writeFileSync } from 'node:fs';
import { resolve } from 'node:path';
import { spawnSync, spawn } from 'node:child_process';
const root = resolve('storage/framework/browser-test');
mkdirSync(root, { recursive: true });
const database = resolve(root, 'database.sqlite');
if (!existsSync(database)) writeFileSync(database, '');
const env = { ...process.env, APP_ENV: 'testing', APP_URL: 'http://127.0.0.1:8123', DB_CONNECTION: 'sqlite', DB_DATABASE: database, DB_URL: '', SESSION_DRIVER: 'file', CACHE_STORE: 'array', SIGNING_PRIVATE_ROOT: resolve(root, 'private') };
for (const args of [['artisan', 'migrate', '--force'], ['tests/browser/seed.php']]) {
    const result = spawnSync('php', args, { env, stdio: 'inherit' });
    if (result.status !== 0) process.exit(result.status || 1);
}
const server = spawn('php', ['-d', 'upload_max_filesize=40M', '-d', 'post_max_size=48M', 'artisan', 'serve', '--host=127.0.0.1', '--port=8123', '--no-reload'], { env, stdio: 'inherit' });
process.on('SIGTERM', () => server.kill());
server.on('exit', code => process.exit(code || 0));
