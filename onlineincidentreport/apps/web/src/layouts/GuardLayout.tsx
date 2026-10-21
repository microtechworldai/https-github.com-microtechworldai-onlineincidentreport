import React, { useEffect, useState, useCallback } from 'react';
import { Outlet, useNavigate } from 'react-router-dom';
import { Shield, LogOut, Clock, WifiOff, MapPin, ExternalLink, AlertTriangle, RefreshCw } from 'lucide-react';
import { guardApi } from '../lib/api';

export const GuardLayout: React.FC = () => {
  const navigate = useNavigate();
  const [profile, setProfile] = useState<any>(null);
  const [dubaiTime, setDubaiTime] = useState<string>('');
  const [isOnline, setIsOnline] = useState<boolean>(navigator.onLine);
  const [isInAppBrowser, setIsInAppBrowser] = useState<boolean>(false);
  const [isCaptivePortal, setIsCaptivePortal] = useState<boolean>(false);
  const [reconnecting, setReconnecting] = useState<boolean>(false);

  // Check In-App Browser (WhatsApp, Instagram, Google Lens, Facebook)
  useEffect(() => {
    const ua = navigator.userAgent || '';
    const isInApp = /FBAN|FBAV|Instagram|WhatsApp|Line|MicroMessenger|Snapchat|GSA/i.test(ua);
    setIsInAppBrowser(isInApp);
  }, []);

  // Periodic Captive Portal & Health Probe
  const probeConnectivity = useCallback(async () => {
    if (!navigator.onLine) {
      setIsCaptivePortal(false);
      return;
    }

    try {
      const controller = new AbortController();
      const timeoutId = setTimeout(() => controller.abort(), 4000);
      const res = await fetch('/api/health', {
        method: 'GET',
        headers: { Accept: 'application/json' },
        signal: controller.signal,
        cache: 'no-store',
      });
      clearTimeout(timeoutId);

      const contentType = res.headers.get('content-type') || '';
      // If captive portal intercepted, it returns HTML login page
      if (contentType.includes('text/html') || res.status === 511) {
        setIsCaptivePortal(true);
      } else {
        setIsCaptivePortal(false);
      }
    } catch {
      // Network timeout or drop
    }
  }, []);

  useEffect(() => {
    // Check Guard Profile
    guardApi
      .getMe()
      .then((data) => setProfile(data))
      .catch(() => {
        // Not logged in -> redirect to guard QR login entry
        navigate('/g');
      });

    // Update Dubai Clock
    const updateClock = () => {
      const now = new Date();
      const timeStr = now.toLocaleTimeString('en-GB', {
        timeZone: 'Asia/Dubai',
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit',
      });
      setDubaiTime(timeStr);
    };

    updateClock();
    const clockTimer = setInterval(updateClock, 1000);

    const onOnline = () => {
      setIsOnline(true);
      setReconnecting(true);
      probeConnectivity();
      setTimeout(() => setReconnecting(false), 2500);
    };

    const onOffline = () => {
      setIsOnline(false);
    };

    window.addEventListener('online', onOnline);
    window.addEventListener('offline', onOffline);

    // Initial and periodic captive portal probe
    probeConnectivity();
    const probeTimer = setInterval(probeConnectivity, 30000);

    return () => {
      clearInterval(clockTimer);
      clearInterval(probeTimer);
      window.removeEventListener('online', onOnline);
      window.removeEventListener('offline', onOffline);
    };
  }, [navigate, probeConnectivity]);

  const handleLogout = async () => {
    try {
      await guardApi.logout();
    } finally {
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
    }
  };

  return (
    <div className="min-h-screen bg-slate-100 flex flex-col">
      {/* Top Mobile Bar */}
      <header className="sticky top-0 z-40 bg-[#0F2042] text-white shadow-md border-b border-blue-950">
        <div className="max-w-xl mx-auto px-4 py-2.5 flex items-center justify-between">
          <div className="flex items-center gap-2.5">
            <div className="w-9 h-9 rounded-lg bg-white p-1 flex items-center justify-center shadow-sm overflow-hidden shrink-0">
              <img
                src={profile?.settings?.logoUrl || '/assets/uss-logo.png'}
                alt="Logo"
                className="w-full h-full object-contain"
                onError={(e: any) => {
                  e.target.src = '/assets/uss-logo.png';
                }}
              />
            </div>
            <div>
              <h1 className="font-bold text-sm tracking-wide text-white leading-tight uppercase truncate max-w-[200px]">
                {profile?.settings?.companyName || 'ULTIMATE SECURITY'}
              </h1>
              <p className="text-[10px] text-amber-400 font-medium tracking-wider uppercase">
                {profile?.settings?.reportTitle || 'Incident Report System'}
              </p>
            </div>
          </div>

          <div className="flex items-center gap-2">
            {/* Live Dubai Time */}
            <div className="hidden sm:flex items-center gap-1.5 px-2.5 py-1 bg-white/10 rounded-lg text-xs font-mono text-slate-200">
              <Clock className="w-3.5 h-3.5 text-amber-400" />
              <span>{dubaiTime} (GST)</span>
            </div>

            {/* Manual Sign Out Button (Always Visible) */}
            <button
              onClick={handleLogout}
              className="flex items-center gap-1.5 px-3 py-1.5 bg-rose-600 hover:bg-rose-700 active:bg-rose-800 text-white rounded-xl text-xs font-bold shadow-sm transition-all touch-btn shrink-0"
              title="Sign Out Officer"
            >
              <LogOut className="w-3.5 h-3.5" />
              <span>Sign Out</span>
            </button>
          </div>
        </div>

        {/* Guard & Site Sub-Bar */}
        {profile?.guard && (
          <div className="bg-[#14264d] border-t border-blue-900/40 px-4 py-1.5 text-xs">
            <div className="max-w-xl mx-auto flex items-center justify-between">
              <div className="flex items-center gap-1.5 truncate">
                <span className="w-2 h-2 rounded-full bg-emerald-400 shrink-0"></span>
                <span className="font-semibold text-slate-200 truncate">
                  {profile.guard.fullName}
                </span>
                <span className="text-slate-400 text-[11px]">
                  ({profile.guard.staffId})
                </span>
              </div>

              {profile.site && (
                <div className="flex items-center gap-1 text-amber-300 font-medium text-[11px] truncate">
                  <MapPin className="w-3 h-3 shrink-0" />
                  <span className="truncate">{profile.site.name}</span>
                </div>
              )}
            </div>
          </div>
        )}

        {/* In-App Browser (WhatsApp, Instagram, Google Lens) Helper Banner */}
        {isInAppBrowser && (
          <div className="bg-indigo-900/95 text-indigo-100 px-4 py-2 text-xs flex items-center justify-between gap-2 border-t border-indigo-800">
            <div className="flex items-center gap-2">
              <ExternalLink className="w-4 h-4 text-indigo-300 shrink-0" />
              <span className="text-[11px]">In-app scanner detected. For full camera and file uploads, open in Chrome or Safari.</span>
            </div>
            <button
              onClick={() => {
                navigator.clipboard.writeText(window.location.href);
                alert('Page link copied! Paste into Chrome or Safari.');
              }}
              className="px-2.5 py-1 bg-white/20 hover:bg-white/30 rounded text-[10px] font-bold text-white shrink-0"
            >
              Copy Link
            </button>
          </div>
        )}

        {/* Captive Portal Alert */}
        {isCaptivePortal && (
          <div className="bg-rose-600 text-white px-4 py-2 text-center text-xs font-bold flex items-center justify-center gap-2">
            <AlertTriangle className="w-4 h-4 shrink-0" />
            <span>Your Wi-Fi needs sign-in, or switch to mobile data (4G/5G).</span>
          </div>
        )}

        {/* Reconnecting / Network Switch Status */}
        {reconnecting && isOnline && (
          <div className="bg-blue-600 text-white px-4 py-1 text-center text-xs font-medium flex items-center justify-center gap-1.5">
            <RefreshCw className="w-3.5 h-3.5 animate-spin" />
            <span>Network switched: Reconnecting and verifying session...</span>
          </div>
        )}

        {/* Offline Connectivity Warning Banner */}
        {!isOnline && (
          <div className="bg-amber-600 text-white px-4 py-1.5 text-center text-xs font-medium flex items-center justify-center gap-1.5 animate-pulse">
            <WifiOff className="w-3.5 h-3.5" />
            <span>Offline Mode: Your draft and photos are safely secured locally and will submit once back online.</span>
          </div>
        )}
      </header>

      {/* Main Single-Column Mobile Content Area */}
      <main className="flex-1 max-w-xl w-full mx-auto p-4 pb-24">
        <Outlet context={{ profile, isOnline }} />
      </main>
    </div>
  );
};
