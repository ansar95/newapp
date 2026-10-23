const outDir = new URL('./img/', import.meta.url);
const base = 'http://localhost:8000';

const tabs = await fetch('http://127.0.0.1:9333/json').then((r) => r.json());
const page = tabs.find((tab) => tab.type === 'page') ?? tabs[0];
const ws = new WebSocket(page.webSocketDebuggerUrl);

await new Promise((resolve, reject) => {
    ws.addEventListener('open', resolve);
    ws.addEventListener('error', () => reject(new Error('WebSocket gagal.')));
});

let id = 0;
const pending = new Map();
ws.addEventListener('message', (event) => {
    const message = JSON.parse(event.data);
    if (message.id && pending.has(message.id)) {
        pending.get(message.id)(message);
        pending.delete(message.id);
    }
});

function cdp(method, params = {}, timeout = 20000) {
    const messageId = ++id;
    return new Promise((resolve, reject) => {
        const timer = setTimeout(() => reject(new Error(`Timeout ${method}`)), timeout);
        pending.set(messageId, (message) => {
            clearTimeout(timer);
            if (message.error) {
                reject(new Error(JSON.stringify(message.error)));
                return;
            }
            resolve(message.result ?? {});
        });
        ws.send(JSON.stringify({ id: messageId, method, params }));
    });
}

async function js(expression) {
    const result = await cdp('Runtime.evaluate', { expression, returnByValue: true, awaitPromise: true });
    return result.result?.value;
}

async function open(url) {
    await cdp('Page.navigate', { url });
    const deadline = Date.now() + 15000;
    let ready = '';
    while (ready !== 'complete' && Date.now() < deadline) {
        await new Promise((resolve) => setTimeout(resolve, 250));
        ready = await js('document.readyState');
    }
    await new Promise((resolve) => setTimeout(resolve, 400));
}

async function shot(name) {
    const result = await cdp('Page.captureScreenshot', { format: 'jpeg', quality: 72 });
    const file = new URL(name, outDir);
    await import('fs').then((fs) => fs.writeFileSync(file, Buffer.from(result.data, 'base64')));
    console.log(name);
}

await cdp('Page.enable');
await cdp('Emulation.setDeviceMetricsOverride', { width: 1440, height: 900, deviceScaleFactor: 1, mobile: false });

await open(`${base}/login`);
await shot('01-login.jpg');
await js(`(() => { const f = document.querySelector('form'); f.querySelector('input[name=email]').value = 'superadmin@example.com'; f.querySelector('input[type=password]').value = 'password'; f.requestSubmit(); return 'ok'; })()`);

const loginDeadline = Date.now() + 15000;
while (!(await js('location.pathname')).includes('dashboard') && Date.now() < loginDeadline) {
    await new Promise((resolve) => setTimeout(resolve, 300));
}

const pages = {
    '02-dashboard.jpg': '/dashboard',
    '03-projects.jpg': '/projects',
    '04-project.jpg': '/projects/2',
    '05-boq.jpg': '/projects/2/boq',
    '06-payment.jpg': '/projects/2/payment',
    '07-progress.jpg': '/projects/2/progress',
    '08-pengadaan.jpg': '/projects/2/procurement',
    '09-penagihan.jpg': '/projects/2/billing',
    '10-manpower.jpg': '/projects/2/man-power',
    '11-laporan.jpg': '/projects/2/reports',
    '12-assets.jpg': '/assets',
    '13-master-manpower.jpg': '/man-powers',
    '14-reports.jpg': '/reports',
    '15-users.jpg': '/users',
    '16-approval.jpg': '/boq-approvals',
};

for (const [name, path] of Object.entries(pages)) {
    await open(base + path);
    await shot(name);
}

ws.close();
