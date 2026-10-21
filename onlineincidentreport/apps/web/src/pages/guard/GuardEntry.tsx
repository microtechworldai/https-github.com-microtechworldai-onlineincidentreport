import React, { useEffect, useState } from 'react';
import { useParams, useNavigate } from 'react-router-dom';
import {
  Shield,
  MapPin,
  Lock,
  User,
  AlertCircle,
  ArrowRight,
  Loader2,
  QrCode,
  Camera,
  CheckCircle2,
  RotateCcw,
  Sparkles,
} from 'lucide-react';
import { extractSiteToken } from '@uss/shared';
import { guardApi } from '../../lib/api';
import { QrScannerModal } from '../../components/QrScannerModal';

export const GuardEntry: React.FC = () => {
  const { token } = useParams<{ token?: string }>();
  const navigate = useNavigate();

  const [siteToken, setSiteToken] = useState<string>(() => extractSiteToken(token || ''));
  const [site, setSite] = useState<any>(null);
  const [loadingSite, setLoadingSite] = useState<boolean>(false);

  const [username, setUsername] = useState<string>('');
  const [password, setPassword] = useState<string>('');
  const [submitting, setSubmitting] = useState<boolean>(false);
  const [error, setError] = useState<string | null>(null);

  // Scanner modal state
  const [isScannerOpen, setIsScannerOpen] = useState<boolean>(false);
  const [manualInputMode, setManualInputMode] = useState<boolean>(false);

  // Validate site token on load or change
  useEffect(() => {
    const clean = extractSiteToken(siteToken);
    if (clean && clean.length >= 8) {
      setLoadingSite(true);
      setError(null);
      guardApi
        .getSiteByToken(clean)
        .then((res) => {
          setSite(res.site);
        })
        .catch((err) => {
          setSite(null);
          setError(err.message || 'Invalid or expired site QR code');
        })
        .finally(() => setLoadingSite(false));
    } else {
      setSite(null);
    }
  }, [siteToken]);

  // Handle successful QR scan from mobile camera
  const handleQrScan = (scannedToken: string) => {
    const clean = extractSiteToken(scannedToken);
    setSiteToken(clean);
    setError(null);
  };

  const handleManualTokenChange = (val: string) => {
    const cleaned = extractSiteToken(val);
    setSiteToken(cleaned || val.trim());
  };

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    const cleanToken = extractSiteToken(siteToken);
    if (!cleanToken || !site) {
      setError('Please scan or enter a valid Site QR Code.');
      return;
    }
    if (!username.trim() || !password) {
      setError('Please enter your guard username and password.');
      return;
    }

    setSubmitting(true);
    setError(null);

    try {
      await guardApi.login({
        siteToken: cleanToken,
        username: username.trim(),
        password,
      });

      // Navigate to Incident Submission Form
      navigate('/g/submit');
    } catch (err: any) {
      setError(err.message || 'Login failed. Please check your credentials.');
    } finally {
      setSubmitting(false);
    }
  };

  return (
    <div className="min-h-screen bg-slate-900 flex flex-col justify-center items-center p-4">
      {/* Mobile QR Camera Scanner Modal */}
      <QrScannerModal
        isOpen={isScannerOpen}
        onClose={() => setIsScannerOpen(false)}
        onScan={handleQrScan}
      />

      <div className="w-full max-w-md bg-white rounded-3xl shadow-2xl overflow-hidden border border-slate-100">
        {/* Brand Header */}
        <div className="bg-[#0F2042] p-6 text-white text-center relative">
          <div className="w-14 h-14 mx-auto rounded-2xl bg-white p-1.5 flex items-center justify-center shadow-lg mb-3 overflow-hidden">
            <img
              src="/assets/uss-logo.png"
              alt="USS Logo"
              className="w-full h-full object-contain"
            />
          </div>
          <h2 className="text-xl font-extrabold tracking-wide uppercase">
            Ultimate Security Services
          </h2>
          <p className="text-xs text-amber-400 font-medium tracking-wider uppercase mt-0.5">
            Dubai, United Arab Emirates
          </p>
          <div className="mt-3 inline-block px-3.5 py-1 bg-white/10 rounded-full text-xs font-semibold tracking-wide text-slate-200">
            Security Patrol Portal
          </div>
        </div>

        {/* Form Container */}
        <div className="p-6">
          {/* Site Verification Section */}
          {loadingSite ? (
            <div className="flex items-center justify-center gap-2 p-4 bg-blue-50 border border-blue-200 rounded-2xl mb-5 text-sm text-blue-800">
              <Loader2 className="w-4 h-4 animate-spin text-blue-600" />
              <span className="font-semibold text-xs">Verifying Site QR Token...</span>
            </div>
          ) : site ? (
            <div className="p-4 bg-emerald-50/80 border border-emerald-200 rounded-2xl mb-5 flex items-start justify-between gap-3 shadow-xs">
              <div className="flex items-start gap-3 min-w-0">
                <div className="p-2 bg-emerald-600 text-white rounded-xl shrink-0 mt-0.5 shadow-sm">
                  <CheckCircle2 className="w-5 h-5" />
                </div>
                <div className="min-w-0">
                  <span className="text-[10px] font-bold tracking-wider text-emerald-800 uppercase block">
                    Assigned Site Verified
                  </span>
                  <h3 className="font-extrabold text-slate-900 text-sm truncate">
                    {site.name}
                  </h3>
                  <p className="text-xs text-slate-600 truncate mt-0.5">
                    {site.address}
                  </p>
                </div>
              </div>
              <button
                type="button"
                onClick={() => {
                  setSite(null);
                  setSiteToken('');
                  setManualInputMode(true);
                }}
                title="Change or Rescan Site"
                className="p-2 text-slate-400 hover:text-slate-700 hover:bg-white rounded-xl transition-colors shrink-0"
              >
                <RotateCcw className="w-4 h-4" />
              </button>
            </div>
          ) : (
            <div className="mb-5 space-y-3">
              {/* Primary Mobile Camera Scanner Button */}
              <button
                type="button"
                onClick={() => setIsScannerOpen(true)}
                className="w-full py-3.5 px-4 bg-gradient-to-r from-blue-700 via-indigo-700 to-blue-800 hover:from-blue-800 hover:to-indigo-900 active:scale-[0.99] text-white font-bold rounded-2xl shadow-md transition-all flex items-center justify-center gap-2.5 text-sm border border-blue-500/30"
              >
                <Camera className="w-5 h-5 text-amber-300" />
                <span>Scan Site QR Code with Camera</span>
              </button>

              {/* Manual Entry Collapsible Toggle */}
              <div className="pt-1">
                {!manualInputMode ? (
                  <button
                    type="button"
                    onClick={() => setManualInputMode(true)}
                    className="w-full text-center text-xs text-slate-500 hover:text-blue-600 transition-colors font-medium flex items-center justify-center gap-1.5 py-1"
                  >
                    <QrCode className="w-3.5 h-3.5" />
                    <span>Or type/paste site code manually</span>
                  </button>
                ) : (
                  <div className="space-y-2 p-3 bg-slate-50 rounded-2xl border border-slate-200">
                    <div className="flex items-center justify-between">
                      <label className="block text-[11px] font-bold text-slate-700 uppercase tracking-wider">
                        Site Token or URL
                      </label>
                      <button
                        type="button"
                        onClick={() => setIsScannerOpen(true)}
                        className="text-[11px] font-bold text-blue-600 hover:text-blue-800 flex items-center gap-1"
                      >
                        <Camera className="w-3 h-3" />
                        <span>Open Camera</span>
                      </button>
                    </div>
                    <div className="relative">
                      <input
                        type="text"
                        value={siteToken}
                        onChange={(e) => handleManualTokenChange(e.target.value)}
                        placeholder="Paste site QR token or URL"
                        className="w-full pl-9 pr-3 py-2.5 text-xs border border-slate-300 rounded-xl focus:ring-2 focus:ring-blue-600 focus:outline-none bg-white font-mono"
                      />
                      <QrCode className="w-4 h-4 text-slate-400 absolute left-3 top-3" />
                    </div>
                    <p className="text-[10px] text-slate-500">
                      Tip: Paste full URL or 32-character token (e.g.{' '}
                      <code className="text-slate-700 font-mono">marina7a9b...</code>)
                    </p>
                  </div>
                )}
              </div>
            </div>
          )}

          {error && (
            <div className="mb-4 p-3.5 bg-red-50 text-red-700 rounded-2xl text-xs border border-red-200 flex items-start gap-2.5">
              <AlertCircle className="w-4 h-4 shrink-0 mt-0.5" />
              <div className="flex-1">
                <span>{error}</span>
                {!site && (
                  <button
                    type="button"
                    onClick={() => setIsScannerOpen(true)}
                    className="block font-bold text-red-800 underline mt-1"
                  >
                    Open Camera Scanner
                  </button>
                )}
              </div>
            </div>
          )}

          {/* Login Form */}
          <form onSubmit={handleSubmit} className="space-y-4">
            <div>
              <label className="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">
                Guard Username / Staff ID
              </label>
              <div className="relative">
                <input
                  type="text"
                  required
                  autoCapitalize="none"
                  value={username}
                  onChange={(e) => setUsername(e.target.value)}
                  placeholder="e.g. tariq.m or rajesh.k"
                  className="w-full pl-10 pr-3 py-3 text-sm border border-slate-300 rounded-xl focus:ring-2 focus:ring-blue-600 focus:outline-none"
                />
                <User className="w-5 h-5 text-slate-400 absolute left-3 top-3.5" />
              </div>
            </div>

            <div>
              <label className="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">
                Guard Password
              </label>
              <div className="relative">
                <input
                  type="password"
                  required
                  value={password}
                  onChange={(e) => setPassword(e.target.value)}
                  placeholder="Enter your security password"
                  className="w-full pl-10 pr-3 py-3 text-sm border border-slate-300 rounded-xl focus:ring-2 focus:ring-blue-600 focus:outline-none"
                />
                <Lock className="w-5 h-5 text-slate-400 absolute left-3 top-3.5" />
              </div>
            </div>

            <button
              type="submit"
              disabled={submitting || !site}
              className="w-full py-3.5 bg-[#0F2042] hover:bg-[#152a55] active:scale-[0.99] disabled:opacity-50 text-white font-bold rounded-2xl shadow-md transition-all flex items-center justify-center gap-2 touch-btn mt-2"
            >
              {submitting ? (
                <>
                  <Loader2 className="w-5 h-5 animate-spin" />
                  <span>Signing In...</span>
                </>
              ) : (
                <>
                  <span>Open Incident Form</span>
                  <ArrowRight className="w-5 h-5" />
                </>
              )}
            </button>
          </form>
        </div>
      </div>
    </div>
  );
};
