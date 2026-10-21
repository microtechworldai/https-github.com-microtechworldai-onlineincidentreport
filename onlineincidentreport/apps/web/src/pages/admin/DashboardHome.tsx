import React, { useEffect, useState, useMemo } from 'react';
import { Link } from 'react-router-dom';
import {
  AlertTriangle,
  CheckCircle2,
  Clock,
  Building2,
  FileText,
  TrendingUp,
  Download,
  ArrowRight,
  ShieldAlert,
  Flame,
  Trash2,
  Radio,
  Bell,
  X,
  LayoutList,
  LayoutGrid,
  ChevronLeft,
  ChevronRight,
  ArrowUpDown,
  ExternalLink,
  User,
} from 'lucide-react';
import { adminApi } from '../../lib/api';
import { useRealtimeIncidents } from '../../hooks/useRealtimeIncidents';
import { IncidentDetailModal } from './IncidentDetailModal';

export const DashboardHome: React.FC = () => {
  const [stats, setStats] = useState<any>(null);
  const [loading, setLoading] = useState<boolean>(true);
  const [selectedIncident, setSelectedIncident] = useState<any>(null);

  // View mode, sorting and pagination state
  const [viewMode, setViewMode] = useState<'list' | 'grid'>('list');
  const [currentPage, setCurrentPage] = useState<number>(1);
  const [sortOrder, setSortOrder] = useState<'newest' | 'oldest'>('newest');
  const pageSize = 8;

  // Local storage for tracking read incidents
  const [readIncidentIds, setReadIncidentIds] = useState<string[]>(() => {
    try {
      const saved = localStorage.getItem('uss_read_incidents');
      return saved ? JSON.parse(saved) : [];
    } catch {
      return [];
    }
  });

  const formatDateDDMMYYYY = (dStr: string) => {
    if (!dStr) return '';
    const parts = dStr.split('-');
    if (parts.length === 3 && parts[0].length === 4) {
      return `${parts[2]}/${parts[1]}/${parts[0]}`;
    }
    return dStr;
  };

  const markAsRead = (id: string) => {
    setReadIncidentIds((prev) => {
      if (prev.includes(id)) return prev;
      const updated = [...prev, id];
      try {
        localStorage.setItem('uss_read_incidents', JSON.stringify(updated));
      } catch (e) {
        console.error(e);
      }
      return updated;
    });
  };

  const isUnread = (inc: any) => {
    if (!inc) return false;
    if (inc._isNew) return true;
    const notReadLocally = !readIncidentIds.includes(inc.id);
    return notReadLocally && (inc.status === 'Submitted' || inc.status === 'Under Review' || !inc.status);
  };

  const openIncidentReview = (inc: any) => {
    markAsRead(inc.id);
    setSelectedIncident(inc);
  };

  const {
    connectionStatus,
    lastUpdated,
    activeToast,
    dismissToast,
    incidents: realtimeStream,
  } = useRealtimeIncidents({ limit: 300 });

  useEffect(() => {
    adminApi
      .getDashboardStats()
      .then((data) => setStats(data))
      .catch((err) => console.error(err))
      .finally(() => setLoading(false));
  }, []);

  // Update stats dynamically when new incidents stream in
  useEffect(() => {
    if (realtimeStream && realtimeStream.length > 0 && stats) {
      setStats((prev: any) => {
        if (!prev) return prev;
        const newOnes = realtimeStream.filter((r) => r._isNew);
        if (newOnes.length === 0) return prev;
        return {
          ...prev,
          todayCount: (prev.todayCount || 0) + newOnes.length,
          totalIncidents: (prev.totalIncidents || 0) + newOnes.length,
          latestSubmissions: [
            ...newOnes,
            ...(prev.latestSubmissions || []).filter(
              (old: any) => !newOnes.some((n) => n.id === old.id)
            ),
          ].slice(0, 200),
        };
      });
    }
  }, [realtimeStream]);

  // Combined, sorted incident list (newest on top always by default)
  const allIncidents = useMemo(() => {
    const map = new Map<string, any>();
    (stats?.latestSubmissions || []).forEach((inc: any) => {
      if (inc && inc.id) map.set(inc.id, inc);
    });
    (realtimeStream || []).forEach((inc: any) => {
      if (inc && inc.id) map.set(inc.id, { ...(map.get(inc.id) || {}), ...inc });
    });
    const list = Array.from(map.values()).filter((inc: any) => !inc.deleted_at);

    return list.sort((a: any, b: any) => {
      const timeA =
        new Date(`${a.incident_date || '1970-01-01'}T${a.incident_time || '00:00'}:00`).getTime() ||
        (a.created_at ? new Date(a.created_at).getTime() : 0);
      const timeB =
        new Date(`${b.incident_date || '1970-01-01'}T${b.incident_time || '00:00'}:00`).getTime() ||
        (b.created_at ? new Date(b.created_at).getTime() : 0);
      return sortOrder === 'newest' ? timeB - timeA : timeA - timeB;
    });
  }, [stats?.latestSubmissions, realtimeStream, sortOrder]);

  const totalPages = Math.max(1, Math.ceil(allIncidents.length / pageSize));
  const currentIncidents = allIncidents.slice(
    (currentPage - 1) * pageSize,
    currentPage * pageSize
  );

  const getPriorityBadge = (prio: string) => {
    switch (prio) {
      case 'Critical':
        return (
          <span className="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[10px] font-black bg-rose-100 text-rose-800 border border-rose-200">
            <span className="w-1.5 h-1.5 rounded-full bg-rose-600 animate-pulse"></span>
            Critical
          </span>
        );
      case 'High':
        return (
          <span className="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-amber-100 text-amber-800 border border-amber-200">
            <span className="w-1.5 h-1.5 rounded-full bg-amber-500"></span>
            High
          </span>
        );
      case 'Low':
        return (
          <span className="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-emerald-100 text-emerald-800 border border-emerald-200">
            <span className="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
            Low
          </span>
        );
      default:
        return (
          <span className="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-blue-100 text-blue-800 border border-blue-200">
            <span className="w-1.5 h-1.5 rounded-full bg-blue-500"></span>
            Medium
          </span>
        );
    }
  };

  const getStatusBadge = (st: string) => {
    switch (st) {
      case 'Resolved':
      case 'Submitted to Client':
        return (
          <span className="inline-flex items-center px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-emerald-100 text-emerald-800 border border-emerald-200">
            {st}
          </span>
        );
      case 'Follow-up Required':
        return (
          <span className="inline-flex items-center px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-amber-100 text-amber-800 border border-amber-200">
            {st}
          </span>
        );
      case 'Under Review':
        return (
          <span className="inline-flex items-center px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-purple-100 text-purple-800 border border-purple-200">
            {st}
          </span>
        );
      default:
        return (
          <span className="inline-flex items-center px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-blue-100 text-blue-800 border border-blue-200">
            {st || 'Submitted'}
          </span>
        );
    }
  };

  const downloadPdf = async (id: string, ref: string) => {
    try {
      const blob = await adminApi.exportSinglePdf(id);
      const url = window.URL.createObjectURL(blob);
      const a = document.createElement('a');
      a.href = url;
      a.download = `${ref}.pdf`;
      a.click();
    } catch {
      alert('Failed to download PDF');
    }
  };

  if (loading) {
    return (
      <div className="flex items-center justify-center min-h-[400px]">
        <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600"></div>
      </div>
    );
  }

  const statusCounts = stats?.statusCounts || {};
  const priorityCounts = stats?.priorityCounts || { Critical: 0, High: 0, Medium: 0, Low: 0 };
  const openFollowUps = (statusCounts['Under Review'] || 0) + (statusCounts['Follow-up Required'] || 0);
  const resolvedCount = statusCounts['Resolved'] || 0;
  const submittedClientCount = statusCounts['Submitted to Client'] || 0;
  const deletedReportsCount = stats?.deletedCount || 0;

  // Maximum trend count for scaling bar chart
  const maxTrend = Math.max(1, ...(stats?.trend30Days || []).map((t: any) => t.count));

  return (
    <div className="space-y-8">
      {/* Page Title & Quick Actions */}
      <div className="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-2 border-b border-slate-200/70">
        <div>
          <div className="flex flex-wrap items-center gap-3">
            <h1 className="text-2xl md:text-3xl font-black text-slate-900 tracking-tight">
              Security Operations Overview
            </h1>
            <div className="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold bg-white border border-slate-200 shadow-2xs">
              <span
                className={`w-2 h-2 rounded-full ${
                  connectionStatus === 'Live'
                    ? 'bg-emerald-500 animate-pulse'
                    : connectionStatus === 'Reconnecting'
                    ? 'bg-amber-500 animate-ping'
                    : 'bg-blue-500'
                }`}
              />
              <span
                className={
                  connectionStatus === 'Live'
                    ? 'text-emerald-700 font-extrabold'
                    : connectionStatus === 'Reconnecting'
                    ? 'text-amber-700 font-bold'
                    : 'text-slate-600'
                }
              >
                {connectionStatus}
              </span>
              <span className="text-[10px] text-slate-400 font-mono pl-1 border-l border-slate-200">
                {lastUpdated.toLocaleTimeString()}
              </span>
            </div>
          </div>
          <p className="text-sm text-slate-500 mt-1">
            Real-time incident monitoring across Dubai commercial, residential, and hospital facilities
          </p>
        </div>

        <div className="flex items-center gap-3">
          {deletedReportsCount > 0 && (
            <Link
              to="/admin/incidents?tab=trash"
              className="flex items-center gap-2 px-4 py-3 bg-rose-50 hover:bg-rose-100 text-rose-700 border border-rose-200 rounded-2xl text-xs font-bold transition-all"
            >
              <Trash2 className="w-4 h-4 text-rose-600" />
              <span>Trash Bin ({deletedReportsCount})</span>
            </Link>
          )}

          <Link
            to="/admin/daily-report"
            className="flex items-center gap-2 px-5 py-3 bg-blue-600 hover:bg-blue-700 text-white rounded-2xl text-xs font-bold shadow-sm hover:shadow transition-all"
          >
            <FileText className="w-4 h-4" />
            <span>Generate Daily Client Report</span>
          </Link>
        </div>
      </div>

      {/* New Realtime Incident Alert Toast */}
      {activeToast && (
        <div className="bg-gradient-to-r from-blue-700 to-indigo-800 text-white p-4 rounded-2xl shadow-xl flex items-center justify-between">
          <div className="flex items-center gap-3">
            <div className="w-9 h-9 rounded-xl bg-white/20 flex items-center justify-center shrink-0">
              <Bell className="w-5 h-5 animate-bounce" />
            </div>
            <div>
              <div className="text-xs font-bold text-blue-200 uppercase tracking-wider">
                ⚡ Real-Time Report Delivered (&lt; 2s)
              </div>
              <div className="text-sm font-black">
                {activeToast.refNumber} — {activeToast.siteName}
              </div>
            </div>
          </div>
          <div className="flex items-center gap-2">
            <Link
              to="/admin/incidents"
              className="px-3.5 py-1.5 bg-white text-blue-900 rounded-xl text-xs font-black hover:bg-blue-50 transition-colors shadow-xs"
            >
              View Report
            </Link>
            <button
              onClick={dismissToast}
              className="p-1 hover:bg-white/20 rounded-lg transition-colors text-white/80 hover:text-white"
            >
              <X className="w-4 h-4" />
            </button>
          </div>
        </div>
      )}

      {/* Enlarged KPI Stats Cards Grid */}
      <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5">
        {/* Today's Count */}
        <div className="bg-gradient-to-br from-blue-600 via-blue-600 to-indigo-700 text-white p-6 md:p-7 rounded-3xl shadow-lg shadow-blue-500/15 border border-blue-400/20 hover:-translate-y-0.5 hover:shadow-xl transition-all duration-200 flex items-center gap-5">
          <div className="w-14 h-14 rounded-2xl bg-white/15 backdrop-blur-sm text-white border border-white/20 flex items-center justify-center shrink-0 shadow-inner">
            <Clock className="w-7 h-7" />
          </div>
          <div>
            <span className="text-xs font-bold text-blue-100/90 uppercase tracking-wider block">
              Today's Incidents
            </span>
            <div className="text-3xl md:text-4xl font-black text-white mt-1">
              {stats?.todayCount ?? 0}
            </div>
            <span className="text-xs text-blue-100/80 font-medium">Recorded since 00:00 GST</span>
          </div>
        </div>

        {/* Total Incidents */}
        <div className="bg-gradient-to-br from-purple-600 via-indigo-600 to-violet-800 text-white p-6 md:p-7 rounded-3xl shadow-lg shadow-purple-500/15 border border-purple-400/20 hover:-translate-y-0.5 hover:shadow-xl transition-all duration-200 flex items-center gap-5">
          <div className="w-14 h-14 rounded-2xl bg-white/15 backdrop-blur-sm text-white border border-white/20 flex items-center justify-center shrink-0 shadow-inner">
            <FileText className="w-7 h-7" />
          </div>
          <div>
            <span className="text-xs font-bold text-purple-100/90 uppercase tracking-wider block">
              Total Logged
            </span>
            <div className="text-3xl md:text-4xl font-black text-white mt-1">
              {stats?.totalIncidents ?? 0}
            </div>
            <span className="text-xs text-purple-100/80 font-medium">All sites combined</span>
          </div>
        </div>

        {/* Open Follow-Ups */}
        <div className="bg-gradient-to-br from-amber-500 via-amber-600 to-orange-600 text-white p-6 md:p-7 rounded-3xl shadow-lg shadow-amber-500/15 border border-amber-300/30 hover:-translate-y-0.5 hover:shadow-xl transition-all duration-200 flex items-center gap-5">
          <div className="w-14 h-14 rounded-2xl bg-white/20 backdrop-blur-sm text-white border border-white/25 flex items-center justify-center shrink-0 shadow-inner">
            <AlertTriangle className="w-7 h-7" />
          </div>
          <div>
            <span className="text-xs font-bold text-amber-100 uppercase tracking-wider block">
              Pending Follow-ups
            </span>
            <div className="text-3xl md:text-4xl font-black text-white mt-1">
              {openFollowUps}
            </div>
            <span className="text-xs text-amber-100/90 font-medium">Requires supervisor action</span>
          </div>
        </div>

        {/* Resolved / Submitted */}
        <div className="bg-gradient-to-br from-emerald-600 via-teal-600 to-cyan-700 text-white p-6 md:p-7 rounded-3xl shadow-lg shadow-emerald-500/15 border border-emerald-400/20 hover:-translate-y-0.5 hover:shadow-xl transition-all duration-200 flex items-center gap-5">
          <div className="w-14 h-14 rounded-2xl bg-white/15 backdrop-blur-sm text-white border border-white/20 flex items-center justify-center shrink-0 shadow-inner">
            <CheckCircle2 className="w-7 h-7" />
          </div>
          <div>
            <span className="text-xs font-bold text-emerald-100/90 uppercase tracking-wider block">
              Client Submissions
            </span>
            <div className="text-3xl md:text-4xl font-black text-white mt-1">
              {submittedClientCount}
            </div>
            <span className="text-xs text-emerald-100/80 font-medium">{resolvedCount} resolved internally</span>
          </div>
        </div>
      </div>

      {/* Analytics Rows: Enlarged 30-Day Trend Chart & Priority + Site Breakdown */}
      <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {/* 30-Day Trend Chart (Taller and wider) */}
        <div className="lg:col-span-2 bg-white p-6 md:p-7 rounded-3xl border border-slate-200 shadow-xs flex flex-col justify-between">
          <div>
            <div className="flex items-center justify-between mb-4">
              <div className="flex items-center gap-2.5">
                <div className="p-2 bg-blue-50 text-blue-600 rounded-xl">
                  <TrendingUp className="w-5 h-5" />
                </div>
                <div>
                  <h2 className="font-extrabold text-base text-slate-900">
                    30-Day Incident Frequency Trend
                  </h2>
                  <p className="text-xs text-slate-400 font-medium">Daily incident distribution across all client properties</p>
                </div>
              </div>
              <span className="text-xs text-slate-500 font-bold bg-slate-100 px-3 py-1 rounded-full">Last 30 Days</span>
            </div>

            {/* Expanded Chart Area (h-64) */}
            <div className="h-64 flex items-end gap-2 pt-8 pb-3 border-b border-slate-100">
              {(stats?.trend30Days || []).map((item: any, i: number) => {
                const heightPct = Math.max(6, Math.round((item.count / maxTrend) * 100));
                const isToday = i === (stats.trend30Days.length - 1);

                return (
                  <div
                    key={item.date}
                    className="flex-1 flex flex-col items-center group relative h-full justify-end cursor-pointer"
                  >
                    <div
                      style={{ height: `${heightPct}%` }}
                      className={`w-full rounded-t-md transition-all ${
                        isToday
                          ? 'bg-blue-600 hover:bg-blue-700 shadow-sm'
                          : item.count > 0
                          ? 'bg-blue-300 hover:bg-blue-500'
                          : 'bg-slate-100 hover:bg-slate-200'
                      }`}
                    ></div>

                    {/* Tooltip */}
                    <div className="absolute -top-9 bg-slate-900 text-white text-[11px] font-bold px-2.5 py-1 rounded-lg opacity-0 group-hover:opacity-100 pointer-events-none transition-opacity whitespace-nowrap z-10 font-mono shadow-lg">
                      {item.date}: {item.count} incident{item.count === 1 ? '' : 's'}
                    </div>
                  </div>
                );
              })}
            </div>
            <div className="flex justify-between items-center text-xs text-slate-400 font-mono pt-3 font-medium">
              <span>30 days ago</span>
              <span>15 days ago</span>
              <span className="text-blue-600 font-bold">Today</span>
            </div>
          </div>
        </div>

        {/* Priority Breakdown & Incidents by Site */}
        <div className="space-y-6">
          {/* Priority Distribution Card */}
          <div className="bg-white p-6 rounded-3xl border border-slate-200 shadow-xs space-y-4">
            <div className="flex items-center justify-between">
              <div className="flex items-center gap-2">
                <Flame className="w-4 h-4 text-rose-500" />
                <h2 className="font-extrabold text-sm text-slate-900">Incident Priority Levels</h2>
              </div>
              <span className="text-[11px] font-bold text-slate-400 uppercase">Triage</span>
            </div>

            <div className="grid grid-cols-2 gap-3.5">
              <div className="p-4 sm:p-5 bg-gradient-to-br from-rose-500 via-rose-600 to-red-700 text-white rounded-2xl shadow-md shadow-rose-500/20 border border-rose-400/30 hover:scale-[1.02] transition-transform">
                <div className="flex items-center justify-between">
                  <span className="text-xs sm:text-sm font-extrabold text-white tracking-wide">Critical</span>
                  <span className="w-2.5 h-2.5 rounded-full bg-white shadow-xs animate-pulse"></span>
                </div>
                <div className="text-3xl sm:text-4xl font-black text-white mt-1.5">{priorityCounts['Critical'] || 0}</div>
              </div>

              <div className="p-4 sm:p-5 bg-gradient-to-br from-amber-500 via-amber-500 to-orange-600 text-white rounded-2xl shadow-md shadow-amber-500/20 border border-amber-300/30 hover:scale-[1.02] transition-transform">
                <div className="flex items-center justify-between">
                  <span className="text-xs sm:text-sm font-extrabold text-white tracking-wide">High</span>
                  <span className="w-2.5 h-2.5 rounded-full bg-white shadow-xs"></span>
                </div>
                <div className="text-3xl sm:text-4xl font-black text-white mt-1.5">{priorityCounts['High'] || 0}</div>
              </div>

              <div className="p-4 sm:p-5 bg-gradient-to-br from-blue-500 via-blue-600 to-indigo-600 text-white rounded-2xl shadow-md shadow-blue-500/20 border border-blue-400/30 hover:scale-[1.02] transition-transform">
                <div className="flex items-center justify-between">
                  <span className="text-xs sm:text-sm font-extrabold text-white tracking-wide">Medium</span>
                  <span className="w-2.5 h-2.5 rounded-full bg-white shadow-xs"></span>
                </div>
                <div className="text-3xl sm:text-4xl font-black text-white mt-1.5">{priorityCounts['Medium'] || 0}</div>
              </div>

              <div className="p-4 sm:p-5 bg-gradient-to-br from-emerald-500 via-teal-600 to-teal-700 text-white rounded-2xl shadow-md shadow-emerald-500/20 border border-emerald-400/30 hover:scale-[1.02] transition-transform">
                <div className="flex items-center justify-between">
                  <span className="text-xs sm:text-sm font-extrabold text-white tracking-wide">Low</span>
                  <span className="w-2.5 h-2.5 rounded-full bg-white shadow-xs"></span>
                </div>
                <div className="text-3xl sm:text-4xl font-black text-white mt-1.5">{priorityCounts['Low'] || 0}</div>
              </div>
            </div>
          </div>

          {/* Site Counts Breakdown */}
          <div className="bg-white p-6 rounded-3xl border border-slate-200 shadow-xs space-y-4">
            <div className="flex items-center justify-between">
              <div className="flex items-center gap-2">
                <Building2 className="w-4 h-4 text-blue-600" />
                <h2 className="font-extrabold text-sm text-slate-900">Incidents by Site</h2>
              </div>
              <Link to="/admin/sites" className="text-xs text-blue-600 font-bold hover:underline">
                View all
              </Link>
            </div>

            <div className="space-y-3">
              {Object.entries(stats?.siteCounts || {}).length === 0 ? (
                <p className="text-xs text-slate-400 italic">No site data available</p>
              ) : (
                Object.entries(stats?.siteCounts || {}).map(([siteName, count]: [string, any]) => (
                  <div key={siteName} className="space-y-1">
                    <div className="flex justify-between text-xs font-semibold">
                      <span className="text-slate-800 truncate">{siteName}</span>
                      <span className="text-blue-600 font-mono font-bold">{count}</span>
                    </div>
                    <div className="w-full bg-slate-100 rounded-full h-2.5 overflow-hidden">
                      <div
                        style={{
                          width: `${Math.min(100, Math.round(((count as number) / Math.max(1, stats.totalIncidents)) * 100))}%`,
                        }}
                        className="bg-blue-600 h-full rounded-full transition-all"
                      ></div>
                    </div>
                  </div>
                ))
              )}
            </div>
          </div>
        </div>
      </div>

      {/* Latest Incident Submissions with List/Grid View, Pagination & Review Modal */}
      <div className="bg-white rounded-3xl border border-slate-200 shadow-xs overflow-hidden">
        {/* Header with Title & Controls */}
        <div className="p-5 md:p-6 border-b border-slate-100 flex flex-col md:flex-row md:items-center justify-between gap-4">
          <div>
            <div className="flex items-center gap-2.5">
              <h2 className="font-extrabold text-base md:text-lg text-slate-900">
                Latest Incident Submissions
              </h2>
              <span className="px-2.5 py-0.5 bg-blue-50 text-blue-700 font-bold text-xs rounded-full border border-blue-200">
                {allIncidents.length} Reports
              </span>
            </div>
            <p className="text-xs text-slate-500 mt-1">
              Recent reports submitted by patrol guards • Sorted newest on top • Click any Reference No to open review & edit page
            </p>
          </div>

          <div className="flex flex-wrap items-center gap-2.5">
            {/* Sort Toggle: Newest First */}
            <button
              onClick={() => {
                setSortOrder((prev) => (prev === 'newest' ? 'oldest' : 'newest'));
                setCurrentPage(1);
              }}
              title="Toggle sorting order"
              className="flex items-center gap-1.5 px-3 py-2 bg-slate-50 hover:bg-slate-100 text-slate-700 rounded-xl text-xs font-bold border border-slate-200 transition-colors"
            >
              <ArrowUpDown className="w-3.5 h-3.5 text-slate-500" />
              <span>{sortOrder === 'newest' ? 'Newest on Top' : 'Oldest First'}</span>
            </button>

            {/* List / Grid View Switcher */}
            <div className="flex items-center bg-slate-100 p-1 rounded-xl border border-slate-200">
              <button
                onClick={() => setViewMode('list')}
                title="List View"
                className={`flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-bold transition-all ${
                  viewMode === 'list'
                    ? 'bg-white text-blue-900 shadow-xs'
                    : 'text-slate-600 hover:text-slate-900'
                }`}
              >
                <LayoutList className="w-4 h-4" />
                <span>List View</span>
              </button>
              <button
                onClick={() => setViewMode('grid')}
                title="Grid View"
                className={`flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-bold transition-all ${
                  viewMode === 'grid'
                    ? 'bg-white text-blue-900 shadow-xs'
                    : 'text-slate-600 hover:text-slate-900'
                }`}
              >
                <LayoutGrid className="w-4 h-4" />
                <span>Grid View</span>
              </button>
            </div>

            <Link
              to="/admin/incidents"
              className="text-xs text-blue-600 font-bold hover:underline flex items-center gap-1 ml-1"
            >
              <span>Open All Incidents</span>
              <ArrowRight className="w-3.5 h-3.5" />
            </Link>
          </div>
        </div>

        {/* View Mode: List View (Table) */}
        {viewMode === 'list' ? (
          <div className="overflow-x-auto dashboard-scrollbar pb-1">
            <table className="w-full text-left text-xs">
              <thead className="bg-slate-50 text-slate-600 font-bold uppercase tracking-wider text-[11px] border-b border-slate-200">
                <tr>
                  <th className="px-4 py-3.5">Reference No</th>
                  <th className="px-4 py-3.5">Priority</th>
                  <th className="px-4 py-3.5">Site</th>
                  <th className="px-4 py-3.5">Date & Time</th>
                  <th className="px-4 py-3.5">Type & Cause</th>
                  <th className="px-4 py-3.5">Reporter</th>
                  <th className="px-4 py-3.5">Status</th>
                  <th className="px-4 py-3.5 text-right">Actions</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-slate-100 font-medium text-slate-700">
                {currentIncidents.length === 0 ? (
                  <tr>
                    <td colSpan={8} className="px-5 py-8 text-center text-slate-400 italic">
                      No incident submissions found.
                    </td>
                  </tr>
                ) : (
                  currentIncidents.map((inc: any) => {
                    const unread = isUnread(inc);
                    const isSubmittedToClient =
                      inc.status === 'Submitted to Client' ||
                      (typeof inc.status === 'string' &&
                        (inc.status.trim().toLowerCase() === 'submitted to client' ||
                         inc.status.trim().toLowerCase().includes('client')));
                    return (
                      <tr
                        key={inc.id}
                        className={`hover:bg-slate-50/80 transition-colors ${
                          unread
                            ? 'bg-red-50/50 border-l-4 border-red-600'
                            : isSubmittedToClient
                            ? 'bg-emerald-50/30 border-l-4 border-emerald-800'
                            : ''
                        }`}
                      >
                        <td className="px-4 py-3.5 whitespace-nowrap">
                          <div className="flex items-center gap-2">
                            <button
                              onClick={() => openIncidentReview(inc)}
                              title="Click to open review, edit & export page"
                              className="font-mono font-bold text-blue-600 hover:text-blue-800 underline decoration-blue-400 hover:decoration-blue-700 hover:bg-blue-50/80 px-2 py-1 rounded-lg transition-all cursor-pointer text-left inline-flex items-center gap-1.5 text-xs group"
                            >
                              <span className="group-hover:underline">{inc.ref_number}</span>
                              <ExternalLink className="w-3.5 h-3.5 text-blue-500 group-hover:text-blue-700" />
                            </button>
                            {unread && (
                              <span className="px-2 py-0.5 rounded-full text-[9px] font-black bg-red-600 text-white tracking-wide animate-pulse shadow-xs">
                                UNREAD
                              </span>
                            )}
                          </div>
                        </td>
                        <td className="px-4 py-3.5 whitespace-nowrap">
                          {getPriorityBadge(inc.priority || 'Medium')}
                        </td>
                        <td className="px-4 py-3.5 font-bold text-slate-900 max-w-[180px]">
                          <span className="block truncate" title={inc.site_name}>
                            {inc.site_name}
                          </span>
                        </td>
                        <td className="px-4 py-3.5 whitespace-nowrap text-slate-600 font-mono">
                          <div className="font-semibold text-slate-900">{formatDateDDMMYYYY(inc.incident_date)}</div>
                          <div className="text-[11px] text-slate-500">
                            {inc.incident_time} {inc.shift ? `(${inc.shift})` : ''}
                          </div>
                        </td>
                        <td className="px-4 py-3.5 max-w-[200px]">
                          <span className="font-bold text-slate-900 block truncate">
                            {(inc.incident_types || []).join(', ') || 'Incident'}
                          </span>
                          <span className="text-slate-500 block text-[11px] truncate">
                            {(inc.cause_main || []).join(', ') || 'General'}
                          </span>
                        </td>
                        <td className="px-4 py-3.5 max-w-[140px] truncate" title={inc.reported_by_name}>
                          <span className="font-medium text-slate-700 block truncate">
                            {inc.reported_by_name || 'N/A'}
                          </span>
                        </td>
                        <td className="px-4 py-3.5 whitespace-nowrap">
                          {getStatusBadge(inc.status)}
                        </td>
                        <td className="px-4 py-3.5 text-right whitespace-nowrap">
                          <button
                            onClick={() => downloadPdf(inc.id, inc.ref_number)}
                            title="Download PDF Form"
                            className="p-1.5 text-slate-500 hover:text-blue-600 hover:bg-slate-100 rounded-lg transition-colors inline-flex items-center gap-1 text-xs font-bold"
                          >
                            <Download className="w-3.5 h-3.5" />
                            <span>PDF</span>
                          </button>
                        </td>
                      </tr>
                    );
                  })
                )}
              </tbody>
            </table>
          </div>
        ) : (
          /* View Mode: Grid View (Cards) */
          <div className="p-4 md:p-6 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
            {currentIncidents.length === 0 ? (
              <div className="col-span-full py-12 text-center text-slate-400 italic">
                No incident submissions found.
              </div>
            ) : (
              currentIncidents.map((inc: any) => {
                const unread = isUnread(inc);
                const isSubmittedToClient =
                  inc.status === 'Submitted to Client' ||
                  (typeof inc.status === 'string' &&
                    (inc.status.trim().toLowerCase() === 'submitted to client' ||
                     inc.status.trim().toLowerCase().includes('client')));

                return (
                  <div
                    key={inc.id}
                    className={`min-w-0 rounded-2xl p-4 flex flex-col justify-between transition-all ${
                      unread
                        ? 'border-2 border-red-500 bg-red-50/20 shadow-md ring-2 ring-red-400/20'
                        : isSubmittedToClient
                        ? 'border-2 border-emerald-800 bg-emerald-50/25 shadow-xs ring-1 ring-emerald-700/30'
                        : 'border border-slate-200 bg-white hover:border-slate-300 hover:shadow-md'
                    }`}
                  >
                    <div className="space-y-2.5 min-w-0">
                      {/* Top Badges Row: Status, Priority & UNREAD */}
                      <div className="flex items-center justify-between gap-1.5 min-h-[22px] flex-wrap">
                        <div className="flex items-center gap-1.5 flex-wrap">
                          {getStatusBadge(inc.status)}
                          {getPriorityBadge(inc.priority || 'Medium')}
                        </div>
                        {unread && (
                          <span className="px-2 py-0.5 rounded-full text-[9px] font-black bg-red-600 text-white tracking-wide animate-pulse shadow-xs shrink-0">
                            UNREAD
                          </span>
                        )}
                      </div>

                      {/* Reference Number: Full width, never wraps awkwardly */}
                      <button
                        type="button"
                        onClick={() => openIncidentReview(inc)}
                        title="Click to open review, edit & export page"
                        className="w-full font-mono font-bold text-blue-600 hover:text-blue-800 underline decoration-blue-300 hover:decoration-blue-700 text-xs inline-flex items-center justify-between gap-1 group py-0.5 text-left"
                      >
                        <span className="truncate group-hover:underline">{inc.ref_number}</span>
                        <ExternalLink className="w-3.5 h-3.5 text-blue-500 group-hover:text-blue-700 shrink-0" />
                      </button>

                      {/* Site Name */}
                      <div className="flex items-center gap-2 text-slate-900 font-bold text-xs sm:text-sm min-w-0">
                        <Building2 className="w-4 h-4 text-blue-600 shrink-0" />
                        <span className="truncate" title={inc.site_name}>
                          {inc.site_name}
                        </span>
                      </div>

                      {/* Date, Time & Shift (DD/MM/YYYY and 24hr format) */}
                      <div className="flex items-center gap-1.5 text-xs text-slate-500 font-mono flex-wrap">
                        <Clock className="w-3.5 h-3.5 text-slate-400 shrink-0" />
                        <span>
                          {formatDateDDMMYYYY(inc.incident_date)} • {inc.incident_time}
                        </span>
                        {inc.shift && (
                          <span className="px-1.5 py-0.5 bg-slate-100 rounded text-[10px] font-semibold text-slate-600">
                            {inc.shift}
                          </span>
                        )}
                      </div>

                      {/* Reporting Person */}
                      <div className="flex items-center gap-1.5 text-xs text-slate-600 min-w-0">
                        <User className="w-3.5 h-3.5 text-slate-400 shrink-0" />
                        <span className="text-[11px] text-slate-400">Reporter:</span>
                        <span className="font-semibold text-slate-800 truncate" title={inc.reported_by_name}>
                          {inc.reported_by_name || 'Security Officer'}
                        </span>
                      </div>

                      {/* Types & Causes */}
                      <div className="space-y-0.5 text-xs min-w-0">
                        <div className="font-bold text-slate-900 truncate" title={(inc.incident_types || []).join(', ')}>
                          {(inc.incident_types || []).join(', ') || 'Incident'}
                        </div>
                        <div className="text-[11px] text-slate-500 truncate" title={(inc.cause_main || []).join(', ')}>
                          {(inc.cause_main || []).join(', ') || 'General'}
                        </div>
                      </div>

                      {/* Summary / Description */}
                      <p
                        className="text-xs text-slate-600 line-clamp-2 leading-relaxed bg-slate-50 p-2.5 rounded-xl border border-slate-100 min-h-[46px]"
                        title={inc.summary_line || inc.description}
                      >
                        {inc.summary_line || inc.description || 'No summary text available.'}
                      </p>
                    </div>

                    {/* Card Footer: PDF export action */}
                    <div className="pt-2.5 mt-2.5 border-t border-slate-100 flex items-center justify-end">
                      <button
                        onClick={() => downloadPdf(inc.id, inc.ref_number)}
                        title="Download PDF"
                        className="p-1.5 text-slate-500 hover:text-blue-600 hover:bg-slate-100 rounded-lg transition-colors inline-flex items-center gap-1 text-xs font-bold"
                      >
                        <Download className="w-3.5 h-3.5" />
                        <span>PDF</span>
                      </button>
                    </div>
                  </div>
                );
              })
            )}
          </div>
        )}

        {/* Pagination Bar with Pages 1, 2, 3, 4, 5, 6... */}
        {allIncidents.length > 0 && (
          <div className="p-4 border-t border-slate-100 bg-slate-50/50 flex flex-col sm:flex-row items-center justify-between gap-3 text-xs">
            <div className="text-slate-500 font-medium">
              Showing{' '}
              <span className="font-bold text-slate-800">
                {(currentPage - 1) * pageSize + 1}
              </span>{' '}
              to{' '}
              <span className="font-bold text-slate-800">
                {Math.min(currentPage * pageSize, allIncidents.length)}
              </span>{' '}
              of <span className="font-bold text-slate-800">{allIncidents.length}</span> reports
            </div>

            <div className="flex items-center gap-1.5 flex-wrap">
              <button
                onClick={() => setCurrentPage((p) => Math.max(1, p - 1))}
                disabled={currentPage === 1}
                className="px-2.5 py-1.5 rounded-lg border border-slate-200 bg-white font-bold text-slate-600 hover:bg-slate-50 disabled:opacity-40 disabled:cursor-not-allowed transition-all inline-flex items-center gap-1"
              >
                <ChevronLeft className="w-3.5 h-3.5" />
                <span>Prev</span>
              </button>

              {Array.from({ length: totalPages }, (_, i) => i + 1).map((pageNum) => (
                <button
                  key={pageNum}
                  onClick={() => setCurrentPage(pageNum)}
                  className={`min-w-8 h-8 px-2 rounded-lg font-bold text-xs transition-all ${
                    currentPage === pageNum
                      ? 'bg-blue-600 text-white shadow-xs'
                      : 'bg-white border border-slate-200 text-slate-700 hover:bg-slate-100'
                  }`}
                >
                  {pageNum}
                </button>
              ))}

              <button
                onClick={() => setCurrentPage((p) => Math.min(totalPages, p + 1))}
                disabled={currentPage === totalPages}
                className="px-2.5 py-1.5 rounded-lg border border-slate-200 bg-white font-bold text-slate-600 hover:bg-slate-50 disabled:opacity-40 disabled:cursor-not-allowed transition-all inline-flex items-center gap-1"
              >
                <span>Next</span>
                <ChevronRight className="w-3.5 h-3.5" />
              </button>
            </div>
          </div>
        )}
      </div>

      {/* Incident Review & Sign-Off Modal */}
      {selectedIncident && (
        <IncidentDetailModal
          incident={selectedIncident}
          onClose={() => setSelectedIncident(null)}
          onUpdated={() => {
            setSelectedIncident(null);
            adminApi.getDashboardStats().then(setStats).catch(console.error);
          }}
        />
      )}
    </div>
  );
};
