import fs from 'fs';
import path from 'path';

const dataDir = path.resolve(process.cwd(), 'data');
if (!fs.existsSync(dataDir)) {
  fs.mkdirSync(dataDir, { recursive: true });
}
const tunnelFile = path.resolve(dataDir, 'active_tunnel.json');

let currentTunnel = null;
let heartbeatInterval = null;

function getTargetFiles() {
  return [
    tunnelFile,
    path.resolve(process.cwd(), 'data', 'active_tunnel.json'),
    path.resolve(process.cwd(), '..', 'data', 'active_tunnel.json'),
    path.resolve(process.cwd(), '..', 'onlineincidentreport', 'data', 'active_tunnel.json'),
    path.resolve(process.cwd(), '..', 'hostinger_deployment', 'data', 'active_tunnel.json'),
    path.resolve(process.cwd(), 'hostinger_deployment', 'data', 'active_tunnel.json')
  ];
}

function cleanup() {
  if (heartbeatInterval) {
    clearInterval(heartbeatInterval);
    heartbeatInterval = null;
  }
  const payload = JSON.stringify(
    { active: false, url: null, updatedAt: new Date().toISOString() },
    null,
    2
  );
  for (const tf of getTargetFiles()) {
    try {
      if (fs.existsSync(tf)) {
        fs.writeFileSync(tf, payload);
      }
    } catch (_) {}
  }
}

process.on('SIGINT', () => {
  cleanup();
  process.exit(0);
});

process.on('SIGTERM', () => {
  cleanup();
  process.exit(0);
});

process.on('exit', () => {
  cleanup();
});

async function createCloudflareTunnel() {
  try {
    cleanup();
    const { startTunnel } = await import('untun');
    console.log('[TUNNEL] Initializing high-speed Cloudflare Edge Tunnel...');
    const tunnel = await startTunnel({ url: 'http://localhost:8080' });
    const url = await tunnel.getURL();

    if (!url) {
      throw new Error('Cloudflare tunnel URL empty');
    }

    let failedPings = 0;
    const healthCheckInterval = setInterval(async () => {
      try {
        const res = await fetch(url + '/g/index.php', {
          method: 'GET',
          signal: AbortSignal.timeout(12000),
        });
        if (res.ok || res.status < 500) {
          failedPings = 0;
        } else {
          failedPings++;
        }
      } catch (err) {
        failedPings++;
      }

      // Only reconnect if truly dead for 6 consecutive checks (3 minutes)
      if (failedPings >= 6) {
        console.warn(`[TUNNEL] Health check failed 6 times for ${url}. Reconnecting fresh tunnel...`);
        clearInterval(healthCheckInterval);
        cleanup();
        try {
          if (currentTunnel && currentTunnel.close) await currentTunnel.close();
        } catch (_) {}
        setTimeout(createCloudflareTunnel, 3000);
      }
    }, 30000);

    const writeState = () => {
      const payload = {
        url,
        provider: 'cloudflare',
        pid: process.pid,
        active: true,
        updatedAt: new Date().toISOString(),
        heartbeat: Date.now(),
      };
      const jsonStr = JSON.stringify(payload, null, 2);
      
      for (const tf of getTargetFiles()) {
        try {
          const dir = path.dirname(tf);
          if (!fs.existsSync(dir)) fs.mkdirSync(dir, { recursive: true });
          fs.writeFileSync(tf, jsonStr);
        } catch (_) {}
      }
    };

    writeState();
    if (heartbeatInterval) clearInterval(heartbeatInterval);
    heartbeatInterval = setInterval(writeState, 5000);

    console.log('\n=============================================================');
    console.log('✅ HIGH-SPEED CLOUDFLARE PUBLIC TUNNEL ACTIVE');
    console.log('🌐 WORKS ON ANY PHONE, 4G, 5G & ANY WI-FI WORLDWIDE (NO PASSWORD)');
    console.log('🔗 PUBLIC LINK: ' + url);
    console.log('=============================================================\n');

    return tunnel;
  } catch (err) {
    console.warn('[TUNNEL] Cloudflare tunnel failed, falling back to localtunnel:', err.message);
    return createLocaltunnel();
  }
}

async function createLocaltunnel() {
  try {
    cleanup();
    const localtunnel = (await import('localtunnel')).default;
    const tunnel = await localtunnel({ port: 8080 });

    currentTunnel = tunnel;

    const writeState = () => {
      const payload = {
        url: tunnel.url,
        provider: 'localtunnel',
        pid: process.pid,
        active: true,
        updatedAt: new Date().toISOString(),
        heartbeat: Date.now(),
      };
      fs.writeFileSync(tunnelFile, JSON.stringify(payload, null, 2));
    };

    writeState();
    if (heartbeatInterval) clearInterval(heartbeatInterval);
    heartbeatInterval = setInterval(writeState, 5000);

    console.log('\n=============================================================');
    console.log('✅ BACKUP TUNNEL ACTIVE: ' + tunnel.url);
    console.log('=============================================================\n');

    tunnel.on('close', () => {
      console.log('[TUNNEL] Closed. Reconnecting in 3s...');
      cleanup();
      setTimeout(createCloudflareTunnel, 3000);
    });

    tunnel.on('error', (err) => {
      console.error('[TUNNEL] Error:', err.message);
      try { tunnel.close(); } catch (_) {}
      cleanup();
      setTimeout(createCloudflareTunnel, 3000);
    });

    return tunnel;
  } catch (err) {
    console.error('[TUNNEL] All tunnels failed, retrying in 5s...', err.message);
    cleanup();
    setTimeout(createCloudflareTunnel, 5000);
  }
}

createCloudflareTunnel();

