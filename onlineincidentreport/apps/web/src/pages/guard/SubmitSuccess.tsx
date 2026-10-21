import React, { useEffect, useState } from 'react';
import { useLocation, useNavigate, Link } from 'react-router-dom';
import { CheckCircle2, ShieldCheck, ArrowRight, QrCode, Lock, LogOut, PlusCircle, Check } from 'lucide-react';
import { guardApi } from '../../lib/api';

export const SubmitSuccess: React.FC = () => {
  const location = useLocation();
  const navigate = useNavigate();
  const [loggingOut, setLoggingOut] = useState(false);
  const { refNumber, siteName } = (location.state as any) || {
    refNumber: 'INC-RECORDED',
    siteName: 'Assigned Site',
  };

  // Ensure ALL drafts and session tokens are completely obliterated upon arriving at success
  useEffect(() => {
    try {
      sessionStorage.removeItem('uss_guard_token');
      for (let i = localStorage.length - 1; i >= 0; i--) {
        const k = localStorage.key(i);
        if (k && k.startsWith('uss_draft_')) {
          localStorage.removeItem(k);
        }
      }
    } catch (_) {}
  }, []);

  const handleSignOut = async () => {
    setLoggingOut(true);
    try {
      await guardApi.logout();
    } finally {
      navigate('/g', { replace: true });
    }
  };

  const handleSubmitAnother = () => {
    // Purge draft cache completely before heading to login / scanner
    try {
      sessionStorage.removeItem('uss_guard_token');
      for (let i = localStorage.length - 1; i >= 0; i--) {
        const k = localStorage.key(i);
        if (k && k.startsWith('uss_draft_')) {
          localStorage.removeItem(k);
        }
      }
    } catch (_) {}
    navigate('/g', { replace: true });
  };

  return (
    <div className="min-h-screen bg-slate-900 flex flex-col justify-center items-center p-4">
      <div className="w-full max-w-md bg-white rounded-3xl shadow-2xl overflow-hidden border border-slate-100 p-6 sm:p-8 text-center space-y-5">
        {/* Animated Success Icon */}
        <div className="w-20 h-20 mx-auto rounded-full bg-emerald-100 text-emerald-600 flex items-center justify-center shadow-inner">
          <CheckCircle2 className="w-12 h-12 stroke-[2.5]" />
        </div>

        <div className="space-y-1.5">
          <span className="text-xs font-bold tracking-widest text-emerald-600 uppercase">
            Report Registered Live
          </span>
          <h2 className="text-2xl font-black text-slate-900 tracking-tight">
            Submission Successful
          </h2>
          <p className="text-xs text-slate-500">
            Your incident report has been securely transmitted and broadcast to the USS Dubai Super Admin Dashboard.
          </p>
        </div>

        {/* Reference Number Card */}
        <div className="bg-slate-50 p-4 rounded-2xl border border-slate-200 space-y-1">
          <span className="text-[10px] text-slate-400 font-bold uppercase tracking-wider">
            Official Reference Number
          </span>
          <div className="text-xl font-mono font-black text-blue-900 select-all tracking-wide">
            {refNumber}
          </div>
          <p className="text-xs text-slate-600 font-semibold mt-1">
            Site: {siteName}
          </p>
        </div>

        {/* Security Auto-Logout Notice */}
        <div className="p-3.5 bg-blue-50/80 border border-blue-200 text-blue-900 rounded-xl text-xs flex items-center justify-center gap-2">
          <Lock className="w-4 h-4 text-blue-700 shrink-0" />
          <span>Security Protocol: Your guard patrol session has been completed.</span>
        </div>

        {/* Action Buttons */}
        <div className="pt-2 space-y-3">
          {/* Explicit Sign Out Option requested by user */}
          <button
            type="button"
            onClick={handleSignOut}
            disabled={loggingOut}
            className="w-full py-3.5 bg-rose-600 hover:bg-rose-700 active:scale-[0.99] text-white font-bold rounded-xl shadow-md transition-all flex items-center justify-center gap-2 text-sm touch-btn"
          >
            <LogOut className="w-4 h-4 text-white" />
            <span>{loggingOut ? 'Signing Out...' : 'Sign Out Officer & Exit'}</span>
          </button>

          {/* Submit Another Incident Report Button */}
          <button
            type="button"
            onClick={handleSubmitAnother}
            className="w-full py-3.5 bg-[#0F2042] hover:bg-[#162e5c] active:scale-[0.99] text-white font-bold rounded-xl shadow-md transition-all flex items-center justify-center gap-2 text-sm touch-btn"
          >
            <PlusCircle className="w-4 h-4 text-emerald-400" />
            <span>Submit Another Incident Report</span>
          </button>
        </div>
      </div>
    </div>
  );
};
