import React, { useRef, useState } from 'react';
import { Camera, Image as ImageIcon, X, AlertCircle, Loader2 } from 'lucide-react';
import { compressImage } from '../lib/compressor';

export interface PhotoItem {
  id: string;
  dataBase64: string;
  originalFilename: string;
  mimeType: string;
  size: number;
  caption: string;
}

interface PhotoUploaderProps {
  photos: PhotoItem[];
  onChange: (photos: PhotoItem[]) => void;
  maxPhotos?: number;
}

export const PhotoUploader: React.FC<PhotoUploaderProps> = ({
  photos,
  onChange,
  maxPhotos = 10,
}) => {
  const cameraInputRef = useRef<HTMLInputElement>(null);
  const galleryInputRef = useRef<HTMLInputElement>(null);
  const [compressing, setCompressing] = useState<boolean>(false);
  const [error, setError] = useState<string | null>(null);

  const handleFiles = async (fileList: FileList | null) => {
    if (!fileList || fileList.length === 0) return;
    setError(null);

    if (photos.length + fileList.length > maxPhotos) {
      setError(`Maximum ${maxPhotos} photos permitted. Please remove some first.`);
      return;
    }

    setCompressing(true);
    const newPhotos: PhotoItem[] = [];

    for (let i = 0; i < fileList.length; i++) {
      const file = fileList[i];
      try {
        const compressed = await compressImage(file, 1600, 0.8);
        newPhotos.push({
          id: Math.random().toString(36).substring(2, 9),
          dataBase64: compressed.dataBase64,
          originalFilename: compressed.originalFilename,
          mimeType: compressed.mimeType,
          size: compressed.size,
          caption: '',
        });
      } catch (err: any) {
        setError(`Failed to process photo "${file.name}": ${err.message}`);
      }
    }

    setCompressing(false);
    onChange([...photos, ...newPhotos]);
  };

  const removePhoto = (index: number) => {
    const updated = photos.filter((_, i) => i !== index);
    onChange(updated);
  };

  const updateCaption = (index: number, caption: string) => {
    const updated = photos.map((p, i) => (i === index ? { ...p, caption } : p));
    onChange(updated);
  };

  return (
    <div className="space-y-3">
      {/* Hidden file inputs for Camera vs Gallery */}
      <input
        ref={cameraInputRef}
        type="file"
        accept="image/*"
        capture="environment"
        className="hidden"
        onChange={(e) => {
          handleFiles(e.target.files);
          e.target.value = '';
        }}
      />
      <input
        ref={galleryInputRef}
        type="file"
        accept="image/*"
        multiple
        className="hidden"
        onChange={(e) => {
          handleFiles(e.target.files);
          e.target.value = '';
        }}
      />

      {/* Action Buttons for Mobile Security Guards */}
      <div className="grid grid-cols-2 gap-3">
        <button
          type="button"
          onClick={() => cameraInputRef.current?.click()}
          disabled={compressing || photos.length >= maxPhotos}
          className="flex items-center justify-center gap-2 px-4 py-3 bg-blue-600 hover:bg-blue-700 active:bg-blue-800 disabled:opacity-50 text-white font-semibold rounded-xl shadow-sm transition-all text-sm touch-btn"
        >
          <Camera className="w-5 h-5 text-blue-100" />
          <span>Take Camera Photo</span>
        </button>

        <button
          type="button"
          onClick={() => galleryInputRef.current?.click()}
          disabled={compressing || photos.length >= maxPhotos}
          className="flex items-center justify-center gap-2 px-4 py-3 bg-slate-100 hover:bg-slate-200 active:bg-slate-300 disabled:opacity-50 text-slate-800 font-semibold rounded-xl border border-slate-300 transition-all text-sm touch-btn"
        >
          <ImageIcon className="w-5 h-5 text-slate-600" />
          <span>Upload From Gallery</span>
        </button>
      </div>

      <div className="flex items-center justify-between text-xs text-slate-500 font-medium px-1">
        <span>Attached: {photos.length} / {maxPhotos} photos (auto-compressed &lt;1600px)</span>
        {compressing && (
          <span className="flex items-center gap-1.5 text-blue-600 font-semibold">
            <Loader2 className="w-3.5 h-3.5 animate-spin" /> Compressing...
          </span>
        )}
      </div>

      {error && (
        <div className="flex items-center gap-2 p-3 bg-red-50 text-red-700 rounded-xl text-xs border border-red-200">
          <AlertCircle className="w-4 h-4 shrink-0" />
          <span>{error}</span>
        </div>
      )}

      {/* Photo Cards Grid */}
      {photos.length > 0 && (
        <div className="grid grid-cols-2 sm:grid-cols-3 gap-3 pt-2">
          {photos.map((photo, idx) => (
            <div
              key={photo.id}
              className="relative bg-white border border-slate-200 rounded-xl p-2 shadow-sm space-y-2 group"
            >
              <div className="relative aspect-video rounded-lg overflow-hidden bg-slate-100">
                <img
                  src={photo.dataBase64}
                  alt={photo.originalFilename}
                  className="w-full h-full object-cover"
                />
                <button
                  type="button"
                  onClick={() => removePhoto(idx)}
                  className="absolute top-1 right-1 p-1 bg-black/60 hover:bg-red-600 text-white rounded-full transition-colors"
                  title="Remove photo"
                >
                  <X className="w-3.5 h-3.5" />
                </button>
                <div className="absolute bottom-1 left-1 bg-black/60 text-white text-[10px] px-1.5 py-0.5 rounded font-mono">
                  {(photo.size / 1024).toFixed(0)} KB
                </div>
              </div>

              <input
                type="text"
                value={photo.caption}
                placeholder="Optional caption..."
                onChange={(e) => updateCaption(idx, e.target.value)}
                className="w-full text-xs px-2 py-1 border border-slate-200 rounded-md focus:outline-none focus:ring-1 focus:ring-blue-500 placeholder:text-slate-400"
              />
            </div>
          ))}
        </div>
      )}
    </div>
  );
};
