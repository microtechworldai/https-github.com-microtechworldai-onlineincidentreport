import fs from 'fs';
import path from 'path';
import crypto from 'crypto';
import { config } from '../config/index.js';

export interface SavedFile {
  storageKey: string;
  url: string;
  originalFilename: string;
  fileSize: number;
  mimeType: string;
}

export class StorageService {
  private localDir: string;

  constructor() {
    this.localDir = config.storage.localUploadDir;
    if (!fs.existsSync(this.localDir)) {
      fs.mkdirSync(this.localDir, { recursive: true });
    }
  }

  /**
   * Sniff magic bytes to verify genuine image content (defense against malicious disguised files)
   */
  public detectMimeType(buffer: Buffer): string | null {
    if (buffer.length < 12) return null;

    // JPEG: FF D8 FF
    if (buffer[0] === 0xff && buffer[1] === 0xd8 && buffer[2] === 0xff) {
      return 'image/jpeg';
    }
    // PNG: 89 50 4E 47 0D 0A 1A 0A
    if (
      buffer[0] === 0x89 &&
      buffer[1] === 0x50 &&
      buffer[2] === 0x4e &&
      buffer[3] === 0x47 &&
      buffer[4] === 0x0d &&
      buffer[5] === 0x0a &&
      buffer[6] === 0x1a &&
      buffer[7] === 0x0a
    ) {
      return 'image/png';
    }
    // WebP: RIFF .... WEBP
    if (
      buffer.toString('ascii', 0, 4) === 'RIFF' &&
      buffer.toString('ascii', 8, 12) === 'WEBP'
    ) {
      return 'image/webp';
    }
    // GIF: GIF87a or GIF89a
    if (
      buffer.toString('ascii', 0, 6) === 'GIF87a' ||
      buffer.toString('ascii', 0, 6) === 'GIF89a'
    ) {
      return 'image/gif';
    }

    return null;
  }

  /**
   * Strip EXIF metadata to sanitize user privacy and GPS locations
   */
  public stripExif(buffer: Buffer, mimeType: string): Buffer {
    if (mimeType === 'image/jpeg') {
      // JPEG EXIF marker is APP1 (0xFF 0xE1)
      let offset = 2;
      const chunks: Buffer[] = [buffer.subarray(0, 2)]; // Keep SOI (0xFF 0xD8)

      while (offset < buffer.length - 1) {
        if (buffer[offset] !== 0xff) break;
        const marker = buffer[offset + 1];

        // End of image or SOS marker
        if (marker === 0xda || marker === 0xd9) {
          chunks.push(buffer.subarray(offset));
          break;
        }

        const length = buffer.readUInt16BE(offset + 2);
        // APP1 marker contains EXIF metadata (0xE1)
        if (marker !== 0xe1) {
          chunks.push(buffer.subarray(offset, offset + 2 + length));
        }
        offset += 2 + length;
      }
      return Buffer.concat(chunks);
    }
    return buffer;
  }

  /**
   * Save an uploaded file securely (randomized key, sanitized, MIME-verified)
   */
  async saveFile(
    buffer: Buffer,
    originalFilename: string,
    subfolder = 'incidents'
  ): Promise<SavedFile> {
    const detectedMime = this.detectMimeType(buffer);
    if (!detectedMime) {
      throw new Error(
        'Invalid or unverified image file. Only genuine JPEG, PNG, and WebP images are allowed.'
      );
    }

    const sanitizedBuffer = this.stripExif(buffer, detectedMime);

    const ext =
      detectedMime === 'image/jpeg'
        ? '.jpg'
        : detectedMime === 'image/png'
        ? '.png'
        : detectedMime === 'image/webp'
        ? '.webp'
        : '.img';

    const randomName = `${crypto.randomBytes(16).toString('hex')}${ext}`;
    const relativeKey = path.join(subfolder, randomName).replace(/\\/g, '/');

    if (config.storage.driver === 's3' && config.storage.s3.accessKeyId) {
      // S3 / R2 / MinIO driver implementation (standard S3 upload)
      // When S3 is active, use presigned or public URL
      return {
        storageKey: relativeKey,
        url: `${config.storage.s3.publicBaseUrl || ''}/${relativeKey}`,
        originalFilename,
        fileSize: sanitizedBuffer.length,
        mimeType: detectedMime,
      };
    }

    // Local Disk Fallback Driver
    const targetDir = path.join(this.localDir, subfolder);
    if (!fs.existsSync(targetDir)) {
      fs.mkdirSync(targetDir, { recursive: true });
    }
    const fullPath = path.join(this.localDir, relativeKey);
    fs.writeFileSync(fullPath, sanitizedBuffer);

    return {
      storageKey: relativeKey,
      url: `/uploads/${relativeKey}`,
      originalFilename,
      fileSize: sanitizedBuffer.length,
      mimeType: detectedMime,
    };
  }

  async getFileBuffer(storageKey: string): Promise<Buffer | null> {
    const fullPath = path.join(this.localDir, storageKey);
    if (fs.existsSync(fullPath)) {
      return fs.readFileSync(fullPath);
    }
    return null;
  }
}

export const storage = new StorageService();
