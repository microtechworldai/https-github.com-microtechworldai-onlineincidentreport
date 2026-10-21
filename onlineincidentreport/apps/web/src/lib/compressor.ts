/**
 * Compresses an image file on the client side:
 * - Resizes so maximum long edge is 1600px
 * - Re-encodes at 80% JPEG quality
 * - Returns base64 data and compressed size
 */
export async function compressImage(
  file: File,
  maxDimension = 1600,
  quality = 0.8
): Promise<{
  dataBase64: string;
  originalFilename: string;
  mimeType: string;
  size: number;
}> {
  return new Promise((resolve, reject) => {
    const reader = new FileReader();
    reader.onerror = () => reject(new Error('Failed to read image file'));
    reader.onload = () => {
      const img = new Image();
      img.onerror = () => reject(new Error('Failed to decode image data'));
      img.onload = () => {
        let width = img.width;
        let height = img.height;

        if (width > maxDimension || height > maxDimension) {
          if (width > height) {
            height = Math.round((height * maxDimension) / width);
            width = maxDimension;
          } else {
            width = Math.round((width * maxDimension) / height);
            height = maxDimension;
          }
        }

        const canvas = document.createElement('canvas');
        canvas.width = width;
        canvas.height = height;

        const ctx = canvas.getContext('2d');
        if (!ctx) {
          return reject(new Error('Canvas 2D context unavailable'));
        }

        // Draw and compress to JPEG 80%
        ctx.drawImage(img, 0, 0, width, height);
        const dataBase64 = canvas.toDataURL('image/jpeg', quality);

        // Approximate size in bytes from base64 string
        const size = Math.round((dataBase64.length * 3) / 4);

        resolve({
          dataBase64,
          originalFilename: file.name.replace(/\.[^/.]+$/, '') + '.jpg',
          mimeType: 'image/jpeg',
          size,
        });
      };
      img.src = reader.result as string;
    };
    reader.readAsDataURL(file);
  });
}
