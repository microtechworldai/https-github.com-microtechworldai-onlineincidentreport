import crypto from 'crypto';

/**
 * Hash a password using scrypt (memory-hard, resistant to hardware brute-force)
 */
export async function hashPassword(password: string): Promise<string> {
  return new Promise((resolve, reject) => {
    const salt = crypto.randomBytes(16).toString('hex');
    crypto.scrypt(password, salt, 64, { N: 16384, r: 8, p: 1 }, (err, derivedKey) => {
      if (err) reject(err);
      resolve(`${salt}:${derivedKey.toString('hex')}`);
    });
  });
}

/**
 * Verify a plain password against the stored scrypt hash
 */
export async function verifyPassword(
  password: string,
  storedHash: string
): Promise<boolean> {
  return new Promise((resolve) => {
    if (!storedHash || !storedHash.includes(':')) {
      return resolve(false);
    }
    const [salt, key] = storedHash.split(':');
    crypto.scrypt(password, salt, 64, { N: 16384, r: 8, p: 1 }, (err, derivedKey) => {
      if (err) return resolve(false);
      try {
        const keyBuffer = Buffer.from(key, 'hex');
        const match = crypto.timingSafeEqual(derivedKey, keyBuffer);
        resolve(match);
      } catch {
        resolve(false);
      }
    });
  });
}

/**
 * Generate a 128-bit cryptographically secure random token for Site QR codes
 */
export function generateSiteQrToken(): string {
  return crypto.randomBytes(16).toString('hex'); // 32 characters hex = 128 bits
}

/**
 * Generate a random UUID v4
 */
export function generateUuid(): string {
  return crypto.randomUUID();
}
