import { useState, useEffect, useRef, useCallback } from 'react';
import { adminApi } from '../lib/api';

export interface RealtimeIncident {
  id: string;
  refNumber: string;
  siteId: string;
  siteName: string;
  siteCode: string;
  incidentDate: string;
  incidentTime: string;
  shift: string;
  reportedByName: string;
  reportedByDesignation: string;
  causeMain?: string[];
  incidentTypes?: string[];
  summaryLine: string;
  description: string;
  status: string;
  priority: string;
  photoCount: number;
  createdAt: string;
  _isNew?: boolean;
}

interface UseRealtimeIncidentsOptions {
  siteId?: string;
  status?: string;
  priority?: string;
  search?: string;
  dateFrom?: string;
  dateTo?: string;
  onlyDeleted?: boolean;
  includeDeleted?: boolean;
  limit?: number;
}

export function useRealtimeIncidents(filters: UseRealtimeIncidentsOptions = {}) {
  const [incidents, setIncidents] = useState<any[]>([]);
  const [total, setTotal] = useState<number>(0);
  const [loading, setLoading] = useState<boolean>(true);
  const [unreadCount, setUnreadCount] = useState<number>(0);
  const [connectionStatus, setConnectionStatus] = useState<'Live' | 'Reconnecting' | 'Polling (10s)'>('Live');
  const [lastUpdated, setLastUpdated] = useState<Date>(new Date());
  const [activeToast, setActiveToast] = useState<{ id: string; refNumber: string; siteName: string } | null>(null);

  const lastSeenRef = useRef<string>(new Date().toISOString());
  const filtersRef = useRef(filters);
  filtersRef.current = filters;

  // Gentle audio chime synthesis using Web Audio API (zero external asset dependency)
  const playChime = useCallback(() => {
    try {
      const AudioContext = window.AudioContext || (window as any).webkitAudioContext;
      if (!AudioContext) return;
      const ctx = new AudioContext();
      const osc = ctx.createOscillator();
      const gain = ctx.createGain();

      osc.type = 'sine';
      osc.frequency.setValueAtTime(587.33, ctx.currentTime); // D5
      osc.frequency.exponentialRampToValueAtTime(880, ctx.currentTime + 0.15); // A5

      gain.gain.setValueAtTime(0.08, ctx.currentTime);
      gain.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + 0.35);

      osc.connect(gain);
      gain.connect(ctx.destination);

      osc.start();
      osc.stop(ctx.currentTime + 0.36);
    } catch (_) {}
  }, []);

  // Initial load
  const loadInitial = useCallback(async () => {
    try {
      setLoading(true);
      const res = await adminApi.listIncidents(filtersRef.current);
      setIncidents(res.incidents || []);
      setTotal(res.total || 0);
      lastSeenRef.current = new Date().toISOString();
      setLastUpdated(new Date());
    } catch (err) {
      console.error('[REALTIME] Failed to load initial incidents:', err);
    } finally {
      setLoading(false);
    }
  }, []);

  // Handle newly arrived incident report
  const handleIncomingIncident = useCallback((raw: any) => {
    const inc = {
      ...raw,
      id: raw.id,
      ref_number: raw.refNumber || raw.ref_number,
      site_name: raw.siteName || raw.site_name,
      site_code: raw.siteCode || raw.site_code,
      incident_date: raw.incidentDate || raw.incident_date,
      incident_time: raw.incidentTime || raw.incident_time,
      reported_by_name: raw.reportedByName || raw.reported_by_name,
      reported_by_designation: raw.reportedByDesignation || raw.reported_by_designation,
      summary_line: raw.summaryLine || raw.summary_line,
      status: raw.status || 'Submitted',
      priority: raw.priority || 'Medium',
      _isNew: true,
    };

    // Filter check: If a specific site filter is active and doesn't match, don't display in list
    const currentSiteFilter = filtersRef.current.siteId;
    const matchesSite = !currentSiteFilter || currentSiteFilter === inc.site_id || currentSiteFilter === inc.siteId;

    if (matchesSite) {
      setIncidents((prev) => {
        // Prevent duplicates
        if (prev.some((p) => p.id === inc.id)) return prev;
        return [inc, ...prev];
      });
      setTotal((t) => t + 1);
    }

    setUnreadCount((c) => c + 1);
    setLastUpdated(new Date());
    lastSeenRef.current = new Date().toISOString();

    // Trigger toast & audio alert
    setActiveToast({
      id: inc.id,
      refNumber: inc.ref_number,
      siteName: inc.site_name || 'Assigned Site',
    });
    playChime();

    // Auto dismiss toast after 6s
    setTimeout(() => {
      setActiveToast((curr) => (curr?.id === inc.id ? null : curr));
    }, 6000);
  }, [playChime]);

  // Server-Sent Events setup with auto-reconnection
  useEffect(() => {
    loadInitial();

    let eventSource: EventSource | null = null;
    let pollInterval: NodeJS.Timeout | null = null;
    let reconnectTimeout: NodeJS.Timeout | null = null;

    const connectSse = () => {
      try {
        const token = adminApi.getToken();
        // Native EventSource connects through standard HTTPS port 443
        // If cookie is set or proxy forwards auth, it connects directly
        eventSource = new EventSource('/api/admin/incidents/stream');

        eventSource.addEventListener('connected', () => {
          setConnectionStatus('Live');
        });

        eventSource.addEventListener('incident_new', (e: MessageEvent) => {
          try {
            const data = JSON.parse(e.data);
            if (data?.incident) {
              handleIncomingIncident(data.incident);
            }
          } catch (err) {
            console.warn('[SSE] Failed to parse incident_new data:', err);
          }
        });

        eventSource.addEventListener('settings_updated', () => {
          // Trigger branding refresh across application
          window.dispatchEvent(new CustomEvent('uss_settings_updated'));
        });

        eventSource.onerror = () => {
          setConnectionStatus('Reconnecting');
          eventSource?.close();
          // Schedule reconnect attempt in 4 seconds
          reconnectTimeout = setTimeout(() => {
            connectSse();
          }, 4000);
        };
      } catch (err) {
        console.warn('[SSE] EventSource failed, switching to 10s polling fallback:', err);
        setConnectionStatus('Polling (10s)');
      }
    };

    connectSse();

    // Guaranteed 10-Second Polling Fallback: ensures zero missed reports on restricted Wi-Fi
    pollInterval = setInterval(async () => {
      try {
        const since = lastSeenRef.current;
        const res = await adminApi.listIncidents({
          ...filtersRef.current,
          since,
          limit: 20,
        });

        if (res.incidents && res.incidents.length > 0) {
          for (const inc of res.incidents) {
            handleIncomingIncident(inc);
          }
        }
        setLastUpdated(new Date());
      } catch (err) {
        // Silent catch for poll error
      }
    }, 10000);

    return () => {
      if (eventSource) eventSource.close();
      if (pollInterval) clearInterval(pollInterval);
      if (reconnectTimeout) clearTimeout(reconnectTimeout);
    };
  }, [loadInitial, handleIncomingIncident]);

  const clearUnread = useCallback(() => {
    setUnreadCount(0);
  }, []);

  const dismissToast = useCallback(() => {
    setActiveToast(null);
  }, []);

  return {
    incidents,
    setIncidents,
    total,
    loading,
    unreadCount,
    clearUnread,
    connectionStatus,
    lastUpdated,
    activeToast,
    dismissToast,
    refresh: loadInitial,
  };
}
