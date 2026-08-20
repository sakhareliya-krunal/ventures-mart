import net from 'node:net';
import { spawn } from 'node:child_process';
import { fileURLToPath } from 'node:url';

const host = '127.0.0.1';
const laravelPort = 8000;
const vitePort = 5173;
const viteBin = fileURLToPath(new URL('../node_modules/vite/bin/vite.js', import.meta.url));

const processes = [];
let shuttingDown = false;

function isPortAvailable(port) {
    return new Promise((resolve) => {
        const server = net.createServer();

        server.once('error', () => resolve(false));
        server.once('listening', () => {
            server.close(() => resolve(true));
        });

        server.listen(port, host);
    });
}

async function assertPortAvailable(name, port) {
    if (await isPortAvailable(port)) {
        return;
    }

    console.error(`${name} port ${port} is already in use.`);
    console.error('Stop the existing dev server first, then run npm run dev again.');
    process.exitCode = 1;
    throw new Error(`Port ${port} is unavailable`);
}

function startProcess(name, command, args) {
    const child = spawn(command, args, {
        cwd: process.cwd(),
        env: process.env,
        stdio: 'inherit',
        windowsHide: true,
    });

    child.name = name;
    processes.push(child);

    child.on('error', (error) => {
        console.error(`${name} failed to start: ${error.message}`);
        shutdown(1);
    });

    child.on('exit', (code, signal) => {
        if (shuttingDown) {
            return;
        }

        if (signal) {
            console.error(`${name} stopped by ${signal}.`);
        } else {
            console.error(`${name} exited with code ${code ?? 0}.`);
        }

        shutdown(code ?? 1);
    });

    return child;
}

function killProcessTree(child) {
    if (!child.pid || child.exitCode !== null || child.signalCode !== null) {
        return;
    }

    if (process.platform === 'win32') {
        spawn('taskkill', ['/pid', String(child.pid), '/t', '/f'], {
            stdio: 'ignore',
            windowsHide: true,
        });
        return;
    }

    child.kill('SIGTERM');
}

function shutdown(code = 0) {
    if (shuttingDown) {
        return;
    }

    shuttingDown = true;

    for (const child of processes) {
        killProcessTree(child);
    }

    setTimeout(() => {
        process.exit(code);
    }, 300);
}

process.on('SIGINT', () => shutdown(0));
process.on('SIGTERM', () => shutdown(0));
process.on('SIGHUP', () => shutdown(0));

try {
    await assertPortAvailable('Laravel', laravelPort);
    await assertPortAvailable('Vite', vitePort);
} catch {
    process.exit(1);
}

startProcess('Laravel', 'php', [
    'artisan',
    'serve',
    `--host=${host}`,
    `--port=${laravelPort}`,
]);

startProcess('Vite', process.execPath, [
    viteBin,
    '--host',
    host,
    '--port',
    String(vitePort),
    '--strictPort',
]);
