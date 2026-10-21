import React, { useEffect, useRef, useState, useCallback } from 'react';
import {
  Camera,
  X,
  Flashlight,
  FlashlightOff,
  SwitchCamera,
  Upload,
  AlertCircle,
  Loader2,
  CheckCircle2,
  Sparkles,
} from 'lucide-react';
import jsQR from 'jsqr';
import { extractSiteToken } from '@uss/shared';

interface QrScannerModalProps {
  isOpen: boolean;
  onClose: () => void;
  onScan: (token: string, rawData: string) => void;
}

export const QrScannerModal: React.FC<QrScannerModalProps> = ({
  isOpen,
  onClose,
  onScan,
}) => {
  const videoRef = useRef<HTMLVideoElement>(null);
  const canvasRef = useRef<HTMLCanvasElement>(null);
  const fileInputRef = useRef<HTMLInputElement>(null);

  const [stream, setStream] = useState<MediaStream | null>(null);
  const [cameraError, setCameraError] = useState<string | null>(null);
  const [isInitializing, setIsInitializing] = useState<boolean>(true);
  const [torchAvailable, setTorchAvailable] = useState<boolean>(false);
  const [torchOn, setTorchOn] = useState<boolean>(false);
  const [availableCameras, setAvailableCameras] = useState<MediaDeviceInfo[]>([]);
  const [activeCameraIndex, setActiveCameraIndex] = useState<number>(0);
  const [hasScanned, setHasScanned] = useState<boolean>(false);
  const [detectedToken, setDetectedToken] = useState<string | null>(null);

  const animationFrameId = useRef<number | null>(null);
  const isScanningRef = useRef<boolean>(false);

  // Sound feedback on successful scan using Web Audio API
  const playBeep = () => {
    try {
      const AudioCtx = window.AudioContext || (window as any).webkitAudioContext;
      if (!AudioCtx) return;
      const ctx = new AudioCtx();
      const osc = ctx.createOscillator();
      const gain = ctx.createGain();

      osc.type = 'sine';
      osc.frequency.setValueAtTime(880, ctx.currentTime); // A5 note
      osc.frequency.exponentialRampToValueAtTime(1320, ctx.currentTime + 0.12);

      gain.gain.setValueAtTime(0.2, ctx.currentTime);
      gain.gain.exponentialRampToValueAtTime(0.01, ctx.currentTime + 0.15);

      osc.connect(gain);
      gain.connect(ctx.destination);

      osc.start();
      osc.stop(ctx.currentTime + 0.15);
    } catch {
      // AudioContext may be restricted by autoplay policy
    }
  };

  const triggerHaptic = () => {
    try {
      if (typeof navigator !== 'undefined' && navigator.vibrate) {
        navigator.vibrate([40, 30, 40]);
      }
    } catch {
      // vibration not supported
    }
  };

  // Stop camera stream tracks
  const stopStream = useCallback(() => {
    if (animationFrameId.current) {
      cancelAnimationFrame(animationFrameId.current);
      animationFrameId.current = null;
    }
    isScanningRef.current = false;

    if (stream) {
      stream.getTracks().forEach((track) => {
        try {
          track.stop();
        } catch {
          // ignore track stop error
        }
      });
      setStream(null);
    }
  }, [stream]);

  // Handle successful detection
  const handleSuccess = useCallback(
    (rawData: string) => {
      if (isScanningRef.current) return;
      isScanningRef.current = true;

      const token = extractSiteToken(rawData);
      if (!token) return;

      setHasScanned(true);
      setDetectedToken(token);
      playBeep();
      triggerHaptic();

      // Small delay for user to see the success confirmation overlay
      setTimeout(() => {
        stopStream();
        onScan(token, rawData);
        onClose();
      }, 600);
    },
    [onScan, onClose, stopStream]
  );

  // Native BarcodeDetector or jsQR frame processing
  const scanFrame = useCallback(() => {
    if (!isOpen || isScanningRef.current || hasScanned) return;

    const video = videoRef.current;
    if (!video || video.readyState !== video.HAVE_ENOUGH_DATA) {
      animationFrameId.current = requestAnimationFrame(scanFrame);
      return;
    }

    const videoWidth = video.videoWidth;
    const videoHeight = video.videoHeight;

    if (videoWidth === 0 || videoHeight === 0) {
      animationFrameId.current = requestAnimationFrame(scanFrame);
      return;
    }

    // Try Native BarcodeDetector if supported
    if ('BarcodeDetector' in window) {
      try {
        const barcodeDetector = new (window as any).BarcodeDetector({
          formats: ['qr_code'],
        });
        barcodeDetector
          .detect(video)
          .then((barcodes: any[]) => {
            if (barcodes && barcodes.length > 0 && barcodes[0].rawValue) {
              handleSuccess(barcodes[0].rawValue);
              return;
            }
            if (!isScanningRef.current) {
              animationFrameId.current = requestAnimationFrame(scanFrame);
            }
          })
          .catch(() => {
            // Fallback to jsQR on native detector failure
            fallbackJsQR(video, videoWidth, videoHeight);
          });
        return;
      } catch {
        // Fall back to jsQR
      }
    }

    // Fallback using jsQR canvas sampling
    fallbackJsQR(video, videoWidth, videoHeight);
  }, [isOpen, hasScanned, handleSuccess]);

  const fallbackJsQR = (
    video: HTMLVideoElement,
    width: number,
    height: number
  ) => {
    const canvas = canvasRef.current;
    if (!canvas) {
      animationFrameId.current = requestAnimationFrame(scanFrame);
      return;
    }

    canvas.width = width;
    canvas.height = height;
    const ctx = canvas.getContext('2d', { willReadFrequently: true });
    if (!ctx) {
      animationFrameId.current = requestAnimationFrame(scanFrame);
      return;
    }

    ctx.drawImage(video, 0, 0, width, height);

    try {
      const imageData = ctx.getImageData(0, 0, width, height);
      const code = jsQR(imageData.data, imageData.width, imageData.height, {
        inversionAttempts: 'dontInvert',
      });

      if (code && code.data && code.data.trim()) {
        handleSuccess(code.data.trim());
        return;
      }
    } catch {
      // Ignore scan frame read errors
    }

    if (!isScanningRef.current) {
      animationFrameId.current = requestAnimationFrame(scanFrame);
    }
  };

  // Enumerate cameras
  const getCameraDevices = async () => {
    try {
      if (!navigator.mediaDevices?.enumerateDevices) return [];
      const devices = await navigator.mediaDevices.enumerateDevices();
      return devices.filter((d) => d.kind === 'videoinput');
    } catch {
      return [];
    }
  };

  // Start Camera Stream
  const startCamera = async (deviceId?: string) => {
    setIsInitializing(true);
    setCameraError(null);
    setHasScanned(false);
    setDetectedToken(null);
    isScanningRef.current = false;

    if (!navigator.mediaDevices?.getUserMedia) {
      setCameraError(
        'Live camera access is not supported on this browser or requires a secure HTTPS connection. You can take or upload a photo of the QR code below.'
      );
      setIsInitializing(false);
      return;
    }

    try {
      // Stop any existing stream
      if (stream) {
        stream.getTracks().forEach((t) => t.stop());
      }

      const constraints: MediaStreamConstraints = {
        audio: false,
        video: deviceId
          ? { deviceId: { exact: deviceId } }
          : {
              facingMode: { ideal: 'environment' },
              width: { ideal: 1280 },
              height: { ideal: 720 },
            },
      };

      const mediaStream = await navigator.mediaDevices.getUserMedia(constraints);
      setStream(mediaStream);

      if (videoRef.current) {
        videoRef.current.srcObject = mediaStream;
        await videoRef.current.play();
      }

      // Check torch capability
      const videoTrack = mediaStream.getVideoTracks()[0];
      if (videoTrack) {
        const capabilities: any = videoTrack.getCapabilities?.() || {};
        setTorchAvailable(Boolean(capabilities.torch));
      }

      // Refresh camera devices list
      const devices = await getCameraDevices();
      setAvailableCameras(devices);

      setIsInitializing(false);
      animationFrameId.current = requestAnimationFrame(scanFrame);
    } catch (err: any) {
      console.warn('Camera stream error:', err);
      setIsInitializing(false);
      if (err.name === 'NotAllowedError' || err.name === 'PermissionDeniedError') {
        setCameraError(
          'Camera permission was denied. Please allow camera access in your mobile browser settings, or use the photo upload option below.'
        );
      } else if (err.name === 'NotFoundError' || err.name === 'DevicesNotFoundError') {
        setCameraError('No camera found on this device. You can upload an image of the QR poster.');
      } else {
        setCameraError(
          err.message || 'Unable to access device camera. Please check browser permissions or upload a QR image.'
        );
      }
    }
  };

  // Toggle Torch
  const toggleTorch = async () => {
    if (!stream) return;
    const videoTrack = stream.getVideoTracks()[0];
    if (!videoTrack) return;

    try {
      const nextTorch = !torchOn;
      await (videoTrack as any).applyConstraints({
        advanced: [{ torch: nextTorch }],
      });
      setTorchOn(nextTorch);
    } catch (e) {
      console.warn('Failed to toggle torch', e);
    }
  };

  // Switch between front/back or other cameras
  const handleSwitchCamera = () => {
    if (availableCameras.length <= 1) return;
    const nextIndex = (activeCameraIndex + 1) % availableCameras.length;
    setActiveCameraIndex(nextIndex);
    const nextDevice = availableCameras[nextIndex];
    startCamera(nextDevice.deviceId);
  };

  // Process uploaded or snapped image file
  const handleFileUpload = (e: React.ChangeEvent<HTMLInputElement>) => {
    const files = e.target.files;
    if (!files || files.length === 0) return;

    const file = files[0];
    const reader = new FileReader();

    reader.onload = (event) => {
      const img = new Image();
      img.onload = () => {
        const canvas = canvasRef.current || document.createElement('canvas');
        canvas.width = img.width;
        canvas.height = img.height;
        const ctx = canvas.getContext('2d', { willReadFrequently: true });
        if (!ctx) return;

        ctx.drawImage(img, 0, 0);

        try {
          const imageData = ctx.getImageData(0, 0, img.width, img.height);
          const code = jsQR(imageData.data, imageData.width, imageData.height);

          if (code && code.data && code.data.trim()) {
            handleSuccess(code.data.trim());
          } else {
            alert('No valid QR code detected in the uploaded photo. Please try a clearer, well-lit picture.');
          }
        } catch (err: any) {
          alert('Failed to decode image: ' + err.message);
        }
      };
      img.src = event.target?.result as string;
    };
    reader.readAsDataURL(file);
    e.target.value = '';
  };

  // Lifecycle when modal opens/closes
  useEffect(() => {
    if (isOpen) {
      startCamera();
    } else {
      stopStream();
    }
    return () => {
      stopStream();
    };
  }, [isOpen]);

  if (!isOpen) return null;

  return (
    <div className="fixed inset-0 z-50 bg-black/85 backdrop-blur-md flex flex-col justify-between p-4 animate-in fade-in duration-200">
      {/* Hidden processing canvas */}
      <canvas ref={canvasRef} className="hidden" />

      {/* Hidden file input for photo upload fallback */}
      <input
        ref={fileInputRef}
        type="file"
        accept="image/*"
        capture="environment"
        className="hidden"
        onChange={handleFileUpload}
      />

      {/* Top Controls Bar */}
      <div className="w-full max-w-md mx-auto flex items-center justify-between text-white py-2 px-1">
        <div className="flex items-center gap-2">
          <div className="w-8 h-8 rounded-xl bg-blue-600/80 flex items-center justify-center shadow-sm">
            <Camera className="w-4 h-4 text-white" />
          </div>
          <div>
            <h3 className="font-extrabold text-sm leading-tight tracking-wide">
              Scan Site QR Code
            </h3>
            <p className="text-[11px] text-slate-300 font-medium">
              Ultimate Security Mobile Patrol
            </p>
          </div>
        </div>

        <button
          onClick={() => {
            stopStream();
            onClose();
          }}
          className="p-2.5 rounded-full bg-white/10 hover:bg-white/20 active:bg-white/30 text-white transition-colors"
          title="Close scanner"
        >
          <X className="w-5 h-5" />
        </button>
      </div>

      {/* Center Viewfinder Container */}
      <div className="flex-1 flex flex-col items-center justify-center my-2 max-w-md w-full mx-auto relative">
        <div className="relative w-full aspect-square max-w-[320px] rounded-3xl overflow-hidden bg-black shadow-2xl border border-slate-700/80">
          {/* Live Video Stream */}
          <video
            ref={videoRef}
            playsInline
            muted
            autoPlay
            className="w-full h-full object-cover"
          />

          {/* Initializing / Loading Spinner */}
          {isInitializing && (
            <div className="absolute inset-0 bg-slate-900/90 flex flex-col items-center justify-center gap-3 text-white">
              <Loader2 className="w-8 h-8 animate-spin text-amber-400" />
              <span className="text-xs font-semibold">Starting camera...</span>
            </div>
          )}

          {/* Error Message Screen */}
          {cameraError && (
            <div className="absolute inset-0 bg-slate-900/95 flex flex-col items-center justify-center p-6 text-center text-white space-y-3">
              <div className="w-12 h-12 rounded-2xl bg-red-500/20 text-red-400 flex items-center justify-center">
                <AlertCircle className="w-6 h-6" />
              </div>
              <p className="text-xs text-slate-300 leading-relaxed font-medium">
                {cameraError}
              </p>
              <div className="flex flex-col gap-2 w-full pt-2">
                <button
                  onClick={() => fileInputRef.current?.click()}
                  className="w-full py-2.5 bg-blue-600 hover:bg-blue-700 text-white font-bold rounded-xl text-xs flex items-center justify-center gap-2 shadow-md"
                >
                  <Upload className="w-4 h-4" />
                  <span>Snap or Upload QR Photo</span>
                </button>
                <button
                  onClick={() => startCamera()}
                  className="w-full py-2 bg-white/10 hover:bg-white/20 text-white font-medium rounded-xl text-xs"
                >
                  Retry Camera Access
                </button>
              </div>
            </div>
          )}

          {/* Viewfinder Target Framing & Reticle */}
          {!cameraError && !isInitializing && (
            <div className="absolute inset-0 pointer-events-none flex flex-col items-center justify-center p-6">
              {/* Target Bounding Box */}
              <div className="relative w-48 h-48 border-2 border-amber-400/80 rounded-2xl shadow-[0_0_20px_rgba(251,191,36,0.35)]">
                {/* 4 Corner Accents */}
                <span className="absolute -top-1 -left-1 w-5 h-5 border-t-4 border-l-4 border-amber-400 rounded-tl-lg"></span>
                <span className="absolute -top-1 -right-1 w-5 h-5 border-t-4 border-r-4 border-amber-400 rounded-tr-lg"></span>
                <span className="absolute -bottom-1 -left-1 w-5 h-5 border-b-4 border-l-4 border-amber-400 rounded-bl-lg"></span>
                <span className="absolute -bottom-1 -right-1 w-5 h-5 border-b-4 border-r-4 border-amber-400 rounded-br-lg"></span>

                {/* Animated Pulsing Laser Scan Line */}
                {!hasScanned && (
                  <div className="absolute inset-x-2 top-0 h-0.5 bg-gradient-to-r from-transparent via-amber-400 to-transparent shadow-[0_0_12px_#fbbf24] animate-[scan_2s_ease-in-out_infinite]" />
                )}
              </div>

              {/* Success Overlay Flash */}
              {hasScanned && (
                <div className="absolute inset-0 bg-emerald-600/90 flex flex-col items-center justify-center text-white space-y-2 animate-in zoom-in-95 duration-200 pointer-events-auto">
                  <div className="w-14 h-14 rounded-full bg-white text-emerald-600 flex items-center justify-center shadow-lg">
                    <CheckCircle2 className="w-8 h-8 stroke-[2.5]" />
                  </div>
                  <h4 className="font-extrabold text-base">QR Code Detected!</h4>
                  <p className="text-xs text-emerald-100 font-mono">
                    Token: {detectedToken?.slice(0, 16)}...
                  </p>
                </div>
              )}
            </div>
          )}
        </div>

        {/* Guidance Notice */}
        <p className="text-xs text-slate-300 font-medium text-center mt-3 max-w-[280px]">
          Align the physical USS QR Checkpoint Poster within the frame.
        </p>
      </div>

      {/* Bottom Action Controls Bar */}
      <div className="w-full max-w-md mx-auto space-y-3 pb-3">
        <div className="flex items-center justify-center gap-3">
          {/* Torch/Flashlight Button (if supported) */}
          {torchAvailable && (
            <button
              onClick={toggleTorch}
              className={`p-3.5 rounded-2xl flex items-center justify-center gap-2 text-xs font-bold transition-all shadow-md ${
                torchOn
                  ? 'bg-amber-400 text-slate-900 shadow-amber-400/30'
                  : 'bg-white/10 hover:bg-white/20 text-white'
              }`}
            >
              {torchOn ? (
                <>
                  <FlashlightOff className="w-4 h-4" />
                  <span>Flashlight ON</span>
                </>
              ) : (
                <>
                  <Flashlight className="w-4 h-4" />
                  <span>Flashlight</span>
                </>
              )}
            </button>
          )}

          {/* Switch Camera Button (if multiple available) */}
          {availableCameras.length > 1 && (
            <button
              onClick={handleSwitchCamera}
              className="p-3.5 rounded-2xl bg-white/10 hover:bg-white/20 text-white flex items-center justify-center gap-2 text-xs font-bold transition-all shadow-md"
            >
              <SwitchCamera className="w-4 h-4" />
              <span>Flip Camera</span>
            </button>
          )}

          {/* Snap/Upload Photo Button */}
          <button
            onClick={() => fileInputRef.current?.click()}
            className="flex-1 py-3 px-4 rounded-2xl bg-white/15 hover:bg-white/25 active:bg-white/30 text-white flex items-center justify-center gap-2 text-xs font-bold transition-all shadow-md border border-white/10"
          >
            <Upload className="w-4 h-4 text-amber-400" />
            <span>Upload / Snap Photo</span>
          </button>
        </div>

        {/* Cancel Button */}
        <button
          onClick={() => {
            stopStream();
            onClose();
          }}
          className="w-full py-2.5 text-center text-xs text-slate-400 hover:text-white transition-colors"
        >
          Cancel & Enter Token Manually
        </button>
      </div>

      {/* Inline styles for custom scan animation if needed */}
      <style>{`
        @keyframes scan {
          0% { top: 4%; opacity: 0.8; }
          50% { top: 94%; opacity: 1; }
          100% { top: 4%; opacity: 0.8; }
        }
      `}</style>
    </div>
  );
};
