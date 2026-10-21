import dotenv from 'dotenv';
import path from 'path';
import os from 'os';

// Load .env from root or apps/api
dotenv.config({ path: path.resolve(process.cwd(), '.env') });
dotenv.config();

export function getNetworkIp(): string {
  const interfaces = os.networkInterfaces();
  for (const name of Object.keys(interfaces)) {
    for (const iface of interfaces[name] || []) {
      if (iface.family === 'IPv4' && !iface.internal && iface.address !== '127.0.0.1') {
        return iface.address;
      }
    }
  }
  return 'localhost';
}

export function getAllNetworkIps(): string[] {
  const interfaces = os.networkInterfaces();
  const ips: string[] = [];
  for (const name of Object.keys(interfaces)) {
    for (const iface of interfaces[name] || []) {
      if (iface.family === 'IPv4' && !iface.internal && iface.address !== '127.0.0.1') {
        ips.push(iface.address);
      }
    }
  }
  return ips;
}

const cleanRawDomain = (process.env.APP_DOMAIN || '').trim().replace(/^https?:\/\//, '').replace(/\/+$/, '');
const localIp = getNetworkIp();
const resolvedDomain = (!cleanRawDomain || cleanRawDomain.startsWith('localhost'))
  ? (localIp !== 'localhost' ? `${localIp}:5173` : 'localhost:5173')
  : cleanRawDomain;

export const projectRoot = process.cwd().replace(/[\\/]apps[\\/]api$/, '');

export const config = {
  env: process.env.NODE_ENV || 'development',
  port: parseInt(process.env.PORT || '4000', 10),
  appDomain: resolvedDomain,
  corsOrigin: process.env.CORS_ORIGIN || `http://${localIp}:5173`,

  superadmin: {
    username: process.env.SUPERADMIN_USERNAME || 'superadmin',
    password: process.env.SUPERADMIN_PASSWORD || 'ChangeMeNow123!',
    name: process.env.SUPERADMIN_NAME || 'USS Operations Director',
  },

  jwt: {
    adminSecret:
      process.env.JWT_ADMIN_SECRET || 'uss_admin_jwt_secret_dev_key_321',
    guardSecret:
      process.env.JWT_GUARD_SECRET || 'uss_guard_jwt_secret_dev_key_123',
    adminExpiresIn: '8h',
    guardExpiresIn: '2h', // Max 2h, idle 30m
  },

  database: {
    url: process.env.DATABASE_URL || 'sqlite:./data/uss_incidents.db',
  },

  storage: {
    driver: (process.env.STORAGE_DRIVER || 'local') as 'local' | 's3',
    localUploadDir: path.resolve(
      projectRoot,
      process.env.LOCAL_UPLOAD_DIR || './uploads'
    ),
    s3: {
      endpoint: process.env.S3_ENDPOINT,
      region: process.env.S3_REGION || 'auto',
      bucket: process.env.S3_BUCKET || 'uss-incident-reports',
      accessKeyId: process.env.S3_ACCESS_KEY_ID,
      secretAccessKey: process.env.S3_SECRET_ACCESS_KEY,
      publicBaseUrl: process.env.S3_PUBLIC_BASE_URL,
    },
  },

  timezone: process.env.TZ || 'Asia/Dubai',
};
