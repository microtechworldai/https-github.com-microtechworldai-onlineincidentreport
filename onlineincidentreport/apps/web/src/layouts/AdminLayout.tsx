import React, { useEffect, useState } from 'react';
import { Outlet, NavLink, useNavigate, useLocation } from 'react-router-dom';
import {
  LayoutDashboard,
  FileText,
  FileSpreadsheet,
  Building2,
  Users,
  Settings,
  Database,
  LogOut,
  KeyRound,
  Clock,
  Menu,
  X,
  AlertTriangle,
} from 'lucide-react';
import { adminApi } from '../lib/api';

export const AdminLayout: React.FC = () => {
  const navigate = useNavigate();
  const location = useLocation();

  const [admin, setAdmin] = useState<any>(null);
  const [dubaiTime, setDubaiTime] = useState<string>('');
  const [mobileMenuOpen, setMobileMenuOpen] = useState<boolean>(false);

  useEffect(() => {
    adminApi
      .getMe()
      .then((res: any) => setAdmin(res.admin))
      .catch(() => {
        navigate('/admin/login');
      });

    const updateClock = () => {
      const now = new Date();
      setDubaiTime(
        now.toLocaleTimeString('en-GB', {
          timeZone: 'Asia/Dubai',
          hour: '2-digit',
          minute: '2-digit',
          second: '2-digit',
        })
      );
    };

    updateClock();
    const timer = setInterval(updateClock, 1000);
    return () => clearInterval(timer);
  }, [navigate]);

  const handleLogout = async () => {
    await adminApi.logout();
    navigate('/admin/login');
  };

  const navItems = [
    { label: 'Dashboard', path: '/admin', icon: LayoutDashboard },
    { label: 'Incident Reports', path: '/admin/incidents', icon: FileText },
    { label: 'Daily Client Reports', path: '/admin/daily-report', icon: FileSpreadsheet },
    { label: 'Sites & QR Codes', path: '/admin/sites', icon: Building2 },
    { label: 'Security Staff', path: '/admin/staff', icon: Users },
    { label: 'Settings & Options', path: '/admin/settings', icon: Settings },
    { label: 'Backups', path: '/admin/backups', icon: Database },
  ];

  return (
    <div className="min-h-screen bg-slate-50 flex flex-col md:flex-row">
      {/* Mobile Top Header */}
      <div className="md:hidden bg-[#0F2042] text-white p-4 flex items-center justify-between sticky top-0 z-50 shadow-md">
        <div className="flex items-center gap-3">
          <div className="w-10 h-10 rounded-xl bg-white p-1 flex items-center justify-center shrink-0 overflow-hidden shadow">
            <img
              src="/assets/uss-logo.png"
              alt="USS Logo"
              className="w-full h-full object-contain"
            />
          </div>
          <span className="font-bold text-base tracking-wide">USS SUPER ADMIN</span>
        </div>
        <button
          onClick={() => setMobileMenuOpen(!mobileMenuOpen)}
          className="p-1.5 text-slate-300 hover:text-white"
        >
          {mobileMenuOpen ? <X className="w-6 h-6" /> : <Menu className="w-6 h-6" />}
        </button>
      </div>

      {/* Desktop & Mobile Drawer Sidebar */}
      <aside
        className={`${
          mobileMenuOpen ? 'block' : 'hidden'
        } md:block w-full md:w-72 bg-[#0F2042] text-white flex flex-col shrink-0 z-40 fixed md:sticky top-0 h-screen overflow-y-auto border-r border-blue-950/80 shadow-lg`}
      >
        {/* Brand Banner */}
        <div className="p-5 border-b border-blue-900/40">
          <div className="flex items-center gap-3.5">
            <div className="w-14 h-14 rounded-2xl bg-white p-1.5 flex items-center justify-center shadow-md shrink-0 overflow-hidden">
              <img
                src="/assets/uss-logo.png"
                alt="USS Logo"
                className="w-full h-full object-contain"
              />
            </div>
            <div className="min-w-0">
              <h1 className="font-extrabold text-base tracking-wide text-white leading-tight truncate">
                ULTIMATE SECURITY
              </h1>
              <p className="text-xs text-amber-400 font-bold tracking-wider uppercase mt-0.5">
                Operations HQ Dubai
              </p>
            </div>
          </div>
        </div>

        {/* Navigation Items */}
        <nav className="flex-1 p-3.5 space-y-1.5">
          {navItems.map((item) => {
            const Icon = item.icon;
            const isActive =
              item.path === '/admin'
                ? location.pathname === '/admin'
                : location.pathname.startsWith(item.path);

            return (
              <NavLink
                key={item.path}
                to={item.path}
                onClick={() => setMobileMenuOpen(false)}
                className={`flex items-center gap-3.5 px-4 py-3 rounded-xl text-sm transition-all ${
                  isActive
                    ? 'bg-amber-600 text-white shadow-sm font-bold'
                    : 'text-slate-300 hover:bg-white/10 hover:text-white font-semibold'
                }`}
              >
                <Icon className={`w-5 h-5 ${isActive ? 'text-white' : 'text-slate-400'}`} />
                <span>{item.label}</span>
              </NavLink>
            );
          })}
        </nav>

        {/* Change Password Prompt if required */}
        {admin?.mustChangePassword && (
          <div className="mx-3.5 mb-3.5 p-3.5 bg-amber-500/20 border border-amber-400/40 rounded-xl text-sm space-y-2">
            <div className="flex items-center gap-2 text-amber-300 font-bold">
              <AlertTriangle className="w-4.5 h-4.5 shrink-0" />
              <span>Password Change Required</span>
            </div>
            <p className="text-xs text-slate-300">
              Initial password must be updated before regular operations.
            </p>
            <button
              onClick={() => navigate('/admin/change-password')}
              className="w-full py-2 bg-amber-500 hover:bg-amber-600 text-slate-900 font-bold rounded-lg text-xs transition-colors"
            >
              Update Password Now
            </button>
          </div>
        )}

        {/* Admin Profile & Logout Footer */}
        <div className="p-4 border-t border-blue-900/40 bg-black/20">
          <div className="flex items-center justify-between mb-2">
            <div className="min-w-0">
              <span className="text-xs uppercase font-extrabold text-amber-400 tracking-wider block">
                Super Admin
              </span>
              <p className="text-sm font-extrabold text-white truncate">
                {admin?.fullName || 'Super Administrator'}
              </p>
            </div>
            <button
              onClick={() => navigate('/admin/change-password')}
              title="Change Password"
              className="p-1.5 text-slate-400 hover:text-amber-400 rounded-lg hover:bg-white/10 transition-colors"
            >
              <KeyRound className="w-5 h-5" />
            </button>
          </div>

          <button
            onClick={handleLogout}
            className="w-full mt-1.5 flex items-center justify-center gap-2.5 py-2.5 bg-red-600/20 hover:bg-red-600 text-red-300 hover:text-white rounded-xl text-sm font-bold transition-all border border-red-500/30 hover:border-transparent"
          >
            <LogOut className="w-4 h-4" />
            <span>Sign Out</span>
          </button>
        </div>
      </aside>

      {/* Main Administrative Workplace */}
      <div className="flex-1 flex flex-col min-w-0 overflow-y-auto">
        {/* Top Desktop Bar */}
        <header className="hidden md:flex bg-white border-b border-slate-200 px-8 py-3.5 items-center justify-between shadow-xs">
          <div className="flex items-center gap-2 text-xs text-slate-500">
            <span className="font-semibold text-slate-800">USS Dubai Operations Dashboard</span>
            <span>•</span>
            <span>Client Incident Management Portal</span>
          </div>

          <div className="flex items-center gap-4">
            <div className="flex items-center gap-1.5 px-3 py-1 bg-slate-100 rounded-lg text-xs font-mono font-medium text-slate-700">
              <Clock className="w-3.5 h-3.5 text-blue-600" />
              <span>{dubaiTime} GST (Asia/Dubai)</span>
            </div>
          </div>
        </header>

        {/* Content Outlet */}
        <main className="flex-1 p-4 md:p-8">
          <Outlet context={{ admin }} />
        </main>
      </div>
    </div>
  );
};
